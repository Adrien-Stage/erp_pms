<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantDirectoryClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Départements d'un établissement, tenus depuis l'ERP.
 *
 * Ils passent par l'API d'orchestration de l'établissement : plus aucune
 * écriture directe dans sa base, que l'application ne pouvait ni valider ni
 * tracer.
 */
class TenantDepartmentController extends Controller
{
    public function __construct(private TenantDirectoryClient $annuaire) {}

    private function authorizeTenant(Tenant $tenant): void
    {
        $user = Auth::user();

        abort_unless($user, 401);
        abort_unless($user->isTechAdmin() || $tenant->owner_id === $user->id, 403,
            "Vous n'avez pas l'autorisation de gérer cet établissement.");
    }

    private function redirectBack(Tenant $tenant): RedirectResponse
    {
        return redirect()->route(
            Auth::user()->isTechAdmin() ? 'tech.establishments.show' : 'business.establishments.show',
            ['tenant' => $tenant, 'section' => 'departments']
        );
    }

    private function auteur(): string
    {
        return Auth::user()->name . ' <' . Auth::user()->email . '>';
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $validated = $request->validate($this->regles());

        $resultat = $this->annuaire->creerDepartement($tenant, $this->donnees($validated));

        AuditLog::record(Auth::id(), 'tenant_department_create',
            "Département {$validated['name']} créé dans {$tenant->name}"
                . ($resultat['ok'] ? '' : ' — échec : ' . $resultat['message']),
            'tenant_departments', ['department_id' => $resultat['id'] ?? null]);

        return $this->redirectBack($tenant)->with(
            $resultat['ok'] ? 'success' : 'error',
            $resultat['ok']
                ? "Le département « {$validated['name']} » a été créé avec succès."
                : 'Le département n\'a pas été créé : ' . $resultat['message']
        );
    }

    public function update(Request $request, Tenant $tenant, int $department): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $validated = $request->validate($this->regles());

        $resultat = $this->annuaire->modifierDepartement($tenant, $department, $this->donnees($validated));

        AuditLog::record(Auth::id(), 'tenant_department_update',
            "Département {$validated['name']} (#{$department}) modifié dans {$tenant->name}"
                . ($resultat['ok'] ? '' : ' — échec : ' . $resultat['message']),
            'tenant_departments', ['department_id' => $department]);

        return $this->redirectBack($tenant)->with(
            $resultat['ok'] ? 'success' : 'error',
            $resultat['ok']
                ? "Le département « {$validated['name']} » a été mis à jour."
                : 'Le département n\'a pas été mis à jour : ' . $resultat['message']
        );
    }

    public function destroy(Tenant $tenant, int $department): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $resultat = $this->annuaire->supprimerDepartement($tenant, $department, $this->auteur());

        AuditLog::record(Auth::id(), 'tenant_department_delete',
            "Département #{$department} supprimé de {$tenant->name}"
                . ($resultat['ok'] ? '' : ' — échec : ' . $resultat['message']),
            'tenant_departments', ['department_id' => $department]);

        return $this->redirectBack($tenant)->with(
            $resultat['ok'] ? 'success' : 'error',
            $resultat['ok'] ? 'Le département a été supprimé.' : 'Le département n\'a pas été supprimé : ' . $resultat['message']
        );
    }

    /** @return array<string, list<string>> */
    private function regles(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'code'        => ['nullable', 'string', 'max:20'],
            'slug'        => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'icon'        => ['nullable', 'string', 'max:50'],
            'accent'      => ['nullable', 'string', 'max:30'],
            'sort_order'  => ['nullable', 'integer'],
        ];
    }

    /**
     * Un département range le personnel : il ne porte plus de modules, les
     * droits viennent des rôles.
     */
    private function donnees(array $validated): array
    {
        return array_filter([
            'name'        => $validated['name'],
            'code'        => $validated['code'] ?? null,
            'slug'        => $validated['slug'] ?? null,
            'description' => $validated['description'] ?? null,
            'icon'        => $validated['icon'] ?? null,
            'accent'      => $validated['accent'] ?? null,
            'sort_order'  => $validated['sort_order'] ?? null,
            'auteur'      => $this->auteur(),
        ], static fn ($v) => $v !== null);
    }
}
