@php
    /**
     * Fiche d'un employé d'un établissement.
     *
     * Attend : $tenant, $employe, $rolesAssignables, $dernierManager
     *
     * Cet employé vit dans la base de son établissement : ce qui est enregistré
     * ici est immédiatement effectif dans wetchah_app, il n'y a pas de copie à
     * resynchroniser.
     */
    $rolesParModule = collect($rolesAssignables)->groupBy(fn ($r) => $r->module ?: 'autre');

    // Niveau actuel par identifiant de rôle, pour pré-remplir le formulaire.
    $niveauActuel = collect($employe->roles)->mapWithKeys(fn ($r) => [(int) $r['id'] => $r['level']])->all();
    $rolesActuels = array_keys($niveauActuel);

    $initiales = collect(explode(' ', trim($employe->name)))
        ->filter()->take(2)->map(fn ($m) => mb_strtoupper(mb_substr($m, 0, 1)))->implode('');

    $dateOuNull = function (?string $valeur) {
        if (empty($valeur)) {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::parse($valeur);
        } catch (\Throwable) {
            return null;
        }
    };
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <x-favicon />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $employe->name }} — {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased font-body">

<header class="sticky top-0 z-30 w-full bg-[#0f172a] border-b border-slate-800 text-white shadow-md">
    <div class="mx-auto px-5 lg:px-8 flex items-center justify-between h-14">
        <div class="flex items-center gap-4">
            <a href="{{ route(auth()->user()?->isTechAdmin() ? 'tech.dashboard' : 'business.dashboard') }}"
               class="shrink-0" title="Wetchah ERP">
                <x-brand variant="mark" class="h-7" />
            </a>
            <div class="h-5 w-px bg-slate-700"></div>
            <a href="{{ route('tech.establishments.show', ['tenant' => $tenant, 'section' => 'users']) }}"
               class="flex items-center gap-2 text-slate-400 hover:text-white transition text-xs font-semibold">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
                Utilisateurs
            </a>
            <div class="h-5 w-px bg-slate-700"></div>
            <div>
                <h1 class="text-sm font-bold text-white leading-none">{{ $tenant->name }}</h1>
                <p class="text-[10px] text-slate-400 font-mono">{{ $tenant->slug }}</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-bold border
            {{ $employe->is_active ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30' }}">
            <span class="h-1.5 w-1.5 rounded-full {{ $employe->is_active ? 'bg-green-400' : 'bg-red-400' }}"></span>
            {{ $employe->is_active ? 'Compte actif' : 'Compte désactivé' }}
        </span>
    </div>
</header>

<main class="mx-auto max-w-6xl px-5 lg:px-8 py-8">

    @if(session('success'))
        <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs font-semibold text-red-800">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800">
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Identité --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-lg font-extrabold text-white">
            {{ $initiales ?: '?' }}
        </div>
        <div class="min-w-0 flex-1">
            <h2 class="text-2xl font-extrabold tracking-tight text-slate-800">{{ $employe->name }}</h2>
            <p class="text-xs text-slate-500 font-mono">{{ $employe->email }}</p>
        </div>
        <div class="flex flex-wrap gap-1.5">
            @if($employe->role)
                <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-2.5 py-1 text-[10px] font-bold text-indigo-700">
                    {{ $employe->role }}
                </span>
            @endif
            @if(!empty($employe->department_name))
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 border border-slate-200 px-2.5 py-1 text-[10px] font-bold text-slate-700">
                    <i data-lucide="{{ $employe->department_icon ?? 'briefcase' }}" class="h-3.5 w-3.5 text-indigo-600"></i>
                    <span>{{ $employe->department_name }}</span>
                    <span class="text-[9px] font-mono px-1 py-0.2 bg-slate-200/70 text-slate-600 rounded">{{ $employe->department_code ?? '' }}</span>
                </span>
            @endif
            <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2.5 py-1 text-[10px] font-bold text-slate-600">
                {{ count($employe->roles ?? []) }} accès module
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Colonne principale : un seul formulaire.
             L'enregistrement remplace intégralement les rôles de l'employé —
             une case décochée vaut retrait. Séparer identité et accès en deux
             formulaires ferait donc effacer les accès à chaque modification du
             nom. --}}
        <form method="POST"
              action="{{ route('tech.establishments.users.update', ['tenant' => $tenant, 'user' => $employe->id]) }}"
              class="lg:col-span-2 space-y-6">
            @csrf
            <input type="hidden" name="return_to" value="fiche">

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-extrabold text-slate-800">Informations</h3>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        Enregistrées dans la base de {{ $tenant->name }} : l'employé les voit à sa prochaine connexion.
                    </p>
                </div>

                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="f-name" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Nom complet</label>
                            <input type="text" id="f-name" name="name" required value="{{ old('name', $employe->name) }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="f-email" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Adresse e-mail</label>
                            <input type="email" id="f-email" name="email" required value="{{ old('email', $employe->email) }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="f-phone" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Téléphone</label>
                            <input type="text" id="f-phone" name="phone" value="{{ old('phone', $employe->phone) }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label for="f-password" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Mot de passe <span class="font-medium normal-case text-slate-400">(vide = inchangé)</span>
                            </label>
                            <input type="password" id="f-password" name="password" autocomplete="new-password"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="f-department" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Département de rattachement</label>
                            <select id="f-department" name="department_id"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white">
                                <option value="">-- Aucun département --</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" @selected((int) old('department_id', $employe->department_id ?? null) === (int) $dept->id)>
                                        {{ $dept->name }} ({{ $dept->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="f-role" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Rôle principal (Historique)</label>
                            <input type="text" id="f-role" name="role" list="f-role-list" value="{{ old('role', $employe->role) }}"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            <datalist id="f-role-list">
                                @foreach($rolesAssignables as $r)<option value="{{ $r->slug }}">{{ $r->name }}</option>@endforeach
                            </datalist>
                        </div>
                    </div>
                    <p class="mt-1 text-[11px] text-slate-400">
                        L'employé hérite automatiquement des modules associés à son département. Le rôle historique assure la compatibilité avec les écrans legacy.
                    </p>
                </div>
            </section>

            {{-- Section 2 : Matrice des Permissions Modulaires par Domaine --}}
            @php
                $moduleDomainGroups = [
                    'Direction Générale' => [
                        'icon' => 'briefcase',
                        'accent' => 'indigo',
                        'modules' => [
                            'analytics'  => ['label' => 'Analytics & Tour de Contrôle', 'service' => 'PMS', 'desc' => 'Tableaux de bord consolidés, KPIs et graphiques de rentabilité.'],
                            'ai'         => ['label' => 'Assistant IA & Suggestions', 'service' => 'PMS', 'desc' => 'Analyses prédictives, rédaction de réponses et recommandations intelligentes.'],
                            'parametres' => ['label' => 'Paramètres Généraux', 'service' => 'PMS', 'desc' => 'Configuration de l’établissement, devises, taxes et modèles d’impression.'],
                        ]
                    ],
                    'Réception & Front Office' => [
                        'icon' => 'calendar-check',
                        'accent' => 'sky',
                        'modules' => [
                            'hebergement' => ['label' => 'Chambres & Hébergement', 'service' => 'PMS', 'desc' => 'Gestion de l’inventaire des chambres, typologies, tarifs et statuts en direct.'],
                            'reservations'=> ['label' => 'Réservations & Séjours', 'service' => 'PMS', 'desc' => 'Planning, arrivées/départs, réservations individuelles et groupes.'],
                            'clients'     => ['label' => 'Clients & CRM', 'service' => 'PMS', 'desc' => 'Fiches clients, historique de séjours, préférences et programme de fidélité.'],
                            'website'     => ['label' => 'Site Web Vitrine & Réservation', 'service' => 'Site Web', 'desc' => 'Portail public responsive, catalogue web des chambres et réservation directe.'],
                        ]
                    ],
                    'Hébergement & Housekeeping' => [
                        'icon' => 'sparkles',
                        'accent' => 'teal',
                        'modules' => [
                            'housekeeping' => ['label' => 'Housekeeping & Entretien', 'service' => 'PMS', 'desc' => 'Attribution des étages, suivi du nettoyage, fiches techniques et inspection.'],
                        ]
                    ],
                    'Restauration (Food & Beverage - F&B)' => [
                        'icon' => 'utensils',
                        'accent' => 'amber',
                        'modules' => [
                            'restaurant' => ['label' => 'Restaurant & Cuisine', 'service' => 'PMS', 'desc' => 'Prise de commande sur place, cuisine (KDS), menus, recettes et facturation.'],
                            'portail'    => ['label' => 'Portail QR Room Service', 'service' => 'PMS', 'desc' => 'Commande en chambre par scan de QR code sur smartphone client.'],
                        ]
                    ],
                    'Boutique & Commerce' => [
                        'icon' => 'store',
                        'accent' => 'orange',
                        'modules' => [
                            'shop' => ['label' => 'Boutique & Caisse Vente', 'service' => 'PMS', 'desc' => 'Catalogue articles souvenirs/produits, tickets et caisse dédiée.'],
                        ]
                    ],
                    'Comptabilité & Finance' => [
                        'icon' => 'calculator',
                        'accent' => 'emerald',
                        'modules' => [
                            'comptabilite' => ['label' => 'Comptabilité de Caisse', 'service' => 'PMS', 'desc' => 'Ouverture/clôture de caisse, encaissements et décaissements quotidiens.'],
                            'ledger'       => ['label' => 'Grand Livre (SYSCOHADA)', 'service' => 'PMS', 'desc' => 'Plan comptable OHADA, journaux, balance générale, lettrage et TVA.'],
                        ]
                    ],
                    'Économat & Approvisionnements' => [
                        'icon' => 'warehouse',
                        'accent' => 'violet',
                        'modules' => [
                            'economat' => ['label' => 'Économat & Stocks Centraux', 'service' => 'PMS', 'desc' => 'Bons de commande fournisseurs, entrées/sorties de stock et inventaires.'],
                        ]
                    ],
                    'Qualité, Contrôle de Gestion & GRC' => [
                        'icon' => 'shield-check',
                        'accent' => 'teal',
                        'modules' => [
                            'grc' => ['label' => 'Plateforme GRC & Audit Interne', 'service' => 'GRC', 'desc' => 'Cartographie des risques 5x5, conformité, audits et fiches d’incidents.'],
                        ]
                    ],
                    'Ressources Humaines & Communication' => [
                        'icon' => 'users',
                        'accent' => 'purple',
                        'modules' => [
                            'utilisateurs' => ['label' => 'Gestion des Employés', 'service' => 'PMS', 'desc' => 'Fiches employés, comptes d’accès, départements et traçabilité.'],
                            'discussions'  => ['label' => 'Discussions & Messagerie', 'service' => 'PMS', 'desc' => 'Messagerie instantanée interne et échanges sécurisés entre collègues.'],
                        ]
                    ],
                    'Informatique & Infrastructure IT' => [
                        'icon' => 'cpu',
                        'accent' => 'slate',
                        'modules' => [
                            'api' => ['label' => 'API d’Intégration', 'service' => 'PMS', 'desc' => 'Routes API exposées pour interconnecter les services et logiciels externes.'],
                            'pwa' => ['label' => 'PWA & Mode Hors-ligne', 'service' => 'PMS', 'desc' => 'Fonctionnement hors-ligne et synchronisation en tâche de fond.'],
                        ]
                    ],
                ];
                $userPermissions = $employe->module_permissions ?? [];
            @endphp

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800">Accès par module</h3>
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            Configurez les permissions modulaires par département. Le mode <span class="font-semibold text-slate-700">Hériter</span> applique automatiquement les privilèges du département de rattachement.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-[10px] font-semibold text-slate-500">
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-slate-400"></span>Hériter</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Écriture</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Lecture</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-rose-500"></span>Refusé</span>
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    @foreach($moduleDomainGroups as $groupTitle => $group)
                        <div class="rounded-xl border border-slate-150 bg-slate-50/50 p-4">
                            <div class="flex items-center gap-2 mb-3">
                                <div class="flex h-6 w-6 items-center justify-center rounded-md bg-white border border-slate-200 text-slate-700 shadow-2xs">
                                    <i data-lucide="{{ $group['icon'] }}" class="h-3.5 w-3.5"></i>
                                </div>
                                <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wide">{{ $groupTitle }}</h4>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @foreach($group['modules'] as $modKey => $mod)
                                    @php
                                        $currentPerm = $userPermissions[$modKey] ?? 'inherit';
                                    @endphp
                                    <div class="rounded-lg border border-slate-200 bg-white p-3 shadow-2xs flex flex-col justify-between gap-2.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="text-xs font-bold text-slate-800">{{ $mod['label'] }}</span>
                                                    <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded border
                                                        {{ $mod['service'] === 'PMS' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : ($mod['service'] === 'Site Web' ? 'bg-rose-50 text-rose-700 border-rose-100' : 'bg-teal-50 text-teal-700 border-teal-100') }}">
                                                        {{ $mod['service'] }}
                                                    </span>
                                                </div>
                                                <p class="text-[10px] text-slate-400 mt-0.5 leading-relaxed">{{ $mod['desc'] }}</p>
                                            </div>
                                        </div>

                                        {{-- 4-State Segmented Control --}}
                                        <div class="flex items-center rounded-lg bg-slate-100 p-1 border border-slate-200/60">
                                            <label class="flex-1 text-center cursor-pointer" title="Hérite des droits du département de rattachement">
                                                <input type="radio" name="module_permissions[{{ $modKey }}]" value="inherit" class="sr-only peer" @checked($currentPerm === 'inherit')>
                                                <span class="block px-1.5 py-1 text-[10px] font-semibold rounded transition peer-checked:bg-white peer-checked:text-slate-900 peer-checked:shadow-xs text-slate-500 hover:text-slate-800">
                                                    Hériter
                                                </span>
                                            </label>
                                            <label class="flex-1 text-center cursor-pointer" title="Accès complet (Lecture / Écriture / Modification)">
                                                <input type="radio" name="module_permissions[{{ $modKey }}]" value="write" class="sr-only peer" @checked($currentPerm === 'write')>
                                                <span class="block px-1.5 py-1 text-[10px] font-semibold rounded transition peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:shadow-xs text-slate-500 hover:text-slate-800">
                                                    Écriture
                                                </span>
                                            </label>
                                            <label class="flex-1 text-center cursor-pointer" title="Accès restreint en consultation seule (actions bloquées)">
                                                <input type="radio" name="module_permissions[{{ $modKey }}]" value="read" class="sr-only peer" @checked($currentPerm === 'read')>
                                                <span class="block px-1.5 py-1 text-[10px] font-semibold rounded transition peer-checked:bg-amber-500 peer-checked:text-white peer-checked:shadow-xs text-slate-500 hover:text-slate-800">
                                                    Lecture
                                                </span>
                                            </label>
                                            <label class="flex-1 text-center cursor-pointer" title="Accès refusé (module masqué et bloqué)">
                                                <input type="radio" name="module_permissions[{{ $modKey }}]" value="none" class="sr-only peer" @checked($currentPerm === 'none')>
                                                <span class="block px-1.5 py-1 text-[10px] font-semibold rounded transition peer-checked:bg-rose-600 peer-checked:text-white peer-checked:shadow-xs text-slate-500 hover:text-slate-800">
                                                    Refusé
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Accordéon pour les rôles pivot legacy wetchah_app --}}
                @if($rolesParModule->isNotEmpty())
                    <div class="px-6 pb-6 pt-2">
                        <details class="group rounded-xl border border-slate-200 bg-slate-50/50 p-4 transition">
                            <summary class="flex cursor-pointer items-center justify-between text-xs font-bold text-slate-700 select-none">
                                <span class="flex items-center gap-2">
                                    <i data-lucide="shield" class="h-4 w-4 text-slate-400"></i>
                                    <span>Rôles applicatifs historiques (Table pivot wetchah_app)</span>
                                </span>
                                <span class="text-[10px] text-slate-400 font-normal group-open:rotate-180 transition-transform">▼</span>
                            </summary>
                            <p class="mt-2 mb-4 text-[11px] text-slate-400">
                                Ces rôles conservent la compatibilité avec les versions antérieures de l'application établissement.
                            </p>
                            <div class="space-y-4 pt-2 border-t border-slate-200/60">
                                @foreach($rolesParModule as $module => $roles)
                                    <div>
                                        <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $module }}</p>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            @foreach($roles as $r)
                                                @php $actif = in_array((int) $r->id, $rolesActuels, true); @endphp
                                                <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border p-2.5 transition hover:border-indigo-300 hover:bg-indigo-50/40
                                                    {{ $actif ? 'border-indigo-300 bg-indigo-50/40' : 'border-slate-200 bg-white' }}">
                                                    <input type="checkbox" name="roles[]" value="{{ $r->id }}" @checked($actif)
                                                           class="h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-xs font-semibold text-slate-800">{{ $r->name }}</span>
                                                        @if($r->description)
                                                            <span class="block truncate text-[10px] text-slate-400">{{ $r->description }}</span>
                                                        @endif
                                                    </span>
                                                    <select name="levels[{{ $r->id }}]"
                                                            class="shrink-0 rounded-md border border-slate-300 px-1.5 py-1 text-[10px] outline-none focus:border-indigo-500 bg-white">
                                                        <option value="write" @selected(($niveauActuel[(int) $r->id] ?? 'write') === 'write')>Lecture/écriture</option>
                                                        <option value="read" @selected(($niveauActuel[(int) $r->id] ?? null) === 'read')>Lecture seule</option>
                                                    </select>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-4 border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <p class="text-[10px] text-slate-400">
                        Les modifications sont enregistrées directement dans la base de données de {{ $tenant->name }}.
                    </p>
                    <button type="submit"
                            class="shrink-0 rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700 cursor-pointer">
                        Enregistrer les accès
                    </button>
                </div>
            </section>
        </form>

        {{-- Colonne latérale : état et actions.
             Hors du formulaire principal — deux formulaires ne s'imbriquent
             pas en HTML. --}}
        <div class="space-y-6">

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h3 class="text-sm font-extrabold text-slate-800">État du compte</h3>
                </div>
                <dl class="divide-y divide-slate-100 text-xs">
                    @php
                        $creele   = $dateOuNull($employe->created_at);
                        $connexion = $dateOuNull($employe->last_login_at);
                        $verifie  = $dateOuNull($employe->email_verified_at);
                    @endphp
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-slate-500">Identifiant</dt>
                        <dd class="font-mono text-slate-700">#{{ $employe->id }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-slate-500">Statut</dt>
                        <dd>
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold
                                {{ $employe->is_active ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $employe->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                {{ $employe->is_active ? 'Actif' : 'Inactif' }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-slate-500">Dernière connexion</dt>
                        <dd class="text-slate-700">{{ $connexion ? $connexion->format('d/m/Y à H:i') : 'Jamais connecté' }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-slate-500">Compte créé</dt>
                        <dd class="text-slate-700">{{ $creele ? $creele->format('d/m/Y') : '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-slate-500">E-mail vérifié</dt>
                        <dd class="text-slate-700">{{ $verifie ? $verifie->format('d/m/Y') : 'Non vérifié' }}</dd>
                    </div>
                </dl>

                <div class="border-t border-slate-100 p-5">
                    <form method="POST"
                          action="{{ route('tech.establishments.users.toggle-active', ['tenant' => $tenant, 'user' => $employe->id]) }}"
                          @if($employe->is_active) onsubmit="return confirm('Désactiver {{ addslashes($employe->name) }} ? Cette personne ne pourra plus se connecter à {{ addslashes($tenant->name) }}.');" @endif>
                        @csrf
                        <input type="hidden" name="return_to" value="fiche">
                        <button type="submit"
                                class="w-full rounded-lg border px-4 py-2.5 text-xs font-bold transition
                                    {{ $employe->is_active
                                        ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'
                                        : 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}">
                            {{ $employe->is_active ? 'Désactiver le compte' : 'Activer le compte' }}
                        </button>
                    </form>
                    <p class="mt-2 text-[10px] leading-relaxed text-slate-400">
                        Un compte désactivé ne peut plus se connecter, mais son historique et ses écritures restent
                        intacts. C'est le geste à préférer à la suppression pour un départ.
                    </p>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden">
                <div class="border-b border-red-100 bg-red-50/60 px-5 py-3.5">
                    <h3 class="text-sm font-extrabold text-red-800">Supprimer</h3>
                </div>
                <div class="p-5">
                    @if($dernierManager)
                        {{-- Retirer le dernier manager fermerait l'administration de
                             l'établissement : on le dit avant le clic. --}}
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-[11px] leading-relaxed text-amber-900">
                            <strong>Suppression impossible.</strong> {{ $employe->name }} est le dernier manager actif
                            de {{ $tenant->name }} : le retirer priverait l'établissement de toute administration.
                            Créez un autre manager avant.
                        </p>
                    @else
                        <form method="POST"
                              action="{{ route('tech.establishments.users.destroy', ['tenant' => $tenant, 'user' => $employe->id]) }}"
                              onsubmit="return confirm('Supprimer définitivement le compte de {{ addslashes($employe->name) }} dans {{ addslashes($tenant->name) }} ? Cette action est irréversible.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-xs font-bold text-red-800 transition hover:bg-red-100">
                                Supprimer définitivement
                            </button>
                        </form>
                        <p class="mt-2 text-[10px] leading-relaxed text-slate-400">
                            Le compte est retiré de la base de l'établissement. Irréversible.
                        </p>
                    @endif
                </div>
            </section>
        </div>
    </div>
</main>

</body>
</html>
