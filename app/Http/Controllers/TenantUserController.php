<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\GrcAccountSync;
use App\Services\TenantDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Le personnel d'un établissement, vu depuis l'ERP.
 *
 * La console consulte : les comptes se créent et se modifient dans
 * l'application, par l'administrateur de l'établissement — le service
 * informatique —, que la console crée elle-même (Droits & rôles). Elle
 * n'écrit plus dans la base de l'établissement : une écriture directe
 * contournait l'application, qui ne pouvait ni la valider ni la tracer.
 *
 * Reste ici l'accès au portail GRC d'un contrôleur de gestion : le GRC tient
 * sa propre base, que seule la console alimente.
 */
class TenantUserController extends Controller
{
    public function __construct(private TenantDatabase $tenantDb) {}

    private function authorizeTenant(Tenant $tenant): void
    {
        $user = Auth::user();

        abort_unless($user, 401);
        abort_unless($user->isTechAdmin() || $tenant->owner_id === $user->id, 403,
            "Vous n'avez pas l'autorisation de gérer cet établissement.");
    }

    private function liste(Tenant $tenant): RedirectResponse
    {
        return redirect()->route(
            Auth::user()->isTechAdmin() ? 'tech.establishments.show' : 'business.establishments.show',
            ['tenant' => $tenant, 'section' => 'users']
        );
    }

    /**
     * Fiche d'un employé : identité, rôles, département, état du compte et
     * restrictions de service. En lecture seule.
     */
    public function show(Tenant $tenant, int $user)
    {
        $this->authorizeTenant($tenant);

        try {
            $employe = $this->tenantDb->userDetail($tenant, $user);
        } catch (\Throwable $e) {
            Log::warning('Lecture base tenant échouée : ' . $e->getMessage());

            return $this->liste($tenant)->with('error', "Impossible de joindre la base de l'établissement. "
                . 'Vérifiez que ses conteneurs sont démarrés, puis réessayez.');
        }

        if (! $employe) {
            return $this->liste($tenant)->with('error', 'Cet employé est introuvable dans cet établissement.');
        }

        $estControleur = $employe->role === 'controller'
            || collect($employe->roles ?? [])->contains(fn ($r) => ($r['slug'] ?? null) === 'controller');

        return view('admin.tenants.user', compact('tenant', 'employe', 'estControleur'));
    }

    /**
     * Donne ou renouvelle l'accès au portail GRC d'un contrôleur de gestion.
     *
     * Le GRC hache lui-même le mot de passe : il ne peut être reporté qu'au
     * moment où l'opérateur en saisit un. Rien n'est écrit dans la base de
     * l'établissement.
     */
    public function grcAccess(Request $request, Tenant $tenant, int $user): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $valide = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        if (! $tenant->hasGrc()) {
            return back()->with('error', "Le module GRC n'est pas activé pour cet établissement.");
        }

        try {
            $employe = $this->tenantDb->userDetail($tenant, $user);
        } catch (\Throwable $e) {
            return back()->with('error', "Impossible de joindre la base de l'établissement.");
        }

        $estControleur = $employe && ($employe->role === 'controller'
            || collect($employe->roles ?? [])->contains(fn ($r) => ($r['slug'] ?? null) === 'controller'));

        if (! $estControleur) {
            return back()->with('error', "Seul un contrôleur de gestion reçoit un accès au portail GRC.");
        }

        $sync = app(GrcAccountSync::class);
        $reporte = $sync->push($tenant, [
            'email'     => $employe->email,
            'password'  => $valide['password'],
            'full_name' => $employe->name,
            'phone'     => $employe->phone ?? null,
        ]);

        AuditLog::record(Auth::id(), 'tenant_grc_access',
            "Accès au portail GRC de {$employe->name} ({$employe->email}) pour {$tenant->name} — "
                . ($reporte ? 'reporté' : 'échec'),
            'tenant_users', ['user_id' => $employe->id]);

        return back()->with($reporte ? 'success' : 'error', $reporte
            ? "Accès au portail GRC de {$employe->name} enregistré."
            : "Le portail GRC n'a pas pu être joint : l'accès n'a pas été enregistré. Vérifiez que le module est démarré, puis réessayez.");
    }
}
