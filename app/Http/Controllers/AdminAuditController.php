<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuditController extends Controller
{
    /**
     * Dashboard TECH (Supervision globale)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) {
            abort(403, "Accès interdit - Rôle TECH_ADMIN requis.");
        }

        $activeTab = $request->input('tab', 'dashboard');
        $subTab = $request->input('sub', 'logs');

        // Logs d'audit (SQLite)
        $logsQuery = AuditLog::with(['user'])->latest();
        
        if ($request->filled('user_id')) {
            $logsQuery->where('user_id', $request->user_id);
        }
        if ($request->filled('event_type')) {
            $logsQuery->where('event_type', $request->event_type);
        }
        if ($request->filled('module')) {
            $logsQuery->where('module', $request->module);
        }

        $logs = $logsQuery->paginate(20, ['*'], 'logs_page')->withQueryString();

        // Utilisateurs (SQLite)
        $usersQuery = User::query();
        if ($request->filled('user_search')) {
            $search = trim((string) $request->user_search);
            $usersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%");
            });
        }
        $users = $usersQuery->orderBy('name')->paginate(10, ['*'], 'users_page')->withQueryString();

        // Établissements (SQLite)
        $tenants = Tenant::orderBy('name')->get();
        $allUsers = User::orderBy('name')->get();

        // Statistiques d'administration
        $auditStats = [
            'total_logs' => AuditLog::count(),
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'inactive_users' => User::where('is_active', false)->count(),
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
        ];

        return view('admin.dashboard', compact(
            'activeTab',
            'subTab',
            'logs',
            'users',
            'tenants',
            'allUsers',
            'auditStats'
        ));
    }

    public function indexTenants()
    {
        return redirect()->route('tech.dashboard', ['tab' => 'tenants']);
    }

    public function createTenant()
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }
        $owners = User::where('role', User::ROLE_OWNER)->orderBy('name')->get();
        return view('admin.tenants.create', compact('owners'));
    }

    public function storeTenant(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'owner_id' => ['required', 'exists:users,id'],
            'db_name' => ['required', 'string', 'max:255'],
        ]);

        $tenant = Tenant::create(array_merge($validated, [
            'docker_status' => 'stopped',
            'is_active' => true,
        ]));

        AuditLog::record(
            Auth::id(),
            'create_tenant',
            "Création de l'établissement {$tenant->name} (slug: {$tenant->slug})",
            'tech_admin'
        );

        return redirect()->route('tech.dashboard', ['tab' => 'tenants'])
            ->with('success', "L'établissement {$tenant->name} a été créé.");
    }

    public function showTenant(Tenant $tenant)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }
        return view('admin.tenants.show', compact('tenant'));
    }

    public function updateTenant(Request $request, Tenant $tenant)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $tenant->update($validated);

        return back()->with('success', 'Établissement mis à jour.');
    }

    public function provisionTenant(Tenant $tenant) { return back(); }
    public function startTenant(Tenant $tenant) { return back(); }
    public function stopTenant(Tenant $tenant) { return back(); }
    public function restartTenant(Tenant $tenant) { return back(); }
    public function healthCheckTenant(Tenant $tenant) { return response()->json(['status' => 'unknown']); }

    public function toggleUserActive(User $user)
    {
        $admin = Auth::user();
        if (!$admin || !$admin->isTechAdmin()) { abort(403); }
        if ($user->id === $admin->id) { return back()->with('error', 'Auto-désactivation impossible.'); }
        $user->update(['is_active' => !$user->is_active]);
        return back()->with('success', 'Statut utilisateur modifié.');
    }

    public function forcePasswordReset(User $user)
    {
        $admin = Auth::user();
        if (!$admin || !$admin->isTechAdmin()) { abort(403); }
        $tempPassword = Str::random(10);
        $user->update(['password' => Hash::make($tempPassword)]);
        return back()->with('success', "Mot de passe réinitialisé en : {$tempPassword}");
    }

    public function exportSupervision() { return response()->json(['error' => 'Non implémenté']); }
    public function exportBackupTenant(?Tenant $tenant = null) { return response()->json(['error' => 'Non implémenté']); }

    // Espace BUSINESS
    public function businessDashboard(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isOwner()) { abort(403); }

        $segment = $request->segment(2); // business/{segment}
        $activeTab = $request->input('tab', $segment ?: 'dashboard');

        if (!in_array($activeTab, ['dashboard', 'establishments', 'analytics', 'employees', 'revenue'])) {
            $activeTab = 'dashboard';
        }

        // Charger uniquement les établissements appartenant à ce propriétaire
        $tenants = $user->tenants()->orderBy('name')->get();

        return view('admin.dashboard', compact('activeTab', 'tenants'));
    }
}
