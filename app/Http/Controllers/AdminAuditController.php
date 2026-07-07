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
        
        // 1. Initial Validation
        $rules = [
            'owner_type' => ['required', 'in:new,existing'],
            
            // If new owner
            'owner_name' => ['required_if:owner_type,new', 'nullable', 'string', 'max:255'],
            'owner_email' => ['required_if:owner_type,new', 'nullable', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['nullable', 'string', 'max:30'],
            'owner_company' => ['nullable', 'string', 'max:255'],
            'owner_nationality' => ['required_if:owner_type,new', 'nullable', 'string', 'max:100'],
            'owner_password' => ['required_if:owner_type,new', 'nullable', 'string', 'min:4'],

            // If existing owner
            'owner_id' => ['required_if:owner_type,existing', 'nullable', 'exists:users,id'],

            // Step 2: Technical Configuration
            'db_name' => ['required', 'string', 'max:255', 'unique:tenants,db_name'],
            'app_port' => ['required', 'integer', 'unique:tenants,app_port'],
            'db_username' => ['nullable', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'db_port' => ['nullable', 'string', 'max:10'],
            'docker_app_container' => ['nullable', 'string', 'max:255'],
            'docker_db_container' => ['nullable', 'string', 'max:255'],

            // Source du template applicatif (toujours GitHub)


            // Step 3: Establishment Info
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:tenants,slug'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:3'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            
            // Theme Colors
            'theme' => ['nullable', 'array'],
            'theme.primary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.secondary' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.accent' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.surface_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.text_on_light' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.text_on_dark' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            // Modules
            'modules' => ['nullable', 'array'],
        ];

        $request->validate($rules);

        // 2. Resolve Owner ID
        $ownerId = null;
        if ($request->owner_type === 'new') {
            $newOwner = User::create([
                'name' => $request->owner_name,
                'email' => $request->owner_email,
                'phone' => $request->owner_phone,
                'company_name' => $request->owner_company,
                'nationality' => $request->owner_nationality,
                'password' => Hash::make($request->owner_password),
                'role' => User::ROLE_OWNER,
                'is_active' => true,
            ]);
            $ownerId = $newOwner->id;
        } else {
            $ownerId = $request->owner_id;
        }

        // 3. Process Logo Upload
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('logos', 'public');
        }

        // 4. Form Settings JSON and Modules
        $settings = [
            'country' => $request->country ?? 'Cameroun',
            'city' => $request->city ?? 'Douala',
            'logo' => $logoPath,
            'theme' => $request->theme ?? [
                'primary' => '#391F0E',
                'secondary' => '#CCAB87',
                'accent' => '#EED4A3',
                'dark' => '#0F0201',
                'surface_dark' => '#2C1810',
                'text_on_light' => '#391F0E',
                'text_on_dark' => '#CCAB87',
            ],
        ];

        $modules = [];
        if ($request->has('modules') && is_array($request->modules)) {
            $modules = array_keys($request->modules);
        }

        // 5. Créer le Tenant en base (statut 'creating' — le provisioning Docker suit via SSE)
        $tenant = Tenant::create([
            'name'                 => $request->name,
            'slug'                 => $request->slug,
            'address'              => $request->address,
            'phone'                => $request->phone,
            'email'                => $request->email,
            'currency'             => $request->currency,
            'owner_id'             => $ownerId,
            'db_name'              => $request->db_name,
            'db_username'          => $request->db_username ?? 'pms',
            'db_password'          => $request->db_password ?? 'secret',
            'app_port'             => $request->app_port,
            'db_port'              => $request->db_port ?? 5434,
            'docker_app_container' => 'meka-erp-' . $request->slug . '-app',
            'docker_db_container'  => 'meka-erp-' . $request->slug . '-db',
            'docker_status'        => 'creating',
            'is_active'            => true,
            'settings'             => $settings,
            'modules'              => $modules,
        ]);

        AuditLog::record(
            Auth::id(),
            'create_tenant',
            "Création de l'établissement {$tenant->name} (slug: {$tenant->slug})",
            'tech_admin'
        );

        // Rediriger vers la page de l'établissement — le provisioning démarre via SSE
        return redirect()
            ->route('tech.establishments.show', $tenant)
            ->with('start_provisioning', true)
            ->with('success', "Établissement \u00ab {$tenant->name} \u00bb créé. Le provisioning Docker va démarrer.");
    }

    public function showTenant(Tenant $tenant)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $section = request('section', 'overview');
        $tenantUsers = collect();

        if ($section === 'users') {
            try {
                $dbHost = 'db';
                $dbPort = '5432';
                if (gethostbyname('db') === 'db') {
                    $dbHost = '127.0.0.1';
                    $dbPort = '5433';
                }
                
                $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);
                $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$safeDbName}";
                
                // Add short connection timeout (2 seconds) to avoid gateway timeouts
                $pdo = new \PDO($dsn, $tenant->db_username ?? 'pms', $tenant->db_password ?? 'secret', [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_TIMEOUT => 2,
                ]);
                
                $stmt = $pdo->query("SELECT id, name, email, phone, role, is_active FROM users ORDER BY name");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                $tenantUsers = collect($rows)->map(function ($row) {
                    return (object) $row;
                });
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Could not fetch tenant users for {$tenant->name}: " . $e->getMessage());
                session()->now('error', "Impossible de se connecter à la base de données de l'établissement : " . $e->getMessage());
            }
        }

        return view('admin.tenants.show', compact('tenant', 'tenantUsers', 'section'));
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

    public function destroyTenant(Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $logs = [];
        $log  = function (string $step, string $message, string $level = 'info') use (&$logs) {
            $logs[] = "[{$level}] {$message}";
        };

        try {
            // 1. Nettoyer l'infrastructure Docker
            $provisioner->delete($tenant, $log);

            $tenantName = $tenant->name;
            $tenantSlug = $tenant->slug;

            // 2. Supprimer de la base globale SQLite
            $tenant->delete();

            // 3. Loguer dans l'audit log
            AuditLog::record(
                $user->id,
                'delete_tenant',
                "Suppression définitive de l'établissement {$tenantName} (slug: {$tenantSlug}) et de toutes ses ressources",
                'tech_admin',
                ['logs' => $logs]
            );

            return redirect()
                ->route('tech.dashboard', ['tab' => 'tenants'])
                ->with('success', "L'établissement « {$tenantName} » a été supprimé définitivement avec toutes ses ressources Docker.");

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Failed to delete tenant {$tenant->name}: " . $e->getMessage());
            return back()->with('error', "Une erreur est survenue lors de la suppression : " . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Actions Docker — déléguées à TenantProvisioningService
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * SSE endpoint : provisionne un tenant en temps réel.
     * Le client JS se connecte à cette route après la création du tenant.
     */
    public function provisionTenantStream(Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        // Augmenter le temps d'exécution max pour le provisioning (10 min)
        set_time_limit(600);
        ini_set('max_execution_time', '600');

        return response()->stream(function () use ($tenant, $provisioner) {
            // Désactiver le buffering de sortie pour SSE
            if (ob_get_level()) {
                ob_end_clean();
            }
            ob_implicit_flush(true);

            $send = function (string $step, string $message, string $level = 'info') {
                // Tronquer les messages très longs pour éviter le débordement du cadre de logs
                if (mb_strlen($message) > 500) {
                    $message = mb_substr($message, 0, 500) . '…';
                }
                $payload = json_encode([
                    'step'    => $step,
                    'message' => $message,
                    'level'   => $level,
                    'time'    => now()->format('H:i:s'),
                ]);
                echo "data: {$payload}\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            };

            try {
                $provisioner->provision($tenant, $send);

                AuditLog::record(
                    Auth::id(),
                    'provision_tenant',
                    "Provisioning Docker terminé pour l'établissement {$tenant->name}",
                    'tech_admin'
                );

                $send('finished', 'Provisioning terminé avec succès.', 'success');

            } catch (\RuntimeException $e) {
                $tenant->update(['docker_status' => 'error']);
                $send('error', $e->getMessage(), 'error');

                AuditLog::record(
                    Auth::id(),
                    'provision_error',
                    "Échec du provisioning pour {$tenant->name} : " . $e->getMessage(),
                    'tech_admin'
                );
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function startTenant(Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $logs = [];
        $log  = function (string $step, string $message, string $level = 'info') use (&$logs) {
            $logs[] = "[{$level}] {$message}";
        };

        try {
            $provisioner->start($tenant, $log);
            $tenant->update(['docker_status' => 'running']);

            AuditLog::record(Auth::id(), 'start_tenant',
                "Démarrage du container pour l'établissement {$tenant->name}", 'tech_admin');

            return back()->with('success', "Container de « {$tenant->name} » démarré.");

        } catch (\RuntimeException $e) {
            $tenant->update(['docker_status' => 'error']);
            return back()->with('error', "Erreur au démarrage : " . $e->getMessage());
        }
    }

    public function stopTenant(Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $log = fn() => null;
        $provisioner->stop($tenant, $log);
        $tenant->update(['docker_status' => 'stopped']);

        AuditLog::record(Auth::id(), 'stop_tenant',
            "Arrêt du container pour l'établissement {$tenant->name}", 'tech_admin');

        return back()->with('success', "Container de « {$tenant->name} » arrêté.");
    }

    public function restartTenant(Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $log = fn() => null;
        $provisioner->restart($tenant, $log);
        $tenant->update(['docker_status' => 'running']);

        AuditLog::record(Auth::id(), 'restart_tenant',
            "Redémarrage du container pour l'établissement {$tenant->name}", 'tech_admin');

        return back()->with('success', "Container de « {$tenant->name} » redémarré.");
    }

    public function provisionTenant(Tenant $tenant)
    {
        // Redirige vers le stream SSE (ancienne méthode conservée pour compatibilité)
        return redirect()->route('tech.establishments.provision.stream', $tenant);
    }

    public function healthCheckTenant(Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $health = $provisioner->health($tenant);

        // Sync docker_status en base
        $newStatus = $health['healthy'] ? 'running' : ($health['app_status'] === 'absent' ? 'stopped' : $health['app_status']);
        $tenant->update(['docker_status' => $newStatus, 'last_health_check' => now()]);

        return response()->json($health);
    }



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

    public function createTenantManager(Request $request, Tenant $tenant)
    {
        $user = Auth::user();
        if (!$user) { abort(401); }
        if (!$user->isTechAdmin() && ($tenant->owner_id !== $user->id)) {
            abort(403, "Vous n'avez pas l'autorisation de gérer cet établissement.");
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:4'],
        ]);

        try {
            $dbHost = 'db';
            $dbPort = '5432';
            if (gethostbyname('db') === 'db') {
                $dbHost = '127.0.0.1';
                $dbPort = '5433';
            }
            
            $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);
            $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$safeDbName}";
            $pdo = new \PDO($dsn, $tenant->db_username ?? 'pms', $tenant->db_password ?? 'secret', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ]);
            
            $stmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ?");
            $stmt->execute([$validated['email']]);
            if ($stmt->fetch()) {
                return response()->json([
                    'message' => "Un utilisateur avec cet email existe déjà dans cet établissement."
                ], 422);
            }

            $hashedPassword = Hash::make($validated['password']);

            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, phone, password, role, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $validated['name'],
                $validated['email'],
                $validated['phone'] ?? null,
                $hashedPassword,
                'manager',
                true
            ]);

            $tenant->increment('users_count');

            AuditLog::record(
                Auth::id(),
                'create_manager',
                "Création du manager {$validated['name']} ({$validated['email']}) pour l'établissement {$tenant->name}",
                $user->role
            );

            return response()->json([
                'success' => true,
                'message' => "Manager créé avec succès."
            ], 201);

        } catch (\PDOException $e) {
            \Illuminate\Support\Facades\Log::error("Failed to connect or insert manager in tenant database {$tenant->db_name}: " . $e->getMessage());
            return response()->json([
                'message' => "Impossible de se connecter à la base de données de l'établissement: " . $e->getMessage()
            ], 500);
        }
    }
}