<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PermissionMatrixVersion;
use App\Models\Tenant;
use App\Services\PermissionMatrixClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Droits & rôles d'un établissement.
 *
 * L'écran montre le gabarit livré avec l'application — quels rôles détiennent
 * quel droit par défaut —, les écarts posés par la console et ceux posés par
 * l'hôtel, et permet de régler la couche de la console. Seuls les écarts sont
 * transmis : le gabarit suit le code de l'établissement et se périmerait s'il
 * était recopié ici.
 *
 * Un établissement d'avant la version 2 de l'API garde l'ancien écran.
 */
class TenantPermissionMatrixController extends Controller
{
    /** Onglets de l'écran v2. */
    private const ONGLETS = ['matrice', 'administrateurs', 'exceptions', 'alertes', 'historique'];

    public function __construct(private readonly PermissionMatrixClient $client)
    {
    }

    private function authorizeTenant(Tenant $tenant): void
    {
        $user = Auth::user();

        abort_unless($user, 401);
        abort_unless(
            $user->isTechAdmin() || $tenant->owner_id === $user->id,
            403,
            "Vous n'avez pas l'autorisation de gérer cet établissement."
        );
    }

    private function prefixe(): string
    {
        return Auth::user()->isTechAdmin() ? 'tech.' : 'business.';
    }

    public function show(Request $request, Tenant $tenant): View
    {
        $this->authorizeTenant($tenant);

        $matrice = $this->client->fetch($tenant);

        if ($matrice !== null && (int) ($matrice['version'] ?? 1) >= 2) {
            return view('establishments.permissions-v2', [
                'tenant'   => $tenant,
                'matrice'  => $matrice,
                'prefixe'  => $this->prefixe(),
                'onglet'   => in_array($request->query('onglet'), self::ONGLETS, true) ? $request->query('onglet') : 'matrice',
                'versions' => PermissionMatrixVersion::where('tenant_id', $tenant->id)
                    ->orderByDesc('numero')->limit(50)->get(),
            ]);
        }

        return view('establishments.permissions', [
            'tenant'  => $tenant,
            'matrice' => $matrice,
            // Distinguer « pas encore provisionné » de « injoignable » : le
            // premier est normal, le second demande une action.
            'raison'  => $matrice !== null
                ? null
                : ($tenant->provisioned_at
                    ? "L'établissement est injoignable. Vérifiez qu'il est démarré."
                    : "L'établissement n'est pas encore provisionné."),
        ]);
    }

    /** Aperçu d'un lot : qui gagne ou perd quoi, quels cumuls il ouvre. */
    public function preview(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorizeTenant($tenant);

        $valide = $request->validate($this->reglesDuLot());

        $resultat = $this->client->preview($tenant, $valide['ecarts']);

        return response()->json($resultat, $resultat['ok'] ? 200 : 422);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        // L'écran v2 envoie l'empreinte de la couche qu'il a ouverte ; l'ancien
        // écran, non.
        if (!$request->has('empreinte')) {
            return $this->updateV1($request, $tenant);
        }

        // Un formulaire n'envoie pas de tableau vide : sans écart, la couche
        // de la console est vidée — une décision comme une autre.
        if (!is_array($request->input('ecarts'))) {
            $request->merge(['ecarts' => []]);
        }

        $valide = $request->validate($this->reglesDuLot() + [
            'empreinte'  => ['present', 'nullable', 'string', 'max:64'],
            // Une matrice de droits qui change sans motif écrit ne se contrôle
            // pas six mois plus tard.
            'motif'      => ['required', 'string', 'max:255'],
            'derogation' => ['nullable', 'boolean'],
        ], [
            'motif.required' => 'Indiquez le motif de la modification.',
        ]);

        $this->consignerLEtatInitial($tenant);

        $resultat = $this->client->push($tenant, $valide['ecarts'], [
            'derogation' => $request->boolean('derogation'),
            'empreinte'  => $valide['empreinte'] ?? '',
            'auteur'     => $this->auteur(),
        ]);

        $version = null;
        if ($resultat['ok']) {
            $version = PermissionMatrixVersion::consigner($tenant, [
                'nature'     => PermissionMatrixVersion::MODIFICATION,
                'ecarts'     => array_values($valide['ecarts']),
                'motif'      => $valide['motif'],
                'derogation' => ($resultat['cumuls'] ?? []) !== [],
                'cumuls'     => $resultat['cumuls'] ?? [],
                'user_id'    => Auth::id(),
                'auteur'     => Auth::user()->name,
            ]);
        }

        $this->journaliser($tenant, $valide['ecarts'], $resultat, $valide['motif'], $version);

        return redirect()
            ->route($this->prefixe() . 'establishments.permissions', ['tenant' => $tenant])
            ->with($resultat['ok'] ? 'success' : 'error', $resultat['ok']
                ? "Version {$version->numero} enregistrée : " . $resultat['message']
                : $resultat['message']);
    }

    /**
     * Revient à une version antérieure : sa couche est renvoyée telle quelle,
     * et devient une nouvelle version. L'histoire ne se réécrit pas.
     */
    public function restore(Request $request, Tenant $tenant, PermissionMatrixVersion $version): RedirectResponse
    {
        $this->authorizeTenant($tenant);
        abort_unless($version->tenant_id === $tenant->id, 404);

        $valide = $request->validate([
            'motif'      => ['required', 'string', 'max:255'],
            'derogation' => ['nullable', 'boolean'],
        ], [
            'motif.required' => 'Indiquez pourquoi vous revenez à cette version.',
        ]);

        $this->consignerLEtatInitial($tenant);

        $resultat = $this->client->push($tenant, $version->ecarts ?? [], [
            'derogation' => $request->boolean('derogation'),
            'auteur'     => $this->auteur(),
        ]);

        $nouvelle = null;
        if ($resultat['ok']) {
            $nouvelle = PermissionMatrixVersion::consigner($tenant, [
                'nature'           => PermissionMatrixVersion::RETOUR,
                'ecarts'           => $version->ecarts ?? [],
                'motif'            => "Retour à la version {$version->numero} : {$valide['motif']}",
                'derogation'       => ($resultat['cumuls'] ?? []) !== [],
                'cumuls'           => $resultat['cumuls'] ?? [],
                'user_id'          => Auth::id(),
                'auteur'           => Auth::user()->name,
                'restauree_depuis' => $version->id,
            ]);
        }

        $this->journaliser(
            $tenant,
            $version->ecarts ?? [],
            $resultat,
            "Retour à la version {$version->numero} : {$valide['motif']}",
            $nouvelle
        );

        return redirect()
            ->route($this->prefixe() . 'establishments.permissions', ['tenant' => $tenant, 'onglet' => 'historique'])
            ->with($resultat['ok'] ? 'success' : 'error', $resultat['ok']
                ? "Version {$version->numero} rétablie (enregistrée comme version {$nouvelle->numero})."
                : $resultat['message']);
    }

    /** Ancien écran : établissement d'avant la version 2 de l'API. */
    private function updateV1(Request $request, Tenant $tenant): RedirectResponse
    {
        $valide = $request->validate($this->reglesDuLot());

        $resultat = $this->client->push($tenant, $valide['ecarts']);

        $this->journaliser($tenant, $valide['ecarts'], $resultat, null, null);

        return redirect()
            ->route($this->prefixe() . 'establishments.permissions', ['tenant' => $tenant])
            ->with($resultat['ok'] ? 'success' : 'error', $resultat['message']);
    }

    /** @return array<string, list<string>> */
    private function reglesDuLot(): array
    {
        return [
            'ecarts'              => ['present', 'array'],
            'ecarts.*.role'       => ['required', 'string', 'max:64'],
            'ecarts.*.permission' => ['required', 'string', 'max:128'],
            'ecarts.*.effect'     => ['required', 'in:allow,deny'],
            'ecarts.*.scope'      => ['nullable', 'in:propre,departement,etablissement'],
            'ecarts.*.reason'     => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Avant le tout premier enregistrement, la couche trouvée dans
     * l'établissement devient la version 0 : sans elle, le premier
     * enregistrement effacerait un état auquel on ne pourrait plus revenir.
     */
    private function consignerLEtatInitial(Tenant $tenant): void
    {
        if (PermissionMatrixVersion::where('tenant_id', $tenant->id)->exists()) {
            return;
        }

        $matrice = $this->client->fetch($tenant);

        if ($matrice === null) {
            return;
        }

        $couche = [];
        foreach ($matrice['ecarts'] ?? [] as $ecart) {
            if (($ecart['subject_type'] ?? null) !== 'role' || ($ecart['origin'] ?? 'erp') !== 'erp') {
                continue;
            }

            $couche[] = array_filter([
                'role'       => $ecart['subject_id'],
                'permission' => $ecart['permission'],
                'effect'     => $ecart['effect'],
                'scope'      => $ecart['scope'] ?? null,
                'reason'     => $ecart['reason'] ?? null,
            ], static fn ($v) => $v !== null);
        }

        PermissionMatrixVersion::create([
            'tenant_id' => $tenant->id,
            'numero'    => 0,
            'nature'    => PermissionMatrixVersion::ETAT_INITIAL,
            'ecarts'    => $couche,
            'motif'     => "État trouvé dans l'établissement avant le premier enregistrement depuis la console.",
        ]);
    }

    private function auteur(): string
    {
        return Auth::user()->name . ' <' . Auth::user()->email . '>';
    }

    private function journaliser(Tenant $tenant, array $ecarts, array $resultat, ?string $motif, ?PermissionMatrixVersion $version): void
    {
        AuditLog::record(
            Auth::id(),
            'permission_matrix',
            "Matrice des droits de {$tenant->name} : " . count($ecarts) . ' écart(s) — '
                . ($resultat['ok'] ? 'appliquée' . ($version ? " (version {$version->numero})" : '') : 'échec : ' . $resultat['message'])
                . ($motif ? " — motif : {$motif}" : ''),
            'security',
            ['tenant_id' => $tenant->id, 'ecarts' => $ecarts, 'cumuls' => $resultat['cumuls'] ?? []]
        );
    }
}
