<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
                $tickets[] = $this->presenter($tenant, $ligne);
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

    /** Mise en forme d'une ligne pour le kanban. */
    private function presenter(Tenant $tenant, array $ligne): array
    {
        $creeLe = \Carbon\Carbon::parse($ligne['created_at']);

        return [
            'id'          => (int) $ligne['id'],
            'tenant_id'   => $tenant->id,
            'tenant'      => $tenant->name,
            'slug'        => $tenant->slug,
            'author'      => $ligne['author_name'],
            'role'        => $ligne['author_role'],
            'type'        => $ligne['type'],
            'subject'     => $ligne['subject'],
            'message'     => $ligne['message'],
            'context_url' => $ligne['context_url'],
            'status'      => in_array($ligne['status'], self::STATUTS, true) ? $ligne['status'] : 'nouveau',
            'reply'       => $ligne['reply'],
            'handled_by'  => $ligne['handled_by'],
            'at'          => $creeLe->format('d/m/Y H:i'),
            'ago'         => $creeLe->diffForHumans(),
            'ts'          => $creeLe->timestamp,
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
