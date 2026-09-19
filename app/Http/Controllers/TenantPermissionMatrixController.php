<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\PermissionMatrixClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Édition de la matrice des droits d'un établissement.
 *
 * L'écran montre le gabarit livré avec l'application — quels rôles détiennent
 * quel droit par défaut — et permet d'en retirer ou d'en ajouter. Seuls les
 * écarts sont transmis : le gabarit suit le code de l'établissement et se
 * périmerait s'il était recopié ici.
 */
class TenantPermissionMatrixController extends Controller
{
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

    public function show(Tenant $tenant): View
    {
        $this->authorizeTenant($tenant);

        $matrice = $this->client->fetch($tenant);

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

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $valide = $request->validate([
            'ecarts'              => ['present', 'array'],
            'ecarts.*.role'       => ['required', 'string', 'max:64'],
            'ecarts.*.permission' => ['required', 'string', 'max:128'],
            'ecarts.*.effect'     => ['required', 'in:allow,deny'],
            'ecarts.*.reason'     => ['nullable', 'string', 'max:255'],
        ]);

        $resultat = $this->client->push($tenant, $valide['ecarts']);

        AuditLog::record(
            Auth::id(),
            'permission_matrix',
            "Matrice des droits de {$tenant->name} : " . count($valide['ecarts']) . ' écart(s) — '
                . ($resultat['ok'] ? 'appliquée' : 'échec : ' . $resultat['message']),
            'security',
            ['tenant_id' => $tenant->id, 'ecarts' => $valide['ecarts']]
        );

        return redirect()
            ->route(
                Auth::user()->isTechAdmin() ? 'tech.establishments.permissions' : 'business.establishments.permissions',
                ['tenant' => $tenant]
            )
            ->with($resultat['ok'] ? 'success' : 'error', $resultat['message']);
    }
}
