<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        $modules = $this->applyModuleDependencies($modules);

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
                $pdo = $this->connectToTenantDatabase($tenant);

                $stmt = $pdo->query("SELECT id, name, email, phone, role, is_active FROM users ORDER BY name");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $tenantUsers = collect($rows)->map(function ($row) {
                    return (object) $row;
                });

                // users_count est un compteur dénormalisé (incrémenté à la création
                // d'un manager) — il peut dériver si un utilisateur est supprimé
                // directement dans la base du tenant. On le resynchronise ici,
                // le seul endroit où on a déjà une lecture live de cette table.
                if ($tenant->users_count !== $tenantUsers->count()) {
                    $tenant->update(['users_count' => $tenantUsers->count()]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Could not fetch tenant users for {$tenant->name}: " . $e->getMessage());
                session()->now('error', "Impossible de se connecter à la base de données de l'établissement : " . $e->getMessage());
            }
        }

        return view('admin.tenants.show', compact('tenant', 'tenantUsers', 'section'));
    }

    /**
     * Connexion PDO à la base applicative propre à un établissement.
     *
     * Se connecte via le nom du container DB du tenant sur le réseau Docker
     * partagé 'pms' (ex: meka-erp-{slug}-db) — PAS via l'alias générique
     * "db", qui résout vers la base de l'admin lui-même (son propre service
     * "db"), pas celle du tenant. Repli sur le port hôte mappé (127.0.0.1)
     * si l'admin ne tourne pas sur le réseau Docker (dev local hors conteneur).
     */
    private function connectToTenantDatabase(Tenant $tenant): \PDO
    {
        $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $tenant->db_name);
        $dbUser = $tenant->db_username ?? 'pms';
        $dbPass = $tenant->db_password ?? 'secret';
        $dbContainer = $tenant->docker_db_container ?: ('meka-erp-' . $tenant->slug . '-db');

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 3,
        ];

        try {
            return new \PDO("pgsql:host={$dbContainer};port=5432;dbname={$safeDbName}", $dbUser, $dbPass, $options);
        } catch (\PDOException $e) {
            return new \PDO("pgsql:host=127.0.0.1;port={$tenant->db_port};dbname={$safeDbName}", $dbUser, $dbPass, $options);
        }
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

    /**
     * Active/désactive des modules métier pour un établissement déjà
     * provisionné : régénère le docker-compose (même image) et recrée le
     * container applicatif pour que TENANT_MODULES soit repris en compte.
     */
    public function updateModules(Request $request, Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $validated = $request->validate([
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(['restaurant', 'shop', 'housekeeping', 'discussions', 'analytics', 'api', 'website'])],
        ]);

        $modules = $this->applyModuleDependencies($validated['modules'] ?? []);
        $tenant->update(['modules' => $modules]);

        if (empty($tenant->docker_image_tag)) {
            return back()->with('error', "Cet établissement n'est pas encore provisionné — les modules seront appliqués au premier provisioning.");
        }

        $logs = [];
        $log  = function (string $step, string $message, string $level = 'info') use (&$logs) {
            $logs[] = "[{$level}] {$message}";
        };

        try {
            $provisioner->applyModules($tenant, $log);

            AuditLog::record(
                $user->id,
                'update_modules',
                "Modules mis à jour pour l'établissement {$tenant->name} : " . implode(', ', $tenant->modules ?: ['aucun']),
                'tech_admin',
                ['logs' => $logs]
            );

            return back()->with('success', 'Modules appliqués avec succès.');
        } catch (\RuntimeException $e) {
            return back()->with('error', "Échec de l'application des modules : " . $e->getMessage());
        }
    }

    /**
     * Met à jour le site vitrine vers la dernière image publiée sur le
     * registre (tag "latest"). Synchrone, comme updateModules — l'image du
     * site est légère, pas besoin du flux SSE utilisé pour l'application.
     */
    public function updateTenantWebsite(Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $logs = [];
        $log  = function (string $step, string $message, string $level = 'info') use (&$logs) {
            $logs[] = "[{$level}] {$message}";
        };

        try {
            $updated = $provisioner->updateWeb($tenant, $log);

            if (!$updated) {
                return back()->with('success', 'Le site est déjà à la dernière version.');
            }

            AuditLog::record(
                $user->id,
                'update_tenant_website',
                "Site vitrine mis à jour pour l'établissement {$tenant->name}",
                'tech_admin',
                ['logs' => $logs]
            );

            return back()->with('success', 'Site vitrine mis à jour avec succès.');
        } catch (\RuntimeException $e) {
            return back()->with('error', "Échec de la mise à jour du site : " . $e->getMessage());
        }
    }

    /**
     * Règle métier (PLAN_REALISATION_ARCHITECTURE.md, Phase 3) : le site
     * vitrine consomme l'API applicative de l'établissement, donc l'activer
     * force "api" — mais l'inverse n'est pas vrai, activer l'API seule ne
     * provisionne pas de site. Appliquée ici plutôt que côté vue seule
     * (Alpine) pour ne pas dépendre du JS client.
     */
    private function applyModuleDependencies(array $modules): array
    {
        if (in_array('website', $modules, true) && !in_array('api', $modules, true)) {
            $modules[] = 'api';
        }

        return $modules;
    }

    /**
     * Enregistre le contenu marketing du site vitrine (module website).
     * Pur stockage côté pms (colonne site_content) — pas d'action Docker,
     * contrairement aux modules : le site le récupère à l'exécution via
     * publicSiteContent(), pas besoin de recréer de container.
     */
    public function updateSiteContent(Request $request, Tenant $tenant)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        // Règles de validation dérivées du schéma (une entrée par champ)
        $rules = [
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            // Onglet Identité du site : édite la fiche du tenant elle-même
            'identity_logo' => ['nullable', 'image', 'max:2048'],
            'identity_remove_logo' => ['nullable'],
            'identity_phone' => ['nullable', 'string', 'max:30'],
            'identity_email' => ['nullable', 'email', 'max:255'],
            'identity_address' => ['nullable', 'string', 'max:255'],
        ];
        foreach (\App\Support\SiteContentSchema::pages() as $pageKey => $page) {
            foreach ($page['sections'] as $sectionKey => $section) {
                foreach ($section['fields'] as $fieldKey => $field) {
                    $input = "pages.{$pageKey}.{$sectionKey}.{$fieldKey}";
                    $file  = "pages_files.{$pageKey}.{$sectionKey}.{$fieldKey}";
                    switch ($field['type']) {
                        case 'text':
                            $rules[$input] = ['nullable', 'string', 'max:255'];
                            break;
                        case 'textarea':
                        case 'items':
                            $rules[$input] = ['nullable', 'string', 'max:8000'];
                            break;
                        case 'image':
                            $rules[$file] = ['nullable', 'image', 'max:4096'];
                            break;
                        case 'images':
                            $rules[$file] = ['nullable', 'array'];
                            $rules["{$file}.*"] = ['image', 'max:4096'];
                            break;
                    }
                }
            }
        }
        $request->validate($rules);

        $content  = $tenant->site_content ?? [];
        $existing = \App\Support\SiteContentSchema::hydrate($content);
        $storage  = \Illuminate\Support\Facades\Storage::disk('public');
        $pages    = [];

        foreach (\App\Support\SiteContentSchema::pages() as $pageKey => $page) {
            foreach ($page['sections'] as $sectionKey => $section) {
                $in  = (array) $request->input("pages.{$pageKey}.{$sectionKey}", []);
                $cur = $existing[$pageKey][$sectionKey];
                $out = ['enabled' => !empty($in['enabled'])];

                foreach ($section['fields'] as $fieldKey => $field) {
                    switch ($field['type']) {
                        case 'text':
                        case 'textarea':
                            $value = trim((string) ($in[$fieldKey] ?? ''));
                            $out[$fieldKey] = $value === '' ? null : $value;
                            break;

                        case 'items':
                            $out[$fieldKey] = \App\Support\SiteContentSchema::parseItems($in[$fieldKey] ?? null, $field['keys']);
                            break;

                        case 'image':
                            $path = $cur[$fieldKey];
                            if (!empty($in["remove_{$fieldKey}"]) && $path) {
                                $storage->delete($path);
                                $path = null;
                            }
                            if ($file = $request->file("pages_files.{$pageKey}.{$sectionKey}.{$fieldKey}")) {
                                if ($path) { $storage->delete($path); }
                                $path = $file->store('site', 'public');
                            }
                            $out[$fieldKey] = $path;
                            break;

                        case 'images':
                            $paths  = $cur[$fieldKey];
                            $remove = array_filter((array) ($in["remove_{$fieldKey}"] ?? []));
                            if ($remove) {
                                foreach ($remove as $p) { $storage->delete($p); }
                                $paths = array_values(array_diff($paths, $remove));
                            }
                            foreach ((array) $request->file("pages_files.{$pageKey}.{$sectionKey}.{$fieldKey}", []) as $file) {
                                $paths[] = $file->store('site', 'public');
                            }
                            $out[$fieldKey] = $paths;
                            break;
                    }
                }

                $pages[$pageKey][$sectionKey] = $out;
            }
        }

        $content['pages'] = $pages;
        $content['seo'] = [
            'title' => $request->input('seo_title') ?: null,
            'description' => $request->input('seo_description') ?: null,
        ];

        // Miroir de l'ancien format à plat : la version du site actuellement
        // déployée chez les établissements lit encore hero/about/contact/
        // gallery au premier niveau (voir publicSiteContent).
        $content['hero'] = [
            'title' => $pages['home']['hero']['title'],
            'subtitle' => $pages['home']['hero']['subtitle'],
            'cta_label' => $pages['home']['hero']['cta_label'],
            'background_image' => $pages['home']['hero']['background_image'],
        ];
        $content['about'] = [
            'title' => $pages['home']['philosophy']['title'],
            'body' => $pages['home']['philosophy']['body'],
        ];
        $content['contact'] = [
            'intro' => $pages['contact']['info']['intro'],
            'hours' => $pages['contact']['info']['hours'],
        ];
        $content['gallery'] = $pages['home']['instagram']['images'];

        // Identité du site : coordonnées et logo vivent sur la fiche du
        // tenant (mêmes champs que la création / l'onglet Informations),
        // pas dans site_content — une seule source de vérité.
        $settings = $tenant->settings ?? [];

        if ($request->boolean('identity_remove_logo') && !empty($settings['logo'])) {
            $storage->delete($settings['logo']);
            $settings['logo'] = null;
        }

        if ($file = $request->file('identity_logo')) {
            if (!empty($settings['logo'])) {
                $storage->delete($settings['logo']);
            }
            $settings['logo'] = $file->store('logos', 'public');
        }

        $tenant->update([
            'site_content' => $content,
            'settings'     => $settings,
            'phone'        => $request->input('identity_phone') ?: null,
            'email'        => $request->input('identity_email') ?: null,
            'address'      => $request->input('identity_address') ?: null,
        ]);

        AuditLog::record($user->id, 'update_site_content', "Contenu du site mis à jour pour l'établissement {$tenant->name}", 'tech_admin');

        return back()->with('success', 'Contenu du site enregistré.');
    }

    /**
     * API publique (lecture seule, pas d'auth) : contenu marketing d'un
     * établissement, consommée par template_site. Résout les images en URLs
     * absolues — le storage vit dans pms, pas dans le container du site.
     */
    public function publicSiteContent(Tenant $tenant)
    {
        $content = $tenant->site_content ?? [];
        $absolute = fn (?string $path) => $path
            ? rtrim(config('app.url'), '/') . '/storage/' . ltrim($path, '/')
            : null;

        // Structure par pages/sections (nouveau format à onglets), images
        // résolues en URLs absolues via le schéma.
        $pages = \App\Support\SiteContentSchema::hydrate($content);
        foreach (\App\Support\SiteContentSchema::pages() as $pageKey => $page) {
            foreach ($page['sections'] as $sectionKey => $section) {
                foreach ($section['fields'] as $fieldKey => $field) {
                    if ($field['type'] === 'image') {
                        $pages[$pageKey][$sectionKey][$fieldKey] = $absolute($pages[$pageKey][$sectionKey][$fieldKey]);
                    } elseif ($field['type'] === 'images') {
                        $pages[$pageKey][$sectionKey][$fieldKey] = array_map($absolute, $pages[$pageKey][$sectionKey][$fieldKey]);
                    }
                }
            }
        }

        return response()->json([
            'name' => $tenant->name,
            // Logo importé à la création de l'établissement (stocké côté pms,
            // servi au site en URL absolue comme toutes les images du CMS).
            'logo' => $absolute($tenant->settings['logo'] ?? null),
            // Clés à plat conservées pour la version du site déjà déployée
            'hero' => [
                'title' => $content['hero']['title'] ?? null,
                'subtitle' => $content['hero']['subtitle'] ?? null,
                'cta_label' => $content['hero']['cta_label'] ?? null,
                'background_image' => $absolute($content['hero']['background_image'] ?? null),
            ],
            'about' => [
                'title' => $content['about']['title'] ?? null,
                'body' => $content['about']['body'] ?? null,
            ],
            'contact' => [
                'intro' => $content['contact']['intro'] ?? null,
                'hours' => $content['contact']['hours'] ?? null,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
            ],
            'gallery' => collect($content['gallery'] ?? [])->map($absolute)->all(),
            'seo' => [
                'title' => $content['seo']['title'] ?? $tenant->name,
                'description' => $content['seo']['description'] ?? null,
            ],
            'pages' => $pages,
        ]);
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
                if (mb_strlen($message) > 3000) {
                    $message = mb_substr($message, 0, 3000) . '…';
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

            } catch (\Throwable $e) {
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

    /**
     * Liste les versions (tags) disponibles sur le registre GHCR, pour le
     * sélecteur de mise à jour côté UI TECH.
     */
    public function availableVersions(Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        return response()->json([
            'current' => $tenant->docker_image_tag,
            'tags'    => $provisioner->listAvailableVersions(),
        ]);
    }

    /**
     * SSE endpoint : met à jour un établissement déjà provisionné vers le
     * tag choisi (?tag=...), en temps réel.
     */
    public function updateTenantVersionStream(Tenant $tenant, Request $request, \App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $tag = $request->query('tag');
        if (!$tag) { abort(422, 'Le paramètre "tag" est requis.'); }

        set_time_limit(600);
        ini_set('max_execution_time', '600');

        return response()->stream(function () use ($tenant, $tag, $provisioner) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            ob_implicit_flush(true);

            $send = function (string $step, string $message, string $level = 'info') {
                if (mb_strlen($message) > 3000) {
                    $message = mb_substr($message, 0, 3000) . '…';
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
                $provisioner->update($tenant, $tag, $send);

                AuditLog::record(
                    Auth::id(),
                    'update_tenant_version',
                    "Mise à jour de l'établissement {$tenant->name} vers le tag « {$tag} »",
                    'tech_admin'
                );

                $send('finished', 'Mise à jour terminée avec succès.', 'success');

            } catch (\Throwable $e) {
                $tenant->update(['docker_status' => 'error']);
                $send('error', $e->getMessage(), 'error');

                AuditLog::record(
                    Auth::id(),
                    'update_tenant_version_error',
                    "Échec de la mise à jour de {$tenant->name} vers « {$tag} » : " . $e->getMessage(),
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

        } catch (\Throwable $e) {
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
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        // Relance (ex: après un échec) : repasse en 'creating' pour que la
        // page affiche à nouveau le widget SSE, comme à la création initiale.
        $tenant->update(['docker_status' => 'creating']);

        return redirect()
            ->route('tech.establishments.show', $tenant)
            ->with('start_provisioning', true);
    }

    public function healthCheckTenant(Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $health = $provisioner->health($tenant);

        // Sync docker_status en base
        $newStatus = $health['healthy'] ? 'running' : ($health['app_status'] === 'absent' ? 'stopped' : $health['app_status']);
        $tenant->update(['docker_status' => $newStatus, 'last_health_check' => now()]);

        return response()->json($health);
    }

    /**
     * Support — Mode lecture : diagnostic en lecture seule d'un établissement
     * (état Docker, dernière activité, comptes, réservations), sans jamais
     * écrire dans sa base. Consommé en AJAX quand un établissement est
     * sélectionné dans l'onglet Support.
     */
    public function supportDiagnostic(Tenant $tenant, \App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $health = $provisioner->health($tenant);

        $diagnostic = [
            'name'          => $tenant->name,
            'slug'          => $tenant->slug,
            'is_active'     => (bool) $tenant->is_active,
            'provisioned'   => (bool) $tenant->provisioned_at,
            'provisioned_at' => $tenant->provisioned_at?->format('d/m/Y H:i'),
            'docker_status' => $tenant->docker_status,
            'app_status'    => $health['app_status'],
            'db_status'     => $health['db_status'],
            'web_status'    => $health['web_status'] ?? null,
            'has_website'   => in_array('website', $tenant->modules ?? [], true),
            'modules'       => $tenant->modules ?? [],
            'app_url'       => $tenant->app_port ? 'http://localhost:' . $tenant->app_port : null,
            'image_tag'     => $tenant->docker_image_tag,
            'web_image_tag' => $tenant->web_image_tag,
            'last_health'   => $tenant->last_health_check?->diffForHumans(),
            'reachable'     => false,
            'users'         => null,
            'active_users'  => null,
            'bookings_total' => null,
            'bookings_today' => null,
            'last_booking'  => null,
        ];

        if ($tenant->provisioned_at && $health['db_status'] === 'running') {
            try {
                $pdo = $this->connectToTenantDatabase($tenant);
                $diagnostic['reachable']      = true;
                $diagnostic['users']          = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $diagnostic['active_users']   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = true")->fetchColumn();
                $diagnostic['bookings_total'] = (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
                $diagnostic['bookings_today'] = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE created_at::date = CURRENT_DATE")->fetchColumn();
                $last = $pdo->query("SELECT created_at FROM bookings ORDER BY created_at DESC LIMIT 1")->fetchColumn();
                $diagnostic['last_booking'] = $last ? \Carbon\Carbon::parse($last)->diffForHumans() : null;
            } catch (\Exception $e) {
                $diagnostic['reachable'] = false;
            }
        }

        AuditLog::record($user->id, 'support_read', "Consultation du diagnostic support de l'établissement {$tenant->name}", 'support', ['slug' => $tenant->slug]);

        return response()->json($diagnostic);
    }

    /**
     * Support — Mode assistance : ouvre une session d'assistance auditée sur
     * un établissement. Exige une justification (motif). Génère un jeton
     * signé (HMAC) que le endpoint /assistance/enter de l'application tenant
     * vérifie pour ouvrir une session support temporaire. Rien n'est écrit
     * dans la base du tenant depuis pms — c'est le tenant qui consomme le
     * jeton.
     */
    public function assistanceOpen(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $validated = $request->validate([
            'tenant_id' => ['required', 'exists:tenants,id'],
            'reason'    => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);

        if (empty(config('assistance.secret'))) {
            return back()->with('error', "Le secret d'assistance (ASSISTANCE_SECRET) n'est pas configuré côté pms.");
        }

        if (!$tenant->provisioned_at) {
            return back()->with('error', "Cet établissement n'est pas encore provisionné.");
        }

        $ttl     = (int) config('assistance.ttl_minutes', 30);
        $expires = now()->addMinutes($ttl);

        $session = \App\Models\AssistanceSession::create([
            'tenant_id'  => $tenant->id,
            'user_id'    => $user->id,
            'reason'     => $validated['reason'],
            'token'      => \Illuminate\Support\Str::random(48),
            'status'     => 'active',
            'expires_at' => $expires,
        ]);

        AuditLog::record(
            $user->id,
            'assistance_open',
            "Ouverture d'une session d'assistance sur {$tenant->name} — motif : {$validated['reason']}",
            'support',
            ['slug' => $tenant->slug, 'session' => $session->token, 'expires_at' => $expires->toIso8601String()]
        );

        return back()->with('success', "Session d'assistance ouverte sur « {$tenant->name} » (valable {$ttl} min).");
    }

    /**
     * Jeton signé + URL d'entrée dans l'application tenant pour une session.
     * Payload signé HMAC-SHA256 avec le secret partagé : le tenant vérifie
     * la signature et l'expiration avant d'ouvrir la session support.
     */
    private function assistanceEntryUrl(\App\Models\AssistanceSession $session): string
    {
        $tenant = $session->tenant;

        $payload = [
            'slug'    => $tenant->slug,
            'session' => $session->token,
            'admin'   => $session->user?->name ?? 'Support',
            'exp'     => $session->expires_at->timestamp,
        ];

        $encoded   = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $encoded, config('assistance.secret'));

        $base = $tenant->app_port ? 'http://localhost:' . $tenant->app_port : '';

        return $base . '/assistance/enter?token=' . $encoded . '.' . $signature;
    }

    /**
     * Support — liste des sessions d'assistance (onglet Mode assistance).
     * Passe paresseusement en 'expired' les sessions actives échues, et
     * expose l'URL d'entrée pour les sessions encore vivantes.
     */
    public function assistanceList()
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        \App\Models\AssistanceSession::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $sessions = \App\Models\AssistanceSession::with(['tenant', 'user'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (\App\Models\AssistanceSession $s) {
                $live = $s->isLive();
                return [
                    'id'         => $s->id,
                    'tenant'     => $s->tenant?->name ?? '—',
                    'slug'       => $s->tenant?->slug,
                    'admin'      => $s->user?->name ?? '—',
                    'reason'     => $s->reason,
                    'status'     => $s->status,
                    'live'       => $live,
                    'expires_at' => $s->expires_at?->format('d/m/Y H:i'),
                    'expires_in' => $live ? $s->expires_at->diffForHumans() : null,
                    'opened_at'  => $s->created_at?->format('d/m/Y H:i'),
                    'entry_url'  => $live ? $this->assistanceEntryUrl($s) : null,
                ];
            });

        return response()->json(['sessions' => $sessions]);
    }

    /**
     * Support — clôture (révocation) manuelle d'une session d'assistance.
     */
    public function assistanceRevoke(\App\Models\AssistanceSession $session)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        if ($session->status === 'active') {
            $session->update(['status' => 'revoked', 'closed_at' => now()]);

            AuditLog::record(
                $user->id,
                'assistance_revoke',
                "Clôture de la session d'assistance sur {$session->tenant?->name}",
                'support',
                ['slug' => $session->tenant?->slug, 'session' => $session->token]
            );
        }

        return back()->with('success', "Session d'assistance clôturée.");
    }

    /**
     * Support — Logs applicatifs : journal d'activité lu dans la table
     * audit_logs de l'application de chaque établissement (meka_template) —
     * connexions et actions internes, avec l'utilisateur concerné. Sans
     * filtre d'établissement, agrège tous les tenants joignables. Lecture
     * seule, timeout court par base, une base injoignable est signalée.
     */
    public function supportAppLogs(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $tenantsQuery = Tenant::query()->whereNotNull('provisioned_at')->orderBy('name');
        if ($request->filled('slug')) {
            $tenantsQuery->where('slug', $request->string('slug'));
        }
        $tenants = $tenantsQuery->get();

        $perTenant  = max(20, (int) ceil(120 / max(1, $tenants->count())));
        $logs       = [];
        $unreachable = [];

        foreach ($tenants as $tenant) {
            try {
                $pdo = $this->connectToTenantDatabase($tenant);
                $stmt = $pdo->prepare(
                    "SELECT a.id, a.event_type, a.action, a.module, a.ip_address, a.created_at, u.name AS user_name, u.role AS user_role
                     FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id
                     ORDER BY a.created_at DESC LIMIT :lim"
                );
                $stmt->bindValue(':lim', $perTenant, \PDO::PARAM_INT);
                $stmt->execute();

                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                    $logs[] = [
                        'tenant'      => $tenant->name,
                        'slug'        => $tenant->slug,
                        'event_type'  => $r['event_type'],
                        'action'      => $r['action'],
                        'module'      => $r['module'],
                        'ip'          => $r['ip_address'],
                        'user'        => $r['user_name'] ?? 'Système / anonyme',
                        'role'        => $r['user_role'],
                        'at'          => \Carbon\Carbon::parse($r['created_at'])->format('d/m/Y H:i:s'),
                        'ts'          => \Carbon\Carbon::parse($r['created_at'])->timestamp,
                        'ago'         => \Carbon\Carbon::parse($r['created_at'])->diffForHumans(),
                    ];
                }
            } catch (\Exception $e) {
                $unreachable[] = $tenant->name;
            }
        }

        // Tri global décroissant puis plafond commun
        usort($logs, fn ($a, $b) => $b['ts'] <=> $a['ts']);
        $logs = array_slice($logs, 0, 200);

        return response()->json([
            'logs'        => $logs,
            'unreachable' => $unreachable,
            'count'       => count($logs),
            'generated_at' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Support — Historique des interventions : journal des actions sensibles
     * effectuées par les admins TECH (provisioning, arrêts, mises à jour,
     * modules, suppressions, consultations support). Filtrable par
     * établissement. Lecture seule du journal d'audit de pms.
     */
    public function supportInterventions(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        // Actions considérées comme des interventions (vs simple navigation)
        $interventionEvents = [
            'create_tenant', 'delete_tenant', 'update_tenant',
            'start_tenant', 'stop_tenant', 'restart_tenant',
            'update_modules', 'update_tenant_version', 'update_tenant_version_error',
            'update_tenant_website', 'update_site_content', 'create_manager',
            'support_read',
        ];

        $query = AuditLog::with('user')
            ->whereIn('event_type', $interventionEvents)
            ->latest();

        if ($request->filled('slug')) {
            $slug = $request->string('slug');
            $query->where(function ($q) use ($slug) {
                $q->where('description', 'like', '%' . $slug . '%')
                  ->orWhere('payload', 'like', '%"' . $slug . '"%');
            });
        }

        $logs = $query->limit(80)->get()->map(fn (AuditLog $log) => [
            'id'          => $log->id,
            'event_type'  => $log->event_type,
            'description' => $log->description,
            'actor'       => $log->user?->name ?? 'Système',
            'at'          => $log->created_at?->format('d/m/Y H:i'),
            'ago'         => $log->created_at?->diffForHumans(),
        ]);

        return response()->json(['interventions' => $logs]);
    }

    /**
     * Répartition des rôles opérationnels par établissement (onglet Rôles) :
     * lit la table users de chaque tenant provisionné, timeout court, une
     * base injoignable est signalée plutôt que bloquante. Consommé en AJAX.
     */
    public function rolesDistribution()
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $catalogKeys = array_keys(\App\Support\TenantRoles::catalog());
        $rows        = [];
        $totals      = array_fill_keys($catalogKeys, 0);
        $unknownTotal = 0;

        foreach (Tenant::orderBy('name')->get() as $tenant) {
            $row = [
                'id'        => $tenant->id,
                'name'      => $tenant->name,
                'slug'      => $tenant->slug,
                'url'       => route('tech.establishments.show', ['tenant' => $tenant, 'section' => 'users']),
                'reachable' => false,
                'roles'     => [],
                'unknown'   => 0,
                'total'     => 0,
            ];

            if ($tenant->provisioned_at) {
                try {
                    $pdo = $this->connectToTenantDatabase($tenant);
                    $stmt = $pdo->query("SELECT role, COUNT(*) AS n FROM users GROUP BY role");

                    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                        $role  = (string) $r['role'];
                        $count = (int) $r['n'];
                        $row['total'] += $count;

                        if (in_array($role, $catalogKeys, true)) {
                            $row['roles'][$role] = $count;
                            $totals[$role] += $count;
                        } else {
                            $row['unknown'] += $count;
                            $unknownTotal += $count;
                        }
                    }

                    $row['reachable'] = true;
                } catch (\Exception $e) {
                    // Base injoignable : la ligne reste marquée non joignable
                }
            }

            $rows[] = $row;
        }

        return response()->json([
            'establishments' => $rows,
            'totals'         => $totals,
            'unknown_total'  => $unknownTotal,
            'generated_at'   => now()->format('H:i:s'),
        ]);
    }

    /**
     * Statistiques consolidées de l'onglet Supervision (TECH) : état Docker
     * de chaque établissement, utilisateurs, réservations du jour (lues dans
     * la base de chaque tenant joignable) et alertes globales. Consommé en
     * AJAX par le dashboard — les inspections Docker sont locales et rapides,
     * les connexions PDO ont un timeout court (3s) et échouent en alerte
     * plutôt qu'en erreur.
     */
    public function supervisionStats(\App\Services\TenantProvisioningService $provisioner)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $establishments = [];
        $alerts         = [];
        $usersTotal     = 0;
        $bookingsToday  = 0;
        $arrivalsToday  = 0;
        $runningCount   = 0;

        foreach (Tenant::orderBy('name')->get() as $tenant) {
            $health = $provisioner->health($tenant);

            $row = [
                'id'            => $tenant->id,
                'name'          => $tenant->name,
                'slug'          => $tenant->slug,
                'is_active'     => (bool) $tenant->is_active,
                'app_status'    => $health['app_status'],
                'db_status'     => $health['db_status'],
                'web_status'    => $health['web_status'] ?? null,
                'has_website'   => in_array('website', $tenant->modules ?? [], true),
                'app_port'      => $tenant->app_port,
                'web_port'      => $tenant->web_port ?? ($tenant->app_port ? $tenant->app_port + 1000 : null),
                'users_count'   => (int) ($tenant->users_count ?? 0),
                'bookings_today' => null,
                'arrivals_today' => null,
                'url'           => route('tech.establishments.show', $tenant),
                'provisioned'   => (bool) $tenant->provisioned_at,
            ];

            $usersTotal += $row['users_count'];

            if ($health['healthy'] && $tenant->is_active) {
                $runningCount++;
            }

            // ── Alertes ──────────────────────────────────────────────────
            if (!$tenant->provisioned_at) {
                $alerts[] = ['level' => 'warning', 'tenant' => $tenant->name, 'message' => "Jamais provisionné — aucun container en place."];
            } else {
                if ($tenant->docker_status === 'error') {
                    $alerts[] = ['level' => 'critical', 'tenant' => $tenant->name, 'message' => "Dernier provisioning ou mise à jour en erreur."];
                }
                if ($tenant->is_active && $health['app_status'] !== 'running') {
                    $alerts[] = ['level' => 'critical', 'tenant' => $tenant->name, 'message' => "Container applicatif « {$health['app_status']} » alors que l'établissement est actif."];
                }
                if ($tenant->is_active && $health['db_status'] !== 'running') {
                    $alerts[] = ['level' => 'critical', 'tenant' => $tenant->name, 'message' => "Base de données « {$health['db_status']} »."];
                }
                if ($row['has_website'] && $tenant->docker_web_container && ($health['web_status'] ?? 'absent') !== 'running') {
                    $alerts[] = ['level' => 'warning', 'tenant' => $tenant->name, 'message' => "Site vitrine « {$health['web_status']} » — le site public est indisponible."];
                }
                if ($row['has_website'] && !$tenant->docker_web_container) {
                    $alerts[] = ['level' => 'warning', 'tenant' => $tenant->name, 'message' => "Module Site web actif mais aucun container web provisionné — réappliquer les modules."];
                }
            }

            // ── Réservations du jour (base du tenant, si joignable) ──────
            if ($tenant->provisioned_at && $health['db_status'] === 'running') {
                try {
                    $pdo = $this->connectToTenantDatabase($tenant);

                    $row['bookings_today'] = (int) $pdo->query(
                        "SELECT COUNT(*) FROM bookings WHERE created_at::date = CURRENT_DATE"
                    )->fetchColumn();

                    $row['arrivals_today'] = (int) $pdo->query(
                        "SELECT COUNT(*) FROM bookings WHERE check_in = CURRENT_DATE AND status <> 'cancelled'"
                    )->fetchColumn();

                    $bookingsToday += $row['bookings_today'];
                    $arrivalsToday += $row['arrivals_today'];
                } catch (\Exception $e) {
                    $alerts[] = ['level' => 'warning', 'tenant' => $tenant->name, 'message' => "Base injoignable pour la lecture des réservations."];
                }
            }

            $establishments[] = $row;
        }

        return response()->json([
            'counts' => [
                'establishments_total'   => count($establishments),
                'establishments_running' => $runningCount,
                'users_total'            => $usersTotal,
                'bookings_today'         => $bookingsToday,
                'arrivals_today'         => $arrivalsToday,
                'alerts'                 => count($alerts),
            ],
            'alerts'         => $alerts,
            'establishments' => $establishments,
            'generated_at'   => now()->format('H:i:s'),
        ]);
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

    /**
     * Export CSV du rapport de supervision : une ligne par établissement
     * (identité, contacts, modules, infra) avec compteurs utilisateurs /
     * réservations lus en direct dans la base de chaque tenant joignable.
     * Format Excel-fr : UTF-8 BOM + délimiteur « ; » (cf. guide de l'onglet).
     */
    public function exportSupervision()
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $tenants = Tenant::with('owner')->orderBy('name')->get();

        $columns = [
            'Établissement', 'Slug', 'Actif', 'Statut Docker', 'Pays', 'Ville',
            'Devise', 'Adresse', 'Téléphone', 'Email', 'Propriétaire',
            'Email propriétaire', 'Modules actifs', 'Port applicatif',
            'Utilisateurs', 'Réservations', 'Provisionné le',
        ];

        $rows = [];
        foreach ($tenants as $tenant) {
            $users = $bookings = null;

            if ($tenant->provisioned_at) {
                try {
                    $pdo      = $this->connectToTenantDatabase($tenant);
                    $users    = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                    $bookings = (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
                } catch (\Exception $e) {
                    // Base injoignable : compteurs N/D, la ligne reste exportée
                }
            }

            $rows[] = [
                $tenant->name,
                $tenant->slug,
                $tenant->is_active ? 'Oui' : 'Non',
                $tenant->docker_status,
                $tenant->settings['country'] ?? '',
                $tenant->settings['city'] ?? '',
                $tenant->currency,
                $tenant->address,
                $tenant->phone,
                $tenant->email,
                $tenant->owner?->name,
                $tenant->owner?->email,
                implode(', ', $tenant->modules ?? []),
                $tenant->app_port,
                $users ?? 'N/D',
                $bookings ?? 'N/D',
                $tenant->provisioned_at?->format('d/m/Y H:i') ?? 'Jamais',
            ];
        }

        AuditLog::record($user->id, 'export_supervision',
            "Export CSV du rapport de supervision ({$tenants->count()} établissement(s))", 'tech_admin');

        $filename = 'supervision_etablissements_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 : accents corrects sous Excel
            fputcsv($out, $columns, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── Import/Export > Backups des établissements ────────────────────────────

    /**
     * Liste des backups (onglet Import/Export), avec la planification de
     * chaque établissement. Consommé en AJAX.
     */
    public function backupsIndex(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $backupsQuery = \App\Models\TenantBackup::with('tenant')->latest();
        if ($request->filled('slug')) {
            $backupsQuery->whereHas('tenant', fn ($q) => $q->where('slug', $request->string('slug')));
        }

        $backups = $backupsQuery->limit(100)->get()->map(fn (\App\Models\TenantBackup $b) => [
            'id'       => $b->id,
            'tenant'   => $b->tenant?->name ?? '—',
            'slug'     => $b->tenant?->slug,
            'filename' => $b->filename,
            'size'     => $b->humanSize(),
            'status'   => $b->status,
            'trigger'  => $b->trigger,
            'error'    => $b->error,
            'at'       => $b->created_at?->format('d/m/Y H:i'),
            'ago'      => $b->created_at?->diffForHumans(),
        ]);

        $schedules = \App\Models\BackupSchedule::with('tenant')->get()->keyBy('tenant_id')->map(fn ($s) => [
            'enabled'     => $s->enabled,
            'frequency'   => $s->frequency,
            'label'       => $s->frequencyLabel(),
            'retention'   => $s->retention,
            'next_run_at' => $s->next_run_at?->format('d/m/Y H:i'),
            'last_run_at' => $s->last_run_at?->format('d/m/Y H:i'),
        ]);

        return response()->json([
            'backups'   => $backups,
            'schedules' => $schedules,
        ]);
    }

    /**
     * Sauvegarde manuelle immédiate d'un établissement.
     */
    public function backupCreate(Tenant $tenant, \App\Services\TenantBackupService $service)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $backup = $service->backup($tenant, 'manual');

        AuditLog::record($user->id, 'backup_create',
            "Sauvegarde manuelle de l'établissement {$tenant->name} ({$backup->status})", 'tech_admin',
            ['slug' => $tenant->slug, 'filename' => $backup->filename, 'status' => $backup->status]);

        if ($backup->status === 'failed') {
            return back()->with('error', "Échec de la sauvegarde de « {$tenant->name} » : {$backup->error}");
        }

        return back()->with('success', "Sauvegarde de « {$tenant->name} » créée ({$backup->humanSize()}) — fichier : {$service->displayPath($backup)}");
    }

    /**
     * Sauvegarde manuelle de tous les établissements provisionnés.
     */
    public function backupAll(\App\Services\TenantBackupService $service)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $tenants = Tenant::whereNotNull('provisioned_at')->get();
        $ok = 0; $failed = 0;

        foreach ($tenants as $tenant) {
            $backup = $service->backup($tenant, 'manual');
            $backup->status === 'completed' ? $ok++ : $failed++;
        }

        AuditLog::record($user->id, 'backup_create',
            "Sauvegarde manuelle globale : {$ok} réussie(s), {$failed} échec(s)", 'tech_admin');

        $msg = "{$ok} sauvegarde(s) créée(s)" . ($failed ? ", {$failed} échec(s)" : '') . " — dossier : {$service->displayDir()}";
        return $failed ? back()->with('warning', $msg) : back()->with('success', $msg);
    }

    /**
     * Restaure une sauvegarde existante dans la base de son établissement.
     * Les données actuelles sont intégralement remplacées par le contenu du
     * dump (--clean --if-exists). Volontairement limité au tenant d'origine
     * de la sauvegarde — pas de restauration croisée entre établissements.
     */
    public function backupRestore(\App\Models\TenantBackup $backup, \App\Services\TenantBackupService $service)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $tenant = $backup->tenant;
        if (!$tenant) {
            return back()->with('error', 'Établissement de cette sauvegarde introuvable.');
        }

        if ($backup->status !== 'completed') {
            return back()->with('error', 'Cette sauvegarde a échoué — rien à restaurer.');
        }

        [$ok, $error] = $service->restore($tenant, $backup->path);

        AuditLog::record($user->id, 'backup_restore',
            "Restauration de la sauvegarde {$backup->filename} dans l'établissement {$tenant->name} (" . ($ok ? 'réussie' : 'échec') . ")",
            'tech_admin', ['slug' => $tenant->slug, 'filename' => $backup->filename]);

        if (!$ok) {
            return back()->with('error', "Échec de la restauration sur « {$tenant->name} » : {$error}");
        }

        return back()->with('success', "Sauvegarde « {$backup->filename} » restaurée dans « {$tenant->name} » — les données de l'application ont été remplacées par celles du {$backup->created_at->format('d/m/Y à H:i')}.");
    }

    /**
     * Importe un fichier de sauvegarde (.sql.gz ou .sql) depuis le PC et le
     * restaure dans l'établissement sélectionné. Le fichier est d'abord
     * archivé dans le dossier backups (trigger « imported ») puis rejoué.
     */
    public function backupImport(Request $request, Tenant $tenant, \App\Services\TenantBackupService $service)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $request->validate([
            'backup_file' => ['required', 'file', 'max:512000'], // 500 Mo
        ]);

        $file = $request->file('backup_file');
        $original = strtolower($file->getClientOriginalName());

        if (!str_ends_with($original, '.sql.gz') && !str_ends_with($original, '.sql')) {
            return back()->with('error', 'Format non pris en charge — importez un fichier .sql.gz (généré par cet outil) ou .sql.');
        }

        $ext      = str_ends_with($original, '.sql.gz') ? 'sql.gz' : 'sql';
        $filename = sprintf('%s_imported_%s.%s', $tenant->slug, now()->format('Ymd_His'), $ext);
        $relPath  = \App\Services\TenantBackupService::DIR . '/' . $filename;

        \Illuminate\Support\Facades\Storage::disk('local')->putFileAs(
            \App\Services\TenantBackupService::DIR, $file, $filename
        );

        $backup = \App\Models\TenantBackup::create([
            'tenant_id'  => $tenant->id,
            'filename'   => $filename,
            'path'       => $relPath,
            'size_bytes' => (int) \Illuminate\Support\Facades\Storage::disk('local')->size($relPath),
            'status'     => 'completed',
            'trigger'    => 'imported',
        ]);

        [$ok, $error] = $service->restore($tenant, $relPath);

        AuditLog::record($user->id, 'backup_import',
            "Import du fichier {$original} et restauration dans {$tenant->name} (" . ($ok ? 'réussie' : 'échec') . ")",
            'tech_admin', ['slug' => $tenant->slug, 'filename' => $filename]);

        if (!$ok) {
            return back()->with('error', "Fichier importé et archivé, mais échec de la restauration sur « {$tenant->name} » : {$error}");
        }

        return back()->with('success', "Fichier « {$original} » importé et restauré dans « {$tenant->name} » — archive conservée : {$service->displayPath($backup)}");
    }

    public function backupDownload(\App\Models\TenantBackup $backup)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        if ($backup->status !== 'completed' || !\Illuminate\Support\Facades\Storage::disk('local')->exists($backup->path)) {
            abort(404, 'Fichier de sauvegarde introuvable.');
        }

        AuditLog::record($user->id, 'backup_download',
            "Téléchargement du backup {$backup->filename}", 'tech_admin', ['slug' => $backup->tenant?->slug]);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($backup->path, $backup->filename);
    }

    public function backupDelete(\App\Models\TenantBackup $backup, \App\Services\TenantBackupService $service)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $name = $backup->filename;
        $service->delete($backup);

        AuditLog::record($user->id, 'backup_delete', "Suppression du backup {$name}", 'tech_admin');

        return back()->with('success', "Sauvegarde « {$name} » supprimée.");
    }

    /**
     * Crée ou met à jour la planification cron de backup d'un établissement.
     */
    public function backupSchedule(Request $request, Tenant $tenant)
    {
        $user = Auth::user();
        if (!$user || !$user->isTechAdmin()) { abort(403); }

        $validated = $request->validate([
            'enabled'      => ['nullable', 'boolean'],
            'frequency'    => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'hour'         => ['required', 'integer', 'between:0,23'],
            'minute'       => ['required', 'integer', 'between:0,59'],
            'day_of_week'  => ['nullable', 'integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'integer', 'between:1,28'],
            'retention'    => ['required', 'integer', 'between:1,365'],
        ]);

        $schedule = \App\Models\BackupSchedule::firstOrNew(['tenant_id' => $tenant->id]);
        $schedule->fill([
            'enabled'      => $request->boolean('enabled'),
            'frequency'    => $validated['frequency'],
            'hour'         => $validated['hour'],
            'minute'       => $validated['minute'],
            'day_of_week'  => $validated['frequency'] === 'weekly' ? ($validated['day_of_week'] ?? 1) : null,
            'day_of_month' => $validated['frequency'] === 'monthly' ? ($validated['day_of_month'] ?? 1) : null,
            'retention'    => $validated['retention'],
        ]);
        $schedule->next_run_at = $schedule->enabled ? $schedule->computeNextRun() : null;
        $schedule->save();

        AuditLog::record($user->id, 'backup_schedule',
            "Planification de backup " . ($schedule->enabled ? 'activée' : 'désactivée') . " pour {$tenant->name} ({$schedule->frequencyLabel()})",
            'tech_admin', ['slug' => $tenant->slug]);

        return back()->with('success', "Planification de sauvegarde enregistrée pour « {$tenant->name} ».");
    }

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

    /**
     * Vue d'ensemble 360° (AJAX) : données financières consolidées des
     * établissements du propriétaire, via l'API reporting de chaque tenant.
     */
    public function businessOverviewData(Request $request, \App\Services\BusinessReportingClient $client)
    {
        $user = Auth::user();
        if (!$user || !$user->isOwner()) { abort(403); }

        $period = in_array($request->query('period'), ['today', 'week', 'month', 'year'], true)
            ? $request->query('period') : 'month';

        $tenants = $user->tenants()->whereNotNull('provisioned_at')->orderBy('name')->get();

        return response()->json($client->overview($tenants, $period));
    }

    /**
     * Séries de revenus consolidées (graphe d'évolution) : additionne les
     * séries de chaque établissement point par point.
     */
    public function businessRevenueData(Request $request, \App\Services\BusinessReportingClient $client)
    {
        $user = Auth::user();
        if (!$user || !$user->isOwner()) { abort(403); }

        $period = in_array($request->query('period'), ['today', 'week', 'month', 'year'], true)
            ? $request->query('period') : 'month';

        $tenants = $user->tenants()->whereNotNull('provisioned_at')->orderBy('name')->get();

        $labels = [];
        $hotel = [];
        $restaurant = [];
        $shop = [];

        foreach ($tenants as $tenant) {
            $data = $client->fetch($tenant, 'revenue', ['period' => $period]);
            $series = $data['series'] ?? null;
            if (!$series) {
                continue;
            }

            // Le premier établissement joignable fixe les labels ; les suivants
            // sont additionnés point par point (mêmes bornes de période).
            if (empty($labels)) {
                $labels = $series['labels'] ?? [];
                $hotel = $series['hotel'] ?? [];
                $restaurant = $series['restaurant'] ?? [];
                $shop = $series['shop'] ?? [];
            } else {
                foreach (($series['hotel'] ?? []) as $i => $v) { $hotel[$i] = ($hotel[$i] ?? 0) + $v; }
                foreach (($series['restaurant'] ?? []) as $i => $v) { $restaurant[$i] = ($restaurant[$i] ?? 0) + $v; }
                foreach (($series['shop'] ?? []) as $i => $v) { $shop[$i] = ($shop[$i] ?? 0) + $v; }
            }
        }

        return response()->json([
            'period' => $period,
            'labels' => $labels,
            'hotel' => array_values($hotel),
            'restaurant' => array_values($restaurant),
            'shop' => array_values($shop),
        ]);
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
            'password' => ['nullable', 'string', 'min:4'],
        ]);

        // Si aucun mot de passe fourni (flux TECH), on en génère un et on le
        // renvoie dans la réponse pour que TECH puisse le transmettre au manager.
        $generatedPassword = null;
        if (empty($validated['password'])) {
            $generatedPassword = Str::random(10);
            $validated['password'] = $generatedPassword;
        }

        try {
            $pdo = $this->connectToTenantDatabase($tenant);

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
                'message' => "Manager créé avec succès.",
                'generated_password' => $generatedPassword,
            ], 201);

        } catch (\PDOException $e) {
            \Illuminate\Support\Facades\Log::error("Failed to connect or insert manager in tenant database {$tenant->db_name}: " . $e->getMessage());
            return response()->json([
                'message' => "Impossible de se connecter à la base de données de l'établissement: " . $e->getMessage()
            ], 500);
        }
    }
}