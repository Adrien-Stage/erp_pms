<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Services\TenantDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestion des départements et de leur organisation depuis l'ERP.
 *
 * Écrit directement dans la base de l'établissement via TenantDatabase :
 * tables `departments` et `department_module`.
 */
class TenantDepartmentController extends Controller
{
    public function __construct(private TenantDatabase $tenantDb) {}

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

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'slug' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'accent' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer'],
            'modules' => ['nullable', 'array'],
            'levels' => ['nullable', 'array'],
        ]);

        $modules = [];
        foreach ($request->input('modules', []) as $modKey) {
            $level = $request->input("levels.{$modKey}", 'write');
            $modules[$modKey] = in_array($level, ['write', 'read'], true) ? $level : 'write';
        }

        try {
            $deptId = $this->tenantDb->createDepartment($tenant, $validated, $modules);

            AuditLog::record(Auth::id(), 'tenant_department_create',
                "Département {$validated['name']} créé dans {$tenant->name}", 'tenant_departments',
                ['department_id' => $deptId, 'modules' => array_keys($modules)]);

            return $this->redirectBack($tenant)->with('success', "Le département « {$validated['name']} » a été créé avec succès.");
        } catch (\Throwable $e) {
            return $this->redirectBack($tenant)->with('error', 'Erreur lors de la création du département : '.$e->getMessage());
        }
    }

    public function update(Request $request, Tenant $tenant, int $department): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20'],
            'slug' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:50'],
            'accent' => ['nullable', 'string', 'max:30'],
            'sort_order' => ['nullable', 'integer'],
            'modules' => ['nullable', 'array'],
            'levels' => ['nullable', 'array'],
        ]);

        $modules = [];
        foreach ($request->input('modules', []) as $modKey) {
            $level = $request->input("levels.{$modKey}", 'write');
            $modules[$modKey] = in_array($level, ['write', 'read'], true) ? $level : 'write';
        }

        try {
            $this->tenantDb->updateDepartment($tenant, $department, $validated, $modules);

            AuditLog::record(Auth::id(), 'tenant_department_update',
                "Département {$validated['name']} (#{$department}) modifié dans {$tenant->name}", 'tenant_departments',
                ['department_id' => $department, 'modules' => array_keys($modules)]);

            return $this->redirectBack($tenant)->with('success', "Le département « {$validated['name']} » a été mis à jour.");
        } catch (\Throwable $e) {
            return $this->redirectBack($tenant)->with('error', 'Erreur lors de la mise à jour du département : '.$e->getMessage());
        }
    }

    public function destroy(Tenant $tenant, int $department): RedirectResponse
    {
        $this->authorizeTenant($tenant);

        try {
            $this->tenantDb->deleteDepartment($tenant, $department);

            AuditLog::record(Auth::id(), 'tenant_department_delete',
                "Département #{$department} supprimé de {$tenant->name}", 'tenant_departments',
                ['department_id' => $department]);

            return $this->redirectBack($tenant)->with('success', 'Le département a été supprimé.');
        } catch (\Throwable $e) {
            return $this->redirectBack($tenant)->with('error', 'Erreur lors de la suppression du département : '.$e->getMessage());
        }
    }
}
