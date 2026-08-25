<?php

namespace App\Http\Controllers;

use App\Models\AssistanceSession;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Tickets d'intervention remontés par le personnel des établissements depuis
 * le bouton « Suggestion » de wetchah_app, agrégés ici pour le kanban de
 * l'onglet Support › Justification.
 *
 * Ces tickets vivent dans la base de chaque établissement — comme les journaux
 * d'audit applicatifs. L'ERP les lit là où ils sont écrits et y réécrit le
 * statut : l'auteur voit donc l'avancement depuis sa propre application, sans
 * qu'aucune copie n'ait à être tenue à jour de part et d'autre.
 */
class SupportTicketController extends Controller
{
    public const STATUTS = ['nouveau', 'en_cours', 'resolu', 'rejete'];

    public function __construct(private readonly TenantDatabase $bases)
    {
    }

    /** Tous les tickets des établissements provisionnés, du plus récent au plus ancien. */
    public function index(Request $request): JsonResponse
    {
        $this->autoriser();

        $tenants = Tenant::query()
            ->whereNotNull('provisioned_at')
            ->when($request->filled('slug'), fn ($q) => $q->where('slug', $request->string('slug')))
            ->orderBy('name')
            ->get();

        $tickets      = [];
        $injoignables = [];
        $aMettreAJour = [];

        // Les sessions d'assistance déjà ouvertes sont chargées en une fois :
        // afficher le kanban ne doit pas coûter une requête par ticket. Lire
        // cette liste n'en ouvre aucune — l'assistance se demande (voir
        // assist()), elle ne se déclenche pas en consultant une page.
        $sessions = $this->sessionsVivantes($tenants->pluck('id')->all());

        foreach ($tenants as $tenant) {
            try {
                $lecture = $this->bases->supportTickets($tenant);
            } catch (\Exception $e) {
                $injoignables[] = $tenant->name;
                continue;
            }

            if (!$lecture['disponible']) {
                // L'application de cet établissement est antérieure au bouton
                // « Suggestion » : rien à lire tant que son image n'est pas à jour.
                $aMettreAJour[] = $tenant->name;
                continue;
            }

            foreach ($lecture['tickets'] as $ligne) {
                $tickets[] = $this->presenter($tenant, $ligne, $sessions);
            }
        }

        usort($tickets, fn ($a, $b) => $b['ts'] <=> $a['ts']);

        return response()->json([
            'tickets'      => $tickets,
            'counts'       => $this->compter($tickets),
            'unreachable'  => $injoignables,
            'outdated'     => $aMettreAJour,
            'generated_at' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Création manuelle d'un ticket depuis l'ERP. L'assistance s'ouvre dans la
     * foulée : contrairement à l'affichage du kanban, saisir un ticket est un
     * acte délibéré du support sur un établissement qu'il a lui-même désigné.
     */
    public function store(Request $request): JsonResponse
    {
        $this->autoriser();

        $valide = $request->validate([
            'tenant_id'   => ['required', 'integer', 'exists:tenants,id'],
            'type'        => ['required', 'in:probleme,suggestion'],
            'subject'     => ['required', 'string', 'min:3', 'max:160'],
            'message'     => ['required', 'string', 'min:5', 'max:2000'],
            'context_url' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant = Tenant::findOrFail($valide['tenant_id']);
        $admin  = Auth::user();

        try {
            $ticketId = $this->bases->createSupportTicket(
                $tenant,
                $admin->name . ' (Support ERP)',
                'tech_admin',
                $valide['type'],
                $valide['subject'],
                $valide['message'],
                $valide['context_url'] ?? null
            );
        } catch (\Exception $e) {
            Log::error("Création de ticket impossible sur « {$tenant->slug} »", ['exception' => $e]);

            return response()->json([
                'ok'      => false,
                'message' => "La base de « {$tenant->name} » est injoignable : le ticket n'a pas pu être créé.",
            ], 503);
        }

        if (!$ticketId) {
            return response()->json(['ok' => false, 'message' => 'Échec de la création du ticket.'], 500);
        }

        $session = AssistanceSession::openForTicket(
            $tenant,
            $ticketId,
            $valide['subject'],
            $admin->name,
            $admin
        );

        AuditLog::record(
            $admin->id,
            'support_ticket_create',
            "Création manuelle du ticket #{$ticketId} pour « {$tenant->name} » avec ouverture automatique d'assistance.",
            'support',
            [
                'tenant_id' => $tenant->id,
                'slug'      => $tenant->slug,
                'ticket_id' => $ticketId,
                'subject'   => $valide['subject'],
            ]
        );

        return response()->json([
            'ok'             => true,
            'ticket_id'      => $ticketId,
            'assistance_url' => $session?->entryUrl(),
        ], 201);
    }

    /**
     * Ouverture explicite d'une session d'assistance sur un ticket du kanban.
     *
     * Entrer en assistance connecte le support dans l'application de
     * l'établissement sous l'identité de son administrateur : c'est un acte
     * qui se demande, ticket par ticket, et que le journal d'audit doit
     * pouvoir imputer. Le motif est reconstruit à partir du ticket relu dans
     * la base de l'établissement, pas à partir de ce que le navigateur
     * envoie — au passage, un ticket inexistant n'ouvre aucun accès.
     */
    public function assist(Request $request): JsonResponse
    {
        $this->autoriser();

        $valide = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'ticket_id' => ['required', 'integer', 'min:1'],
        ]);

        $tenant = Tenant::findOrFail($valide['tenant_id']);
        $admin  = Auth::user();

        try {
            $ligne = $this->bases->supportTicket($tenant, $valide['ticket_id']);
        } catch (\Exception $e) {
            Log::error("Assistance impossible sur « {$tenant->slug} »", ['exception' => $e]);

            return response()->json([
                'ok'      => false,
                'message' => "La base de « {$tenant->name} » est injoignable : l'assistance n'a pas pu être ouverte.",
            ], 503);
        }

        if (!$ligne) {
            return response()->json(['ok' => false, 'message' => 'Ticket introuvable dans cet établissement.'], 404);
        }

        $session = AssistanceSession::openForTicket(
            $tenant,
            (int) $ligne['id'],
            $ligne['subject'] ?? '',
            $ligne['author_name'] ?? 'Établissement',
            $admin
        );

        if (!$session) {
            return response()->json([
                'ok'      => false,
                'message' => "Assistance indisponible sur « {$tenant->name} » : établissement non provisionné, ou secret d'assistance absent côté ERP.",
            ], 422);
        }

        return response()->json(['ok' => true, 'assistance_url' => $session->entryUrl()]);
    }

    /**
     * Déplacement d'un ticket dans le kanban, avec éventuellement la réponse
     * faite à son auteur. L'action est auditée : traiter un ticket, c'est
     * écrire dans la base d'un établissement.
     */
    public function update(Request $request): JsonResponse
    {
        $this->autoriser();

        $valide = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'ticket_id' => ['required', 'integer', 'min:1'],
            'status'    => ['required', 'in:' . implode(',', self::STATUTS)],
            'reply'     => ['nullable', 'string', 'max:2000'],
        ]);

        $tenant = Tenant::findOrFail($valide['tenant_id']);
        $admin  = Auth::user();

        try {
            $applique = $this->bases->updateSupportTicket(
                $tenant,
                $valide['ticket_id'],
                $valide['status'],
                $valide['reply'] ?? null,
                $admin->name
            );
        } catch (\Exception $e) {
            Log::error("Traitement de ticket impossible sur « {$tenant->slug} »", ['exception' => $e]);

            return response()->json([
                'ok'      => false,
                'message' => "La base de « {$tenant->name} » est injoignable : le ticket n'a pas été modifié.",
            ], 503);
        }

        if (!$applique) {
            return response()->json(['ok' => false, 'message' => 'Ticket introuvable dans cet établissement.'], 404);
        }

        AuditLog::record(
            $admin->id,
            'support_ticket_update',
            "Ticket #{$valide['ticket_id']} de « {$tenant->name} » passé au statut « {$valide['status']} »"
                . (($valide['reply'] ?? '') !== '' ? ' avec réponse à son auteur.' : '.'),
            'support',
            [
                'tenant_id' => $tenant->id,
                'slug'      => $tenant->slug,
                'ticket_id' => $valide['ticket_id'],
                'status'    => $valide['status'],
                'replied'   => ($valide['reply'] ?? '') !== '',
            ]
        );

        return response()->json(['ok' => true]);
    }

    private function autoriser(): void
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) {
            abort(403, "Accès interdit - Rôle TECH_ADMIN requis.");
        }
    }

    /**
     * Sessions d'assistance encore vivantes rattachées à un ticket, indexées
     * par « tenant:ticket » pour que le kanban les retrouve sans requête.
     *
     * @param  array<int, int> $tenantIds
     * @return Collection<string, AssistanceSession>
     */
    private function sessionsVivantes(array $tenantIds): Collection
    {
        return AssistanceSession::query()
            ->with(['tenant', 'user'])
            ->whereIn('tenant_id', $tenantIds)
            ->whereNotNull('ticket_id')
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->get()
            ->keyBy(fn (AssistanceSession $s) => $s->tenant_id . ':' . $s->ticket_id);
    }

    /**
     * Mise en forme d'une ligne pour le kanban.
     *
     * @param Collection<string, AssistanceSession> $sessions
     */
    private function presenter(Tenant $tenant, array $ligne, Collection $sessions): array
    {
        $creeLe   = \Carbon\Carbon::parse($ligne['created_at']);
        $ticketId = (int) $ligne['id'];
        $session  = $sessions->get($tenant->id . ':' . $ticketId);

        return [
            'id'             => $ticketId,
            'tenant_id'      => $tenant->id,
            'tenant'         => $tenant->name,
            'slug'           => $tenant->slug,
            'author'         => $ligne['author_name'],
            'role'           => $ligne['author_role'],
            'type'           => $ligne['type'],
            'subject'        => $ligne['subject'],
            'message'        => $ligne['message'],
            'context_url'    => $ligne['context_url'],
            'status'         => in_array($ligne['status'], self::STATUTS, true) ? $ligne['status'] : 'nouveau',
            'reply'          => $ligne['reply'],
            'handled_by'     => $ligne['handled_by'],
            'at'             => $creeLe->format('d/m/Y H:i'),
            'ago'            => $creeLe->diffForHumans(),
            'ts'             => $creeLe->timestamp,
            'assistance_url' => $session?->entryUrl(),
        ];
    }

    /** @param array<int, array> $tickets */
    private function compter(array $tickets): array
    {
        $counts = array_fill_keys(self::STATUTS, 0);

        foreach ($tickets as $ticket) {
            $counts[$ticket['status']]++;
        }

        $counts['total'] = count($tickets);

        return $counts;
    }
}
