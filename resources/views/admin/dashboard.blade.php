@php
    $isTech = auth()->user()->isTechAdmin();
    $isOwner = auth()->user()->isOwner();

    if ($isTech) {
        $tabs = [
            'dashboard' => [
                'label' => 'Supervision',
                'title' => 'Supervision multi-etablissements',
                'description' => 'Vue globale des etablissements, utilisateurs, activite, alertes et indicateurs consolides.',
                'items' => ['Etablissements actifs', 'Utilisateurs actifs', 'Reservations du jour', 'Alertes globales'],
            ],
            'tenants' => [
                'label' => 'Etablissements',
                'title' => 'Gestion des etablissements',
                'description' => 'Creation, configuration, activation, suspension et diagnostic des tenants.',
                'items' => ['Creation tenant', 'Configuration generale', 'Modules actifs', 'Etat onboarding'],
            ],
            'managers' => [
                'label' => 'Managers',
                'title' => 'Gestion des managers',
                'description' => 'Creation, activation, reinitialisation et rattachement des managers aux etablissements.',
                'items' => ['Compte manager', 'Reinitialisation mot de passe', 'Activation compte', 'Rattachement tenant'],
            ],
            'roles' => [
                'label' => 'Roles',
                'title' => 'Roles et permissions',
                'description' => 'Consultation des roles operationnels et configuration future des permissions par module.',
                'items' => ['Roles disponibles', 'Permissions par module', 'Roles par tenant', 'Roles personnalises'],
            ],
            'modules' => [
                'label' => 'Modules',
                'title' => 'Modules plateforme',
                'description' => 'Activation des modules disponibles selon les besoins de chaque etablissement.',
                'items' => ['Hotel', 'Restaurant', 'Boutique', 'Housekeeping', 'Comptabilite', 'IA'],
            ],
            'support' => [
                'label' => 'Support',
                'title' => 'Support operationnel',
                'description' => 'Lecture globale et futur mode assistance audite pour diagnostiquer un etablissement.',
                'items' => ['Mode lecture', 'Mode assistance', 'Justification', 'Historique interventions'],
            ],
            'settings' => [
                'label' => 'Configuration',
                'title' => 'Configuration globale',
                'description' => 'Parametres applicatifs, limites, integrations et statuts techniques de la plateforme.',
                'items' => ['Parametres globaux', 'Limites tenant', 'Integrations', 'Etat technique'],
            ],
            'billing' => [
                'label' => 'Licences',
                'title' => 'Abonnements et licences',
                'description' => 'Gestion future des plans, modules inclus, echeances et suspensions.',
                'items' => ['Plan actif', 'Modules inclus', 'Expiration', 'Historique abonnement'],
            ],
            'imports' => [
                'label' => 'Import/Export',
                'title' => 'Import et export',
                'description' => 'Zone dediee aux imports et exports de donnees sous forme de fichiers Excel.',
                'items' => ['Import etablissements', 'Import utilisateurs', 'Export audit', 'Export supervision'],
            ],
            'system' => [
                'label' => 'Systeme',
                'title' => 'Sante systeme',
                'description' => 'Surveillance technique des services, files, integrations et erreurs applicatives.',
                'items' => ['Files attente', 'Erreurs API', 'Emails', 'Stockage'],
            ],
        ];
    } else {
        $tabs = [
            'dashboard' => [
                'label' => 'Vue d\'ensemble',
                'title' => 'Tableau de bord Business',
                'description' => 'Indicateurs consolidés de vos établissements.',
                'items' => ['Revenus de l\'année', 'Taux d\'occupation', 'Réservations consolidées', 'Status opérationnel'],
            ],
            'establishments' => [
                'label' => 'Mes Établissements',
                'title' => 'Registre de mes établissements',
                'description' => 'Consulter et accéder à vos établissements.',
                'items' => ['Hôtels', 'Restaurants', 'Boutiques', 'Housekeeping'],
            ],
            'analytics' => [
                'label' => 'Statistiques',
                'title' => 'Analyses et statistiques',
                'description' => 'Rapports détaillés de performance et croissance.',
                'items' => ['Volume réservations', 'Fidélité client', 'Fréquentation'],
            ],
            'employees' => [
                'label' => 'Employés',
                'title' => 'Gestion des employés',
                'description' => 'Registre des employés associés à vos établissements.',
                'items' => ['Membres du personnel', 'Activités récentes', 'Permissions'],
            ],
            'revenue' => [
                'label' => 'Rapport',
                'title' => 'Rapports & audit financier',
                'description' => 'Encaissements, dépenses, audit de caisse et export de rapports financiers complets.',
                'items' => ['Encaissements', 'Dépenses', 'Audit de caisse', 'Export Excel / PDF'],
            ],
        ];
    }

    $activeTab = $activeTab ?? request('tab', 'dashboard');
    if (!array_key_exists($activeTab, $tabs)) {
        $activeTab = 'dashboard';
    }
    $active = $tabs[$activeTab];
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration - {{ \App\Models\Tenant::first()?->name ?? 'Villa Boutanga' }}</title>
    <!-- Premium Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased font-body">

    <!-- Top Full-Width Navigation Bar (Fixed to top) -->
    <header class="sticky top-0 z-30 w-full bg-[#0f172a] border-b border-slate-800 text-white shadow-md">
        <div class="mx-auto max-w-7xl px-5 lg:px-8 flex items-center justify-between h-16">
            <div class="flex items-center gap-8">
                <div class="flex items-center gap-2">
                    <div class="text-sm font-extrabold uppercase tracking-wider text-white">
                        MEKA ERP
                    </div>
                    @if($isTech)
                        <span class="inline-flex items-center rounded-full bg-emerald-500/20 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-500/30">
                            TECH
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-indigo-500/20 px-2 py-0.5 text-[10px] font-bold text-indigo-400 border border-indigo-500/30">
                            BUSINESS
                        </span>
                    @endif
                </div>
                <nav class="hidden md:flex items-center gap-1.5" aria-label="Navigation administration">
                    @foreach($tabs as $key => $tab)
                        <a
                            href="{{ route($isTech ? 'tech.dashboard' : 'business.dashboard', ['tab' => $key]) }}"
                            class="rounded-md px-3 py-1.5 text-xs font-semibold tracking-wide transition {{ $activeTab === $key ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"
                        >
                            {{ $tab['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
            
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md border border-slate-700 bg-slate-800 px-3.5 py-1.5 text-xs font-bold text-slate-200 transition hover:bg-slate-700 hover:text-white">
                    Déconnexion
                </button>
            </form>
        </div>
        <!-- Mobile Navigation -->
        <div class="md:hidden border-t border-slate-800 px-5 py-2 overflow-x-auto">
            <div class="flex gap-1.5 min-w-max">
                @foreach($tabs as $key => $tab)
                    <a
                        href="{{ route($isTech ? 'tech.dashboard' : 'business.dashboard', ['tab' => $key]) }}"
                        class="rounded-md px-2.5 py-1 text-xs font-semibold transition {{ $activeTab === $key ? 'bg-indigo-600 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"
                    >
                        {{ $tab['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </header>

    <main class="mx-auto min-h-screen w-full max-w-7xl px-5 py-8 lg:px-8">
        
        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mb-6 rounded-md bg-green-50 border border-green-200 p-4 text-xs font-bold text-green-800 shadow-sm flex items-center gap-2">
                <svg class="h-4 w-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 rounded-md bg-red-50 border border-red-200 p-4 text-xs font-bold text-red-800 shadow-sm flex items-center gap-2">
                <svg class="h-4 w-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(session('temp_password_info'))
            <div class="mb-6 rounded-lg bg-emerald-50 border-2 border-emerald-500 p-5 shadow-sm">
                <div class="flex items-start gap-3.5">
                    <div class="rounded-full bg-emerald-500 p-1 text-white shadow-sm shrink-0">
                        <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="w-full">
                        <h3 class="text-xs font-bold text-emerald-900 uppercase tracking-wide">Mot de passe temporaire généré !</h3>
                        <p class="mt-1 text-xs text-emerald-700 leading-relaxed">
                            Un nouveau mot de passe a été généré pour <strong>{{ session('temp_password_info')['name'] }}</strong> ({{ session('temp_password_info')['email'] }}).
                            <br>Veuillez copier ce mot de passe et le lui communiquer de manière sécurisée. L'utilisateur devra le modifier lors de sa prochaine connexion.
                        </p>
                        <div class="mt-3 flex items-center gap-2">
                            <span class="text-xs font-bold text-emerald-800">Mot de passe :</span>
                            <input type="text" readonly value="{{ session('temp_password_info')['password'] }}" class="font-mono text-xs border border-emerald-300 bg-white px-3 py-1 rounded text-emerald-950 select-all font-semibold outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($activeTab === 'tenants' || $activeTab === 'establishments')
            <!-- ================= GESTION DES ETABLISSEMENTS LAYOUT ================= -->
            
            <!-- Breadcrumb Path -->
            <p class="text-[10px] font-bold tracking-widest text-indigo-600 uppercase">{{ $isTech ? 'ÉTABLISSEMENTS' : 'MES ÉTABLISSEMENTS' }}</p>
            
            <!-- Page Title and Subtitle Row -->
            <div class="mt-2 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2 border-b border-slate-200 pb-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">{{ $isTech ? 'Gestion des Établissements' : 'Mes Établissements' }}</h1>
                    <p class="text-xs text-slate-500 mt-1">{{ $isTech ? 'Gérer les informations générales, le statut et les paramètres des filiales de l\'ONG.' : 'Consulter et accéder à vos établissements actifs.' }}</p>
                </div>
                @if($isTech)
                    <a href="{{ route('tech.establishments.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition group">
                        <svg class="h-4 w-4 transition-transform group-hover:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Nouvel Établissement
                    </a>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                @forelse($tenants as $tenant)
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden flex flex-col justify-between group hover:shadow-md hover:border-slate-300 transition-all duration-200">
                        <div>
                            <!-- Header with logo / image placeholder -->
                            <div class="h-32 bg-slate-900 flex items-center justify-center relative">
                                @if(!empty($tenant->settings['logo']))
                                    <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="Logo {{ $tenant->name }}" class="h-20 object-contain">
                                @else
                                    <div class="text-indigo-400 flex flex-col items-center">
                                        <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                                        </svg>
                                        <span class="text-[9px] font-bold tracking-widest uppercase text-slate-500 mt-2">Aucun logo</span>
                                    </div>
                                @endif
                                <div class="absolute top-3 right-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $tenant->is_active ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                                        {{ $tenant->is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Details -->
                            <div class="p-5">
                                <h3 class="text-base font-bold text-slate-800 tracking-tight">{{ $tenant->name }}</h3>
                                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $tenant->slug }}</p>
                                
                                <div class="mt-4 space-y-2.5 text-xs text-slate-600 border-t border-slate-100 pt-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Pays :</span>
                                        <span class="font-semibold text-slate-700">{{ $tenant->settings['country'] ?? 'Cameroun' }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Devise :</span>
                                        <span class="font-mono font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.5 text-[9px]">{{ $tenant->currency }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Utilisateurs :</span>
                                        <span class="font-semibold text-slate-700">{{ $tenant->users_count ?? 0 }}</span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-slate-400">Lien d'accès :</span>
                                        <a href="http://localhost:{{ $tenant->app_port }}" target="_blank" class="font-semibold text-indigo-600 hover:text-indigo-850 hover:underline font-mono text-[10px] truncate max-w-[150px]" title="http://localhost:{{ $tenant->app_port }}">
                                            localhost:{{ $tenant->app_port }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div class="bg-slate-50 px-5 py-3 border-t border-slate-100 flex justify-end gap-2" x-data="{}">
                            @if($isTech)
                                <a 
                                    href="{{ route('tech.establishments.show', $tenant) }}"
                                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Gérer
                                </a>
                            @else
                                <a
                                    href="{{ route('business.establishments.show', $tenant) }}"
                                    class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    Gérer
                                </a>
                                <a
                                    href="http://localhost:{{ $tenant->app_port }}"
                                    target="_blank"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-indigo-750 transition shadow-sm"
                                >
                                    <span>Accéder</span>
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-12 text-center text-slate-400 italic text-xs bg-white rounded-lg border border-slate-200">
                        Aucun établissement enregistré pour le moment.
                    </div>
                @endforelse
            </div>
        @elseif($activeTab === 'imports')
            {{-- ================= IMPORT / EXPORT & BACKUPS ================= --}}
            <p class="text-[10px] font-bold tracking-widest text-indigo-600 uppercase">IMPORT / EXPORT</p>
            <div class="mt-2 border-b border-slate-200 pb-4">
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Sauvegardes & Export de données</h1>
                <p class="text-xs text-slate-500 mt-1">Créez des sauvegardes complètes de la base de chaque établissement, à la demande ou automatiquement selon une planification.</p>
            </div>

            <div x-data="{
                    tab: 'backups',
                    data: null,
                    loading: false,
                    filter: '',
                    importSel: '',
                    tenantMap: {{ Illuminate\Support\Js::from($tenants->mapWithKeys(fn ($t) => [$t->id => ['slug' => $t->slug, 'name' => $t->name]])) }},
                    get importBackups() {
                        if (!this.data || !this.importSel) return [];
                        const slug = this.tenantMap[this.importSel]?.slug;
                        return this.data.backups.filter(b => b.slug === slug && b.status === 'completed');
                    },
                    async load() {
                        this.loading = true;
                        try {
                            const url = new URL('{{ route('tech.backups.index') }}', window.location.origin);
                            if (this.filter) url.searchParams.set('slug', this.filter);
                            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            this.data = await r.json();
                        } catch (e) { this.data = null; }
                        this.loading = false;
                    }
                 }" x-init="load()">

                {{-- Sous-onglets --}}
                <div class="flex flex-wrap gap-1 border-b border-slate-200 mt-6 mb-6">
                    <button type="button" @click="tab = 'backups'"
                            class="px-4 py-2.5 text-xs font-bold transition border-b-2 -mb-px cursor-pointer"
                            :class="tab === 'backups' ? 'text-indigo-700 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-800'">
                        Sauvegardes
                    </button>
                    <button type="button" @click="tab = 'schedule'"
                            class="px-4 py-2.5 text-xs font-bold transition border-b-2 -mb-px cursor-pointer"
                            :class="tab === 'schedule' ? 'text-indigo-700 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-800'">
                        Planification
                    </button>
                    <button type="button" @click="tab = 'import'"
                            class="px-4 py-2.5 text-xs font-bold transition border-b-2 -mb-px cursor-pointer"
                            :class="tab === 'import' ? 'text-indigo-700 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-800'">
                        Importation
                    </button>
                    <button type="button" @click="tab = 'exports'"
                            class="px-4 py-2.5 text-xs font-bold transition border-b-2 -mb-px cursor-pointer"
                            :class="tab === 'exports' ? 'text-indigo-700 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-800'">
                        Exports CSV
                    </button>
                </div>

                {{-- ============ SAUVEGARDES ============ --}}
                <div x-show="tab === 'backups'">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-2">
                            <select x-model="filter" @change="load()"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500">
                                <option value="">Tous les établissements</option>
                                @foreach($tenants as $t)
                                    <option value="{{ $t->slug }}">{{ $t->name }}</option>
                                @endforeach
                            </select>
                            <button type="button" @click="load()" :disabled="loading"
                                    class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition disabled:opacity-50 cursor-pointer">
                                <svg class="h-3.5 w-3.5" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                Actualiser
                            </button>
                        </div>
                        <form method="POST" action="{{ route('tech.backups.all') }}" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Sauvegarde en cours…';">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm cursor-pointer disabled:opacity-60">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                                Sauvegarder tout
                            </button>
                        </form>
                    </div>

                    {{-- Lancer un backup ponctuel par établissement --}}
                    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm mb-5" x-data="{ target: '' }">
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                            <div class="flex-1">
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Sauvegarder un établissement précis</label>
                                <select x-model="target" class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500">
                                    <option value="">— Choisir —</option>
                                    @foreach($tenants as $t)
                                        @if($t->provisioned_at)
                                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <form method="POST" :action="`{{ url('tech/backups') }}/${target}`" class="shrink-0 sm:self-end"
                                  @submit="if(!target){$event.preventDefault(); return;} $el.querySelector('button').disabled = true; $el.querySelector('button').textContent = 'Sauvegarde…';">
                                @csrf
                                <button type="submit" :disabled="!target" class="w-full sm:w-auto rounded-lg bg-slate-800 px-4 py-2.5 text-xs font-bold text-white hover:bg-slate-900 transition disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer">
                                    Créer la sauvegarde
                                </button>
                            </form>
                        </div>
                    </div>

                    <div x-show="loading && !data" class="rounded-lg border border-slate-200 bg-white p-8 text-center text-xs text-slate-400">Chargement…</div>

                    <template x-if="data">
                        <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                            <th class="px-5 py-3">Établissement</th>
                                            <th class="px-3 py-3">Fichier</th>
                                            <th class="px-3 py-3">Taille</th>
                                            <th class="px-3 py-3">Type</th>
                                            <th class="px-3 py-3">Date</th>
                                            <th class="px-5 py-3 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <template x-for="b in data.backups" :key="b.id">
                                            <tr class="hover:bg-slate-50/60 transition align-top">
                                                <td class="px-5 py-3 font-bold text-slate-800" x-text="b.tenant"></td>
                                                <td class="px-3 py-3">
                                                    <span class="font-mono text-[10px] text-slate-500" x-text="b.filename"></span>
                                                    <template x-if="b.status === 'failed'">
                                                        <p class="text-[10px] text-red-500 mt-0.5" x-text="b.error"></p>
                                                    </template>
                                                </td>
                                                <td class="px-3 py-3 text-slate-600" x-text="b.status === 'completed' ? b.size : '—'"></td>
                                                <td class="px-3 py-3">
                                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase tracking-wider"
                                                          :class="b.trigger === 'scheduled' ? 'bg-violet-50 text-violet-600 border border-violet-200' : (b.trigger === 'imported' ? 'bg-sky-50 text-sky-600 border border-sky-200' : 'bg-slate-100 text-slate-500 border border-slate-200')"
                                                          x-text="b.trigger === 'scheduled' ? 'Auto' : (b.trigger === 'imported' ? 'Importé' : 'Manuel')"></span>
                                                    <template x-if="b.status === 'failed'">
                                                        <span class="ml-1 text-[9px] font-bold px-1.5 py-0.5 rounded bg-red-50 text-red-600 border border-red-200 uppercase">Échec</span>
                                                    </template>
                                                </td>
                                                <td class="px-3 py-3 whitespace-nowrap">
                                                    <span class="text-slate-700" x-text="b.at"></span>
                                                    <p class="text-[10px] text-slate-400" x-text="b.ago"></p>
                                                </td>
                                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                                    <template x-if="b.status === 'completed'">
                                                        <a :href="`{{ url('tech/backups') }}/${b.id}/download`" class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">Télécharger</a>
                                                    </template>
                                                    <form method="POST" :action="`{{ url('tech/backups') }}/${b.id}`" class="inline ml-2" @submit="return confirm('Supprimer cette sauvegarde ?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="font-semibold text-red-500 hover:text-red-700 cursor-pointer">Supprimer</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        </template>
                                        <template x-if="data.backups.length === 0">
                                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">Aucune sauvegarde pour ce filtre. Lancez-en une ci-dessus.</td></tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- ============ IMPORTATION (restauration de backups) ============ --}}
                <div x-show="tab === 'import'" x-cloak>

                    {{-- Rôle réel de l'importation --}}
                    <div class="rounded-lg border border-amber-200 bg-amber-50/60 p-5 mb-5">
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg bg-amber-100 p-2 shrink-0">
                                <svg class="h-5 w-5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 00-2.25 2.25v9a2.25 2.25 0 002.25 2.25h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25H15m0-3l-3-3m0 0l-3 3m3-3v11.25" /></svg>
                            </div>
                            <div class="text-xs leading-relaxed">
                                <h3 class="text-sm font-bold text-amber-900">Que fait l'importation d'une sauvegarde ?</h3>
                                <p class="mt-1.5 text-amber-950/80">Importer une sauvegarde <strong>restaure la base de données de l'établissement</strong> : le dump SQL est rejoué dans son container PostgreSQL, les tables sont supprimées puis recréées, et <strong>toutes les données actuelles de l'application (réservations, clients, chambres, utilisateurs…) sont remplacées</strong> par celles contenues dans la sauvegarde.</p>
                                <ul class="mt-2.5 space-y-1 text-amber-950/80">
                                    <li class="flex items-start gap-1.5"><span class="mt-1.5 h-1 w-1 rounded-full bg-amber-600 shrink-0"></span> <span><strong>Reprise après incident</strong> : revenir à un état sain après une panne ou une mauvaise manipulation.</span></li>
                                    <li class="flex items-start gap-1.5"><span class="mt-1.5 h-1 w-1 rounded-full bg-amber-600 shrink-0"></span> <span><strong>Retour arrière</strong> : annuler des modifications récentes en rechargeant une sauvegarde antérieure.</span></li>
                                    <li class="flex items-start gap-1.5"><span class="mt-1.5 h-1 w-1 rounded-full bg-amber-600 shrink-0"></span> <span><strong>Migration</strong> : réinjecter les données d'un établissement sur une instance reprovisionnée.</span></li>
                                </ul>
                                <p class="mt-2.5 font-semibold text-amber-900">⚠ Opération irréversible : les données écrasées ne sont pas récupérables. Créez une sauvegarde fraîche avant d'importer. L'opération est auditée.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Choix de l'établissement cible --}}
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm mb-5">
                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Établissement à restaurer</label>
                        <select x-model="importSel" @change="if (filter) { filter = ''; load(); }"
                                class="block w-full sm:max-w-md rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            <option value="">— Choisir un établissement —</option>
                            @foreach($tenants as $t)
                                @if($t->provisioned_at)
                                    <option value="{{ $t->id }}">{{ $t->name }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    <template x-if="importSel">
                        <div class="grid gap-5 lg:grid-cols-2 items-start">

                            {{-- Restaurer une sauvegarde existante --}}
                            <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-800">Restaurer une sauvegarde existante</h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5">Sauvegardes archivées de cet établissement (manuelles, automatiques ou importées).</p>
                                </div>
                                <div class="divide-y divide-slate-50 max-h-[420px] overflow-y-auto">
                                    <template x-for="b in importBackups" :key="b.id">
                                        <div class="px-5 py-3 flex items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="font-mono text-[10px] text-slate-600 truncate" x-text="b.filename"></p>
                                                <p class="text-[10px] text-slate-400 mt-0.5"><span x-text="b.at"></span> · <span x-text="b.size"></span> · <span x-text="b.trigger === 'scheduled' ? 'Auto' : (b.trigger === 'imported' ? 'Importé' : 'Manuel')"></span></p>
                                            </div>
                                            <form method="POST" :action="`{{ url('tech/backups') }}/${b.id}/restore`" class="shrink-0"
                                                  @submit="if (!confirm(`Restaurer ${b.filename} dans « ${tenantMap[importSel]?.name} » ?\n\nToutes les données actuelles de l'application seront REMPLACÉES par celles de cette sauvegarde. Cette action est irréversible.`)) $event.preventDefault(); else { const btn = $el.querySelector('button'); btn.disabled = true; btn.textContent = 'Restauration…'; }">
                                                @csrf
                                                <button type="submit" class="rounded-lg bg-amber-600 px-3 py-1.5 text-[10px] font-bold text-white hover:bg-amber-700 transition cursor-pointer disabled:opacity-60">
                                                    Restaurer
                                                </button>
                                            </form>
                                        </div>
                                    </template>
                                    <template x-if="importBackups.length === 0">
                                        <div class="px-5 py-8 text-center text-xs text-slate-400">Aucune sauvegarde archivée pour cet établissement — crée-en une depuis l'onglet Sauvegardes, ou importe un fichier ci-contre.</div>
                                    </template>
                                </div>
                            </div>

                            {{-- Importer un fichier depuis le PC --}}
                            <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-800">Importer un fichier de sauvegarde</h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5">Fichier .sql.gz (généré par cet outil) ou .sql — archivé puis restauré immédiatement.</p>
                                </div>
                                <form method="POST" :action="`{{ url('tech/backups') }}/${importSel}/import`" enctype="multipart/form-data" class="p-5 space-y-4"
                                      @submit="if (!confirm(`Importer ce fichier et restaurer « ${tenantMap[importSel]?.name} » ?\n\nToutes les données actuelles de l'application seront REMPLACÉES par le contenu du fichier. Cette action est irréversible.`)) $event.preventDefault(); else { const btn = $el.querySelector('button[type=submit]'); btn.disabled = true; btn.textContent = 'Import en cours…'; }">
                                    @csrf
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Fichier de sauvegarde</label>
                                        <input type="file" name="backup_file" required accept=".gz,.sql"
                                               class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700">
                                        <p class="text-[10px] text-slate-400 mt-1.5">500 Mo max. Le fichier est conservé dans les archives (badge « Importé ») pour pouvoir être restauré à nouveau plus tard.</p>
                                    </div>
                                    <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-amber-700 transition shadow-sm cursor-pointer disabled:opacity-60">
                                        Importer et restaurer
                                    </button>
                                </form>
                            </div>
                        </div>
                    </template>

                    <div x-show="!importSel" x-cloak class="rounded-lg border border-dashed border-slate-300 bg-slate-50/60 p-8 text-center text-xs text-slate-400">
                        Sélectionne un établissement pour afficher ses sauvegardes restaurables.
                    </div>
                </div>

                {{-- ============ PLANIFICATION ============ --}}
                <div x-show="tab === 'schedule'" x-cloak>
                    <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-4 mb-5 flex items-start gap-2">
                        <svg class="h-4 w-4 text-indigo-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <p class="text-[11px] text-indigo-950 leading-relaxed">Chaque établissement peut avoir sa propre planification. La commande <code class="font-mono bg-white/60 px-1 rounded">backups:run</code> est vérifiée chaque minute par le scheduler ; en production, une entrée cron <code class="font-mono bg-white/60 px-1 rounded">* * * * * php artisan schedule:run</code> (ou <code class="font-mono bg-white/60 px-1 rounded">schedule:work</code>) doit être active.</p>
                    </div>

                    <div class="space-y-4">
                        @foreach($tenants as $t)
                            @php $sched = \App\Models\BackupSchedule::where('tenant_id', $t->id)->first(); @endphp
                            <form method="POST" action="{{ route('tech.backups.schedule', $t) }}"
                                  class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm"
                                  x-data="{
                                    enabled: {{ $sched && $sched->enabled ? 'true' : 'false' }},
                                    frequency: '{{ $sched->frequency ?? 'daily' }}'
                                  }">
                                @csrf
                                <div class="flex items-center justify-between gap-3 mb-4">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-800">{{ $t->name }}</h3>
                                        @if($sched && $sched->enabled && $sched->next_run_at)
                                            <p class="text-[10px] text-emerald-600 mt-0.5">Prochaine : {{ $sched->next_run_at->format('d/m/Y à H:i') }}
                                                @if($sched->last_run_at) · dernière : {{ $sched->last_run_at->format('d/m H:i') }} @endif
                                            </p>
                                        @else
                                            <p class="text-[10px] text-slate-400 mt-0.5">Aucune sauvegarde automatique</p>
                                        @endif
                                    </div>
                                    <label class="flex items-center gap-2 cursor-pointer select-none shrink-0">
                                        <span class="text-[10px] font-bold uppercase tracking-wider" :class="enabled ? 'text-indigo-600' : 'text-slate-400'" x-text="enabled ? 'Activée' : 'Désactivée'"></span>
                                        <input type="checkbox" name="enabled" value="1" x-model="enabled" hidden>
                                        <div class="h-5 w-9 rounded-full transition-colors" :class="enabled ? 'bg-indigo-600' : 'bg-slate-200'">
                                            <div class="h-4 w-4 mt-0.5 rounded-full bg-white shadow transition-transform" :class="enabled ? 'translate-x-[18px]' : 'translate-x-0.5'"></div>
                                        </div>
                                    </label>
                                </div>

                                <div class="grid grid-cols-2 md:grid-cols-5 gap-3" x-show="enabled" x-cloak>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Fréquence</label>
                                        <select name="frequency" x-model="frequency" class="block w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500">
                                            <option value="daily">Quotidienne</option>
                                            <option value="weekly">Hebdomadaire</option>
                                            <option value="monthly">Mensuelle</option>
                                        </select>
                                    </div>
                                    <div x-show="frequency === 'weekly'">
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Jour</label>
                                        <select name="day_of_week" class="block w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500">
                                            @foreach(['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'] as $i => $d)
                                                <option value="{{ $i }}" @selected(($sched->day_of_week ?? 1) == $i)>{{ $d }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div x-show="frequency === 'monthly'">
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Jour du mois</label>
                                        <input type="number" name="day_of_month" min="1" max="28" value="{{ $sched->day_of_month ?? 1 }}" class="block w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Heure</label>
                                        <input type="number" name="hour" min="0" max="23" value="{{ $sched->hour ?? 2 }}" class="block w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Minute</label>
                                        <input type="number" name="minute" min="0" max="59" value="{{ $sched->minute ?? 0 }}" class="block w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Conserver</label>
                                        <input type="number" name="retention" min="1" max="365" value="{{ $sched->retention ?? 7 }}" class="block w-full rounded-lg border border-slate-200 bg-white px-2.5 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500" title="Nombre de sauvegardes conservées">
                                    </div>
                                </div>
                                {{-- Champs neutres pour enregistrer une planification désactivée sans erreur de validation --}}
                                <template x-if="!enabled">
                                    <div>
                                        <input type="hidden" name="frequency" :value="frequency">
                                        <input type="hidden" name="hour" value="{{ $sched->hour ?? 2 }}">
                                        <input type="hidden" name="minute" value="{{ $sched->minute ?? 0 }}">
                                        <input type="hidden" name="retention" value="{{ $sched->retention ?? 7 }}">
                                    </div>
                                </template>

                                <div class="flex justify-end mt-4">
                                    <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm cursor-pointer">Enregistrer</button>
                                </div>
                            </form>
                        @endforeach
                    </div>
                </div>

                {{-- ============ EXPORTS CSV (existant) ============ --}}
                <div x-show="tab === 'exports'" x-cloak>
                    <div class="bg-white rounded-lg border border-slate-200 p-6 shadow-sm">
                        <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                            <div class="rounded-full bg-indigo-50 p-2 text-indigo-600">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <div>
                                <h2 class="text-md font-bold text-slate-800 tracking-tight">Exportation des données métiers (CSV)</h2>
                                <p class="text-[11px] text-slate-500">Extractions consolidées au format CSV compatible Microsoft Excel (UTF-8 BOM, délimiteur « ; »).</p>
                            </div>
                        </div>
                        <div class="mt-6 space-y-4">
                            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-5 flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <h3 class="text-sm font-bold text-slate-800">Rapport de Supervision des Établissements</h3>
                                    <p class="text-xs text-slate-500 max-w-xl">Rapport consolidé de tous les établissements (contacts, devise, pays, utilisateurs, réservations).</p>
                                </div>
                                <a href="{{ route('tech.export.supervision') }}" class="shrink-0 inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-indigo-700 transition shadow-xs">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                    Exporter la Supervision
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>


        @elseif($activeTab === 'dashboard' && $isTech)
            {{-- ================= SUPERVISION MULTI-ÉTABLISSEMENTS ================= --}}
            <div class="mt-6"
                 x-data="{
                    loading: true,
                    data: null,
                    dot(s) {
                        if (s === 'running') return 'bg-emerald-500';
                        if (s === 'absent' || s === 'exited' || !s) return 'bg-slate-300';
                        return 'bg-red-500';
                    },
                    async refresh() {
                        this.loading = true;
                        try {
                            const r = await fetch('{{ route('tech.supervision.stats') }}', { headers: { 'Accept': 'application/json' } });
                            this.data = await r.json();
                        } catch (e) { this.data = null; }
                        this.loading = false;
                    }
                 }"
                 x-init="refresh(); setInterval(() => refresh(), 60000)">

                <div class="flex items-center justify-between gap-4 mb-5">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800 tracking-tight">Supervision multi-établissements</h2>
                        <p class="text-xs text-slate-500 mt-1">Vue globale des établissements, utilisateurs, activité et alertes — actualisée automatiquement toutes les 60 secondes.</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-[10px] text-slate-400 font-mono" x-show="data" x-text="data ? 'à ' + data.generated_at : ''"></span>
                        <button type="button" @click="refresh()" :disabled="loading"
                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition disabled:opacity-50 cursor-pointer">
                            <svg class="h-3.5 w-3.5" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Actualiser
                        </button>
                    </div>
                </div>

                {{-- Squelette de chargement (premier affichage) --}}
                <div x-show="loading && !data" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <template x-for="i in 4">
                        <div class="h-24 rounded-lg border border-slate-200 bg-white shadow-sm animate-pulse"></div>
                    </template>
                </div>

                <template x-if="data">
                    <div>
                        {{-- KPI cards --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Établissements actifs</p>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-3xl font-extrabold text-slate-800" x-text="data.counts.establishments_running"></span>
                                    <span class="text-xs text-slate-400">/ <span x-text="data.counts.establishments_total"></span> opérationnels</span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Utilisateurs actifs</p>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-3xl font-extrabold text-slate-800" x-text="data.counts.users_total"></span>
                                    <span class="text-xs text-slate-400">tous établissements</span>
                                </div>
                            </div>
                            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Réservations du jour</p>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-3xl font-extrabold text-slate-800" x-text="data.counts.bookings_today"></span>
                                    <span class="text-xs text-slate-400"><span x-text="data.counts.arrivals_today"></span> arrivée(s) prévue(s)</span>
                                </div>
                            </div>
                            <div class="rounded-lg border p-5 shadow-sm"
                                 :class="data.counts.alerts > 0 ? 'border-red-200 bg-red-50' : 'border-slate-200 bg-white'">
                                <p class="text-[10px] font-bold uppercase tracking-wider" :class="data.counts.alerts > 0 ? 'text-red-500' : 'text-slate-400'">Alertes globales</p>
                                <div class="mt-2 flex items-baseline gap-2">
                                    <span class="text-3xl font-extrabold" :class="data.counts.alerts > 0 ? 'text-red-700' : 'text-slate-800'" x-text="data.counts.alerts"></span>
                                    <span class="text-xs" :class="data.counts.alerts > 0 ? 'text-red-500' : 'text-slate-400'" x-text="data.counts.alerts > 0 ? 'à traiter' : 'tout est en ordre'"></span>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
                            {{-- Tableau des établissements --}}
                            <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-800">État des établissements</h3>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                                <th class="px-5 py-3">Établissement</th>
                                                <th class="px-3 py-3 text-center">App</th>
                                                <th class="px-3 py-3 text-center">Base</th>
                                                <th class="px-3 py-3 text-center">Site</th>
                                                <th class="px-3 py-3 text-right">Utilisateurs</th>
                                                <th class="px-3 py-3 text-right">Résa. jour</th>
                                                <th class="px-5 py-3 text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            <template x-for="e in data.establishments" :key="e.id">
                                                <tr class="hover:bg-slate-50/60 transition">
                                                    <td class="px-5 py-3">
                                                        <p class="font-bold text-slate-800" x-text="e.name"></p>
                                                        <p class="font-mono text-[10px] text-slate-400" x-text="e.slug"></p>
                                                    </td>
                                                    <td class="px-3 py-3 text-center">
                                                        <span class="inline-block h-2.5 w-2.5 rounded-full" :class="dot(e.app_status)" :title="'Application : ' + e.app_status"></span>
                                                    </td>
                                                    <td class="px-3 py-3 text-center">
                                                        <span class="inline-block h-2.5 w-2.5 rounded-full" :class="dot(e.db_status)" :title="'Base de données : ' + e.db_status"></span>
                                                    </td>
                                                    <td class="px-3 py-3 text-center">
                                                        <template x-if="e.has_website">
                                                            <span class="inline-block h-2.5 w-2.5 rounded-full" :class="dot(e.web_status)" :title="'Site vitrine : ' + (e.web_status ?? 'non provisionné')"></span>
                                                        </template>
                                                        <template x-if="!e.has_website">
                                                            <span class="text-slate-300">—</span>
                                                        </template>
                                                    </td>
                                                    <td class="px-3 py-3 text-right font-bold text-slate-700" x-text="e.users_count"></td>
                                                    <td class="px-3 py-3 text-right font-bold text-slate-700" x-text="e.bookings_today ?? '—'"></td>
                                                    <td class="px-5 py-3 text-right">
                                                        <a :href="e.url" class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">Fiche →</a>
                                                    </td>
                                                </tr>
                                            </template>
                                            <template x-if="data.establishments.length === 0">
                                                <tr><td colspan="7" class="px-5 py-8 text-center text-slate-400">Aucun établissement.</td></tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Alertes globales --}}
                            <aside class="rounded-lg border border-slate-200 bg-white shadow-sm self-start overflow-hidden">
                                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-slate-800">Alertes globales</h3>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                          :class="data.counts.alerts > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                          x-text="data.counts.alerts"></span>
                                </div>
                                <div class="divide-y divide-slate-50 max-h-[420px] overflow-y-auto">
                                    <template x-if="data.alerts.length === 0">
                                        <div class="px-5 py-8 text-center">
                                            <p class="text-xs font-bold text-emerald-600">✓ Aucune alerte</p>
                                            <p class="text-[10px] text-slate-400 mt-1">Tous les établissements sont opérationnels.</p>
                                        </div>
                                    </template>
                                    <template x-for="a in data.alerts">
                                        <div class="px-5 py-3 flex items-start gap-2.5">
                                            <span class="mt-1 h-2 w-2 rounded-full shrink-0" :class="a.level === 'critical' ? 'bg-red-500' : 'bg-amber-400'"></span>
                                            <div>
                                                <p class="text-[10px] font-bold uppercase tracking-wider" :class="a.level === 'critical' ? 'text-red-600' : 'text-amber-600'" x-text="a.tenant"></p>
                                                <p class="text-xs text-slate-600 leading-snug mt-0.5" x-text="a.message"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </aside>
                        </div>
                    </div>
                </template>

                {{-- Échec de chargement --}}
                <div x-show="!loading && !data" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-5 text-xs font-bold text-red-700">
                    Impossible de charger les statistiques de supervision — vérifie que le serveur répond, puis réessaie.
                </div>
            </div>
        @elseif($activeTab === 'dashboard' && $isOwner)
            {{-- ================= VUE D'ENSEMBLE 360° (BUSINESS) ================= --}}
            <div class="mt-2"
                 x-data="{
                    period: 'month',
                    loading: true,
                    data: null,
                    chart: null,
                    chartLoading: false,
                    periods: { today: 'Aujourd\'hui', week: 'Cette semaine', month: 'Ce mois', year: 'Cette année' },
                    fmt(cents) {
                        const v = Math.round((cents || 0) / 100);
                        return new Intl.NumberFormat('fr-FR').format(v);
                    },
                    async load() {
                        this.loading = true;
                        try {
                            const url = new URL('{{ route('business.overview.data') }}', window.location.origin);
                            url.searchParams.set('period', this.period);
                            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            this.data = await r.json();
                        } catch (e) { this.data = null; }
                        this.loading = false;
                        this.loadChart();
                    },
                    async loadChart() {
                        this.chartLoading = true;
                        try {
                            const url = new URL('{{ route('business.revenue.data') }}', window.location.origin);
                            url.searchParams.set('period', this.period);
                            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            this.chart = await r.json();
                        } catch (e) { this.chart = null; }
                        this.chartLoading = false;
                    },
                    setPeriod(p) { this.period = p; this.load(); },
                    trendClass(t) {
                        if (!t) return 'text-slate-400';
                        return t.direction === 'up' ? 'text-emerald-600' : (t.direction === 'down' ? 'text-red-600' : 'text-slate-400');
                    },
                    // Génère le tracé SVG (aire) d'une série de valeurs.
                    areaPath(values, w, h) {
                        if (!values || values.length === 0) return '';
                        const max = Math.max(...values, 1);
                        const stepX = values.length > 1 ? w / (values.length - 1) : 0;
                        let d = values.map((v, i) => `${i === 0 ? 'M' : 'L'} ${(i*stepX).toFixed(1)} ${(h - (v/max)*h).toFixed(1)}`).join(' ');
                        return `${d} L ${w.toFixed(1)} ${h} L 0 ${h} Z`;
                    },
                    linePath(values, w, h) {
                        if (!values || values.length === 0) return '';
                        const max = Math.max(...values, 1);
                        const stepX = values.length > 1 ? w / (values.length - 1) : 0;
                        return values.map((v, i) => `${i === 0 ? 'M' : 'L'} ${(i*stepX).toFixed(1)} ${(h - (v/max)*h).toFixed(1)}`).join(' ');
                    },
                    get chartTotals() {
                        if (!this.chart) return [];
                        const n = (this.chart.labels || []).length;
                        const out = [];
                        for (let i = 0; i < n; i++) {
                            out.push((this.chart.hotel[i]||0) + (this.chart.restaurant[i]||0) + (this.chart.shop[i]||0));
                        }
                        return out;
                    }
                 }" x-init="load()">

                {{-- En-tête + sélecteur de période --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Vue d'ensemble</h1>
                        <p class="text-xs text-slate-500 mt-1">Le pouls de votre business, tous établissements réunis — en direct.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5">
                            <template x-for="(label, key) in periods" :key="key">
                                <button type="button" @click="setPeriod(key)"
                                        class="px-3 py-1.5 text-xs font-semibold rounded-md transition"
                                        :class="period === key ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800'"
                                        x-text="label"></button>
                            </template>
                        </div>
                        <button type="button" @click="load()" :disabled="loading"
                                class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 transition disabled:opacity-50 cursor-pointer" title="Actualiser">
                            <svg class="h-4 w-4" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                        </button>
                    </div>
                </div>

                {{-- Chargement --}}
                <div x-show="loading && !data" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <template x-for="i in 4" :key="i"><div class="h-28 rounded-xl bg-slate-100 animate-pulse"></div></template>
                </div>

                <template x-if="data">
                    <div>
                        {{-- Bandeau établissements injoignables --}}
                        <template x-if="data.unreachable && data.unreachable.length > 0">
                            <div class="mb-5 rounded-lg bg-amber-50 border border-amber-200 px-4 py-2.5 text-xs text-amber-800 flex items-center gap-2">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                                <span>Données indisponibles pour : <span class="font-semibold" x-text="data.unreachable.join(', ')"></span>. Ces établissements sont peut-être hors ligne.</span>
                            </div>
                        </template>

                        {{-- KPI HÉROS --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            {{-- Revenu total --}}
                            <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-indigo-600 to-indigo-700 text-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-100">Revenu total</p>
                                    <svg class="h-5 w-5 text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <p class="text-3xl font-extrabold mt-2 leading-none"><span x-text="fmt(data.totals.revenue.total)"></span> <span class="text-sm font-semibold text-indigo-200" x-text="data.currency"></span></p>
                                <div class="mt-2 flex items-center gap-1.5 text-xs" x-show="data.revenue_trend">
                                    <template x-if="data.revenue_trend.direction === 'up'"><svg class="h-3.5 w-3.5 text-emerald-300" fill="currentColor" viewBox="0 0 20 20"><path d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z"/></svg></template>
                                    <template x-if="data.revenue_trend.direction === 'down'"><svg class="h-3.5 w-3.5 text-red-300" fill="currentColor" viewBox="0 0 20 20"><path d="M12 13a1 1 0 100 2h5a1 1 0 001-1v-5a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586 3.707 5.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z"/></svg></template>
                                    <span class="font-semibold text-indigo-100"><span x-text="data.revenue_trend.pct"></span>% vs période précédente</span>
                                </div>
                            </div>

                            {{-- Occupation --}}
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Taux d'occupation</p>
                                    <div class="rounded-lg bg-emerald-50 p-1.5"><svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" /></svg></div>
                                </div>
                                <p class="text-3xl font-extrabold text-slate-800 mt-2 leading-none"><span x-text="data.occupancy_rate"></span><span class="text-lg text-slate-400">%</span></p>
                                <p class="text-[11px] text-slate-400 mt-2"><span x-text="data.totals.rooms_occupied"></span> / <span x-text="data.totals.rooms_total"></span> chambres occupées</p>
                            </div>

                            {{-- Réservations --}}
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Réservations</p>
                                    <div class="rounded-lg bg-amber-50 p-1.5"><svg class="h-4 w-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg></div>
                                </div>
                                <p class="text-3xl font-extrabold text-slate-800 mt-2 leading-none" x-text="data.totals.bookings"></p>
                                <p class="text-[11px] text-slate-400 mt-2">sur la période</p>
                            </div>

                            {{-- Personnel --}}
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Personnel</p>
                                    <div class="rounded-lg bg-violet-50 p-1.5"><svg class="h-4 w-4 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg></div>
                                </div>
                                <p class="text-3xl font-extrabold text-slate-800 mt-2 leading-none" x-text="data.totals.staff"></p>
                                <p class="text-[11px] text-slate-400 mt-2"><span x-text="data.count_reachable"></span> / <span x-text="data.count_total"></span> établissement(s) en ligne</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
                            {{-- Graphe d'évolution du revenu --}}
                            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-800">Évolution du chiffre d'affaires</h3>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Tous établissements et pôles confondus</p>
                                    </div>
                                    <div class="flex items-center gap-3 text-[10px] text-slate-500">
                                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-indigo-500"></span>Hôtel</span>
                                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Restaurant</span>
                                        <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Boutique</span>
                                    </div>
                                </div>
                                <div class="p-5">
                                    <template x-if="chart && chartTotals.length > 0 && chartTotals.some(v => v > 0)">
                                        <div>
                                            <svg viewBox="0 0 600 180" class="w-full h-44" preserveAspectRatio="none">
                                                <defs>
                                                    <linearGradient id="revGrad" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="0%" stop-color="#6366f1" stop-opacity="0.25"/>
                                                        <stop offset="100%" stop-color="#6366f1" stop-opacity="0"/>
                                                    </linearGradient>
                                                </defs>
                                                <path :d="areaPath(chartTotals, 600, 180)" fill="url(#revGrad)"></path>
                                                <path :d="linePath(chartTotals, 600, 180)" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linejoin="round"></path>
                                            </svg>
                                            <div class="flex justify-between text-[9px] text-slate-400 mt-1.5">
                                                <span x-text="chart.labels[0] ?? ''"></span>
                                                <span x-text="chart.labels[Math.floor(chart.labels.length/2)] ?? ''"></span>
                                                <span x-text="chart.labels[chart.labels.length-1] ?? ''"></span>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="chart && (chartTotals.length === 0 || !chartTotals.some(v => v > 0))">
                                        <div class="h-44 flex items-center justify-center text-xs text-slate-400">Aucun revenu enregistré sur cette période.</div>
                                    </template>
                                    <div x-show="chartLoading && !chart" class="h-44 rounded-lg bg-slate-50 animate-pulse"></div>
                                </div>
                            </div>

                            {{-- Répartition par pôle --}}
                            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-800">Répartition du revenu</h3>
                                </div>
                                <div class="p-5 space-y-4">
                                    @php
                                        $poles = [
                                            ['key' => 'hotel', 'label' => 'Hôtel', 'color' => 'bg-indigo-500'],
                                            ['key' => 'restaurant', 'label' => 'Restaurant', 'color' => 'bg-amber-500'],
                                            ['key' => 'shop', 'label' => 'Boutique', 'color' => 'bg-emerald-500'],
                                        ];
                                    @endphp
                                    @foreach($poles as $pole)
                                        <div>
                                            <div class="flex items-center justify-between text-xs mb-1">
                                                <span class="font-semibold text-slate-600">{{ $pole['label'] }}</span>
                                                <span class="font-bold text-slate-800"><span x-text="fmt(data.totals.revenue.{{ $pole['key'] }})"></span> <span class="text-[10px] text-slate-400" x-text="data.currency"></span></span>
                                            </div>
                                            <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                                                <div class="h-full {{ $pole['color'] }} rounded-full transition-all"
                                                     :style="`width: ${data.totals.revenue.total > 0 ? (data.totals.revenue.{{ $pole['key'] }} / data.totals.revenue.total * 100) : 0}%`"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                    {{-- Écart de caisse consolidé --}}
                                    <div class="pt-3 border-t border-slate-100">
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="font-semibold text-slate-600">Écart de caisse</span>
                                            <span class="font-bold" :class="data.totals.cash_discrepancy < 0 ? 'text-red-600' : (data.totals.cash_discrepancy > 0 ? 'text-amber-600' : 'text-emerald-600')">
                                                <span x-text="fmt(data.totals.cash_discrepancy)"></span> <span class="text-[10px]" x-text="data.currency"></span>
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 mt-1">Différence déclaré vs théorique, toutes caisses</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                            {{-- Classement des établissements --}}
                            <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-800">Performance par établissement</h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5">Classés par revenu — repérez d'un coup d'œil qui performe et qui décroche</p>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                                <th class="px-5 py-3">Établissement</th>
                                                <th class="px-3 py-3 text-right">Revenu</th>
                                                <th class="px-3 py-3 text-right">Tendance</th>
                                                <th class="px-3 py-3 text-right">Occup.</th>
                                                <th class="px-3 py-3 text-center">Alertes</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            <template x-for="e in data.establishments" :key="e.id">
                                                <tr class="hover:bg-slate-50/60 transition">
                                                    <td class="px-5 py-3">
                                                        <span class="font-bold text-slate-800" x-text="e.name"></span>
                                                        <template x-if="!e.reachable"><span class="ml-2 text-[9px] font-bold px-1.5 py-0.5 rounded bg-red-50 text-red-600 border border-red-200 uppercase">Hors ligne</span></template>
                                                    </td>
                                                    <td class="px-3 py-3 text-right font-bold text-slate-800">
                                                        <template x-if="e.reachable"><span><span x-text="fmt(e.revenue)"></span></span></template>
                                                        <template x-if="!e.reachable"><span class="text-slate-300">—</span></template>
                                                    </td>
                                                    <td class="px-3 py-3 text-right">
                                                        <template x-if="e.reachable && e.revenue_trend">
                                                            <span class="inline-flex items-center gap-0.5 font-semibold" :class="trendClass(e.revenue_trend)">
                                                                <span x-text="e.revenue_trend.direction === 'up' ? '▲' : (e.revenue_trend.direction === 'down' ? '▼' : '–')"></span>
                                                                <span x-text="e.revenue_trend.pct + '%'"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="!e.reachable"><span class="text-slate-300">—</span></template>
                                                    </td>
                                                    <td class="px-3 py-3 text-right text-slate-600">
                                                        <template x-if="e.reachable"><span x-text="e.occupancy + '%'"></span></template>
                                                        <template x-if="!e.reachable"><span class="text-slate-300">—</span></template>
                                                    </td>
                                                    <td class="px-3 py-3 text-center">
                                                        <template x-if="e.reachable && e.alerts > 0"><span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-red-100 text-red-700 text-[10px] font-bold" x-text="e.alerts"></span></template>
                                                        <template x-if="e.reachable && e.alerts === 0"><span class="text-emerald-500">✓</span></template>
                                                        <template x-if="!e.reachable"><span class="text-slate-300">—</span></template>
                                                    </td>
                                                </tr>
                                            </template>
                                            <template x-if="data.establishments.length === 0">
                                                <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Aucun établissement provisionné.</td></tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {{-- Centre d'alertes --}}
                            <div class="rounded-xl border shadow-sm overflow-hidden"
                                 :class="data.alerts.length > 0 ? 'border-red-200 bg-white' : 'border-slate-200 bg-white'">
                                <div class="px-5 py-4 border-b flex items-center gap-2"
                                     :class="data.alerts.length > 0 ? 'border-red-100 bg-red-50/50' : 'border-slate-100'">
                                    <svg class="h-4 w-4" :class="data.alerts.length > 0 ? 'text-red-600' : 'text-emerald-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                                    <h3 class="text-sm font-bold text-slate-800">Alertes</h3>
                                    <span class="ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full"
                                          :class="data.alerts.length > 0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'"
                                          x-text="data.alerts.length"></span>
                                </div>
                                <div class="max-h-[360px] overflow-y-auto divide-y divide-slate-50">
                                    <template x-for="(a, idx) in data.alerts" :key="idx">
                                        <div class="px-5 py-3">
                                            <div class="flex items-start gap-2">
                                                <span class="mt-0.5 h-2 w-2 rounded-full shrink-0" :class="a.severity === 'high' ? 'bg-red-500' : (a.severity === 'medium' ? 'bg-amber-500' : 'bg-slate-300')"></span>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-semibold text-slate-800" x-text="a.title"></p>
                                                    <p class="text-[11px] text-slate-500 leading-snug mt-0.5" x-text="a.message"></p>
                                                    <p class="text-[10px] text-slate-400 mt-1"><span class="font-semibold" x-text="a.establishment"></span> · <span x-text="a.at"></span></p>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <template x-if="data.alerts.length === 0">
                                        <div class="px-5 py-10 text-center">
                                            <svg class="h-8 w-8 text-emerald-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                            <p class="text-xs text-slate-500 font-medium">Tout est sous contrôle</p>
                                            <p class="text-[10px] text-slate-400 mt-0.5">Aucune anomalie détectée sur la période.</p>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <p class="text-[10px] text-slate-400 mt-4 text-right">Dernière actualisation : <span x-text="data.generated_at"></span></p>
                    </div>
                </template>

                {{-- Échec --}}
                <div x-show="!loading && !data" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-5 text-xs font-bold text-red-700">
                    Impossible de charger votre tableau de bord. Réessayez dans un instant.
                </div>
            </div>

        @elseif($activeTab === 'analytics' && $isOwner)
            {{-- ================= STATISTIQUES (COMPARAISON & TENDANCES) ================= --}}
            <div class="mt-2"
                 x-data="{
                    period: 'month', loading: true, data: null,
                    sortBy: 'revenue',
                    periods: { today:'Aujourd\'hui', week:'Semaine', month:'Mois', year:'Année' },
                    palette: ['#6366f1','#f59e0b','#10b981','#ef4444','#0ea5e9','#8b5cf6','#f97316','#14b8a6'],
                    fmt(c) { return new Intl.NumberFormat('fr-FR').format(Math.round((c||0)/100)); },
                    async load() {
                        this.loading = true;
                        try {
                            const url = new URL('{{ route('business.stats.data') }}', window.location.origin);
                            url.searchParams.set('period', this.period);
                            const r = await fetch(url, { headers: { 'Accept':'application/json' } });
                            this.data = await r.json();
                        } catch(e) { this.data = null; }
                        this.loading = false;
                    },
                    color(i) { return this.palette[i % this.palette.length]; },
                    get sorted() {
                        if (!this.data) return [];
                        const arr = [...this.data.establishments];
                        arr.sort((a,b) => (b[this.sortBy]||0) - (a[this.sortBy]||0));
                        return arr;
                    },
                    get maxRevenue() {
                        if (!this.data) return 1;
                        return Math.max(...this.data.establishments.map(e => e.revenue), 1);
                    },
                    // Multi-courbes : normalise toutes les séries sur le même max global.
                    get chartMax() {
                        if (!this.data) return 1;
                        let m = 1;
                        this.data.establishments.forEach(e => (e.series||[]).forEach(v => { if (v > m) m = v; }));
                        return m;
                    },
                    linePath(vals, w, h, max) {
                        if (!vals || !vals.length) return '';
                        const sx = vals.length>1 ? w/(vals.length-1) : 0;
                        return vals.map((v,i)=>`${i===0?'M':'L'} ${(i*sx).toFixed(1)} ${(h-(v/max)*h).toFixed(1)}`).join(' ');
                    }
                 }" x-init="load()">

                {{-- En-tête --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Statistiques</h1>
                        <p class="text-xs text-slate-500 mt-1">Comparez vos établissements, comprenez vos forces et repérez ce qui décroche.</p>
                    </div>
                    <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5">
                        <template x-for="(label,key) in periods" :key="key">
                            <button type="button" @click="period=key; load()" class="px-3 py-1.5 text-xs font-semibold rounded-md transition" :class="period===key ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800'" x-text="label"></button>
                        </template>
                    </div>
                </div>

                <div x-show="loading && !data" class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6"><template x-for="i in 4" :key="i"><div class="h-24 rounded-xl bg-slate-100 animate-pulse"></div></template></div>

                <template x-if="data && data.count === 0">
                    <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400">
                        Aucun établissement en ligne pour établir des statistiques.
                        <template x-if="data.unreachable && data.unreachable.length"><p class="text-xs text-amber-600 mt-2">Hors ligne : <span x-text="data.unreachable.join(', ')"></span></p></template>
                    </div>
                </template>

                <template x-if="data && data.count > 0">
                    <div>
                        {{-- KPI analytiques dérivés --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Revenu moyen / établissement</p>
                                <p class="text-2xl font-extrabold text-slate-800 mt-2 leading-none"><span x-text="fmt(data.derived.avg_revenue)"></span> <span class="text-xs text-slate-400" x-text="data.currency"></span></p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Panier moyen</p>
                                <p class="text-2xl font-extrabold text-slate-800 mt-2 leading-none"><span x-text="fmt(data.derived.avg_basket)"></span> <span class="text-xs text-slate-400" x-text="data.currency"></span></p>
                                <p class="text-[11px] text-slate-400 mt-2">par réservation</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Occupation moyenne</p>
                                <p class="text-2xl font-extrabold text-slate-800 mt-2 leading-none"><span x-text="data.derived.avg_occupancy"></span>%</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Revenu par chambre</p>
                                <p class="text-2xl font-extrabold text-slate-800 mt-2 leading-none"><span x-text="fmt(data.derived.revenue_per_room)"></span> <span class="text-xs text-slate-400" x-text="data.currency"></span></p>
                                <p class="text-[11px] text-slate-400 mt-2">tous établissements</p>
                            </div>
                        </div>

                        {{-- Top & Flop --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <template x-if="data.rankings.top">
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">🏆 Meilleur revenu</p>
                                    <p class="text-sm font-bold text-slate-800 mt-1.5" x-text="data.rankings.top.name"></p>
                                    <p class="text-xs text-slate-500"><span x-text="fmt(data.rankings.top.revenue)"></span> <span x-text="data.currency"></span></p>
                                </div>
                            </template>
                            <template x-if="data.rankings.flop">
                                <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-amber-700">⚠ Revenu le plus faible</p>
                                    <p class="text-sm font-bold text-slate-800 mt-1.5" x-text="data.rankings.flop.name"></p>
                                    <p class="text-xs text-slate-500"><span x-text="fmt(data.rankings.flop.revenue)"></span> <span x-text="data.currency"></span></p>
                                </div>
                            </template>
                            <template x-if="data.rankings.top_riser">
                                <div class="rounded-xl border border-emerald-200 bg-white p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-600">▲ Plus forte hausse</p>
                                    <p class="text-sm font-bold text-slate-800 mt-1.5" x-text="data.rankings.top_riser.name"></p>
                                    <p class="text-xs font-semibold text-emerald-600" x-text="'+' + data.rankings.top_riser.trend_raw + '%'"></p>
                                </div>
                            </template>
                            <template x-if="data.rankings.top_faller && data.rankings.top_faller.trend_raw < 0">
                                <div class="rounded-xl border border-red-200 bg-white p-4">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-red-600">▼ Plus forte baisse</p>
                                    <p class="text-sm font-bold text-slate-800 mt-1.5" x-text="data.rankings.top_faller.name"></p>
                                    <p class="text-xs font-semibold text-red-600" x-text="data.rankings.top_faller.trend_raw + '%'"></p>
                                    <p class="text-[10px] text-red-500/70 mt-0.5">À investiguer avec le directeur</p>
                                </div>
                            </template>
                        </div>

                        {{-- Évolution comparée (multi-courbes) --}}
                        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-6">
                            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">Évolution comparée du chiffre d'affaires</h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5">Une courbe par établissement — visualisez les trajectoires</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-3 text-[10px]">
                                    <template x-for="(e,i) in data.establishments" :key="e.id">
                                        <span class="flex items-center gap-1 text-slate-500"><span class="h-2 w-2 rounded-full" :style="`background:${color(i)}`"></span><span x-text="e.name"></span></span>
                                    </template>
                                </div>
                            </div>
                            <div class="p-5">
                                <template x-if="data.labels && data.labels.length">
                                    <div>
                                        <svg viewBox="0 0 600 200" class="w-full h-52" preserveAspectRatio="none">
                                            <template x-for="(e,i) in data.establishments" :key="e.id">
                                                <path :d="linePath(e.series, 600, 200, chartMax)" fill="none" :stroke="color(i)" stroke-width="2" stroke-linejoin="round" opacity="0.9"></path>
                                            </template>
                                        </svg>
                                        <div class="flex justify-between text-[9px] text-slate-400 mt-1.5">
                                            <span x-text="data.labels[0] ?? ''"></span>
                                            <span x-text="data.labels[Math.floor(data.labels.length/2)] ?? ''"></span>
                                            <span x-text="data.labels[data.labels.length-1] ?? ''"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!data.labels || !data.labels.length"><div class="h-52 flex items-center justify-center text-xs text-slate-400">Pas de données d'évolution sur cette période.</div></template>
                            </div>
                        </div>

                        {{-- Comparaison détaillée --}}
                        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-6">
                            <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">Comparaison des établissements</h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5">Cliquez une colonne pour trier</p>
                                </div>
                                <div class="flex items-center gap-1 text-[10px] text-slate-500">
                                    <span>Trier :</span>
                                    <button @click="sortBy='revenue'" class="px-2 py-1 rounded font-semibold" :class="sortBy==='revenue'?'bg-indigo-100 text-indigo-700':'hover:bg-slate-100'">Revenu</button>
                                    <button @click="sortBy='occupancy'" class="px-2 py-1 rounded font-semibold" :class="sortBy==='occupancy'?'bg-indigo-100 text-indigo-700':'hover:bg-slate-100'">Occupation</button>
                                    <button @click="sortBy='revenue_per_room'" class="px-2 py-1 rounded font-semibold" :class="sortBy==='revenue_per_room'?'bg-indigo-100 text-indigo-700':'hover:bg-slate-100'">Rev/chambre</button>
                                </div>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                            <th class="px-5 py-3">Établissement</th>
                                            <th class="px-3 py-3">Revenu</th>
                                            <th class="px-3 py-3 text-right">Tendance</th>
                                            <th class="px-3 py-3 text-right">Occup.</th>
                                            <th class="px-3 py-3 text-right">Résa.</th>
                                            <th class="px-3 py-3 text-right">Panier</th>
                                            <th class="px-5 py-3 text-right">Rev/chambre</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <template x-for="e in sorted" :key="e.id">
                                            <tr class="hover:bg-slate-50/60 transition">
                                                <td class="px-5 py-3 font-bold text-slate-800 whitespace-nowrap" x-text="e.name"></td>
                                                <td class="px-3 py-3 min-w-[160px]">
                                                    <div class="flex items-center gap-2">
                                                        <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                                                            <div class="h-full bg-indigo-500 rounded-full" :style="`width:${(e.revenue/maxRevenue*100)}%`"></div>
                                                        </div>
                                                        <span class="font-bold text-slate-800 whitespace-nowrap" x-text="fmt(e.revenue)"></span>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-3 text-right">
                                                    <span class="font-semibold" :class="e.trend.direction==='up'?'text-emerald-600':(e.trend.direction==='down'?'text-red-600':'text-slate-400')">
                                                        <span x-text="e.trend.direction==='up'?'▲':(e.trend.direction==='down'?'▼':'–')"></span><span x-text="e.trend.pct+'%'"></span>
                                                    </span>
                                                </td>
                                                <td class="px-3 py-3 text-right text-slate-600" x-text="e.occupancy+'%'"></td>
                                                <td class="px-3 py-3 text-right text-slate-600" x-text="e.bookings"></td>
                                                <td class="px-3 py-3 text-right text-slate-600" x-text="fmt(e.avg_booking)"></td>
                                                <td class="px-5 py-3 text-right font-semibold text-slate-700" x-text="fmt(e.revenue_per_room)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Mix de revenus par établissement --}}
                        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-800">Mix de revenus</h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5">D'où vient l'argent de chaque établissement</p>
                                </div>
                                <div class="flex items-center gap-3 text-[10px] text-slate-500">
                                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-indigo-500"></span>Hôtel</span>
                                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Resto</span>
                                    <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Boutique</span>
                                </div>
                            </div>
                            <div class="p-5 space-y-4">
                                <template x-for="e in data.establishments" :key="e.id">
                                    <div>
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="font-semibold text-slate-700" x-text="e.name"></span>
                                            <span class="text-slate-400"><span x-text="fmt(e.revenue)"></span> <span x-text="data.currency"></span></span>
                                        </div>
                                        <div class="flex h-3 rounded-full overflow-hidden bg-slate-100">
                                            <div class="bg-indigo-500 h-full" :style="`width:${e.revenue>0?(e.by_pole.hotel/e.revenue*100):0}%`"></div>
                                            <div class="bg-amber-500 h-full" :style="`width:${e.revenue>0?(e.by_pole.restaurant/e.revenue*100):0}%`"></div>
                                            <div class="bg-emerald-500 h-full" :style="`width:${e.revenue>0?(e.by_pole.shop/e.revenue*100):0}%`"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <p class="text-[10px] text-slate-400 mt-4 text-right">Dernière actualisation : <span x-text="data.generated_at"></span></p>
                    </div>
                </template>

                <div x-show="!loading && !data" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-5 text-xs font-bold text-red-700">Impossible de charger les statistiques. Réessayez.</div>
            </div>

        @elseif($activeTab === 'employees' && $isOwner)
            {{-- ================= EMPLOYÉS (CONSOLIDÉ) ================= --}}
            <div class="mt-2"
                 x-data="{
                    loading: true, data: null, filter: '', search: '',
                    async load() {
                        this.loading = true;
                        try {
                            const url = new URL('{{ route('business.employees.data') }}', window.location.origin);
                            if (this.filter) url.searchParams.set('slug', this.filter);
                            const r = await fetch(url, { headers: { 'Accept':'application/json' } });
                            this.data = await r.json();
                        } catch(e) { this.data = null; }
                        this.loading = false;
                    },
                    get filtered() {
                        if (!this.data) return [];
                        if (!this.search) return this.data.employees;
                        const q = this.search.toLowerCase();
                        return this.data.employees.filter(e =>
                            (e.name||'').toLowerCase().includes(q) ||
                            (e.email||'').toLowerCase().includes(q) ||
                            (e.role||'').toLowerCase().includes(q) ||
                            (e.establishment||'').toLowerCase().includes(q));
                    },
                    roleColor(role) {
                        const r = (role||'').toLowerCase();
                        if (r.includes('manager') || r.includes('admin')) return 'bg-indigo-50 text-indigo-700 border-indigo-200';
                        if (r.includes('reception')) return 'bg-sky-50 text-sky-700 border-sky-200';
                        if (r.includes('restaurant')) return 'bg-amber-50 text-amber-700 border-amber-200';
                        if (r.includes('shop') || r.includes('cash')) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
                        if (r.includes('housekeep')) return 'bg-violet-50 text-violet-700 border-violet-200';
                        return 'bg-slate-100 text-slate-600 border-slate-200';
                    }
                 }" x-init="load()">

                {{-- En-tête --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Employés</h1>
                        <p class="text-xs text-slate-500 mt-1">Tout le personnel de vos établissements, réuni en un seul registre.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <select x-model="filter" @change="load()"
                                class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500">
                            <option value="">Tous les établissements</option>
                            @foreach($tenants as $t)
                                @if($t->provisioned_at)
                                    <option value="{{ $t->slug }}">{{ $t->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <button type="button" @click="load()" :disabled="loading"
                                class="inline-flex items-center justify-center h-9 w-9 rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 transition disabled:opacity-50 cursor-pointer" title="Actualiser">
                            <svg class="h-4 w-4" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                        </button>
                    </div>
                </div>

                <div x-show="loading && !data" class="rounded-xl bg-slate-100 h-64 animate-pulse"></div>

                <template x-if="data">
                    <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        {{-- Barre : total + recherche --}}
                        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-xs text-slate-500">
                                <span class="font-bold text-slate-800" x-text="filtered.length"></span>
                                <span>employé(s)</span>
                                <template x-if="filter"><span class="text-slate-400">· filtré</span></template>
                            </div>
                            <div class="relative">
                                <svg class="h-4 w-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
                                <input type="text" x-model="search" placeholder="Rechercher (nom, email, poste…)"
                                       class="w-full sm:w-72 rounded-lg border border-slate-200 bg-white pl-9 pr-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                        </div>

                        {{-- Bandeau injoignables --}}
                        <template x-if="data.unreachable && data.unreachable.length > 0">
                            <div class="px-5 py-2 bg-amber-50 border-b border-amber-100 text-[10px] text-amber-700">
                                Établissements injoignables (employés non listés) : <span class="font-semibold" x-text="data.unreachable.join(', ')"></span>
                            </div>
                        </template>

                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                        <th class="px-5 py-3">Nom</th>
                                        <th class="px-3 py-3">Email</th>
                                        <th class="px-3 py-3">Numéro</th>
                                        <th class="px-3 py-3">Poste</th>
                                        <th class="px-3 py-3">Établissement</th>
                                        <th class="px-5 py-3 text-right">Créé le</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <template x-for="(e, i) in filtered" :key="i">
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="px-5 py-3">
                                                <div class="flex items-center gap-2.5">
                                                    <span class="h-7 w-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center text-[10px] font-bold shrink-0"
                                                          x-text="(e.name||'?').trim().split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase()"></span>
                                                    <div>
                                                        <span class="font-bold text-slate-800" x-text="e.name"></span>
                                                        <template x-if="!e.is_active"><span class="ml-1.5 text-[9px] font-bold px-1.5 py-0.5 rounded bg-red-50 text-red-600 border border-red-200 uppercase">Inactif</span></template>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-3 py-3 text-slate-600" x-text="e.email"></td>
                                            <td class="px-3 py-3 text-slate-500" x-text="e.phone"></td>
                                            <td class="px-3 py-3">
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded border" :class="roleColor(e.role)" x-text="e.role"></span>
                                            </td>
                                            <td class="px-3 py-3 font-semibold text-slate-700" x-text="e.establishment"></td>
                                            <td class="px-5 py-3 text-right text-slate-500 whitespace-nowrap" x-text="e.created_at"></td>
                                        </tr>
                                    </template>
                                    <template x-if="filtered.length === 0">
                                        <tr><td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                            <span x-text="search ? 'Aucun employé ne correspond à votre recherche.' : 'Aucun employé — ou établissements momentanément injoignables.'"></span>
                                        </td></tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>

                <div x-show="!loading && !data" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-5 text-xs font-bold text-red-700">Impossible de charger la liste des employés. Réessayez.</div>
            </div>

        @elseif($activeTab === 'revenue' && $isOwner)
            {{-- ================= RAPPORT & AUDIT FINANCIER ================= --}}
            <div class="mt-2"
                 x-data="{
                    period: 'month', loading: true, data: null,
                    periods: { today:'Aujourd\'hui', week:'Semaine', month:'Mois', year:'Année' },
                    fmt(c) { return new Intl.NumberFormat('fr-FR').format(Math.round((c||0)/100)); },
                    async load() {
                        this.loading = true;
                        try {
                            const url = new URL('{{ route('business.report.data') }}', window.location.origin);
                            url.searchParams.set('period', this.period);
                            const r = await fetch(url, { headers: { 'Accept':'application/json' } });
                            this.data = await r.json();
                        } catch(e) { this.data = null; }
                        this.loading = false;
                    },
                    exportUrl(fmt) {
                        const u = new URL('{{ url('business/report/export') }}/' + fmt, window.location.origin);
                        u.searchParams.set('period', this.period);
                        return u.toString();
                    },
                    methodLabel(m) { return (m||'').replace('_',' ').replace(/\b\w/g, c => c.toUpperCase()); },
                    get methodMax() { return this.data ? Math.max(...this.data.payment_methods.map(m=>m.total), 1) : 1; }
                 }" x-init="load()">

                {{-- En-tête + période + exports --}}
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Rapport financier</h1>
                        <p class="text-xs text-slate-500 mt-1">Encaissements, dépenses, audit de caisse — et export de rapports complets.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5">
                            <template x-for="(label,key) in periods" :key="key">
                                <button type="button" @click="period=key; load()" class="px-3 py-1.5 text-xs font-semibold rounded-md transition" :class="period===key ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800'" x-text="label"></button>
                            </template>
                        </div>
                        <a :href="exportUrl('excel')" class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3.5 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6m-9 4.5V6.75A2.25 2.25 0 015.25 4.5h13.5A2.25 2.25 0 0121 6.75v10.5A2.25 2.25 0 0118.75 19.5H5.25A2.25 2.25 0 013 17.25z" /></svg>
                            Excel
                        </a>
                        <a :href="exportUrl('pdf')" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2 text-xs font-bold text-red-700 hover:bg-red-100 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            PDF
                        </a>
                    </div>
                </div>

                <div x-show="loading && !data" class="grid grid-cols-1 sm:grid-cols-4 gap-4"><template x-for="i in 4" :key="i"><div class="h-24 rounded-xl bg-slate-100 animate-pulse"></div></template></div>

                <template x-if="data && data.count === 0">
                    <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-400">
                        Aucun établissement en ligne pour établir un rapport.
                        <template x-if="data.unreachable && data.unreachable.length"><p class="text-xs text-amber-600 mt-2">Hors ligne : <span x-text="data.unreachable.join(', ')"></span></p></template>
                    </div>
                </template>

                <template x-if="data && data.count > 0">
                    <div>
                        {{-- KPI financiers --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Revenu</p>
                                <p class="text-2xl font-extrabold text-slate-800 mt-2 leading-none"><span x-text="fmt(data.totals.revenue.total)"></span> <span class="text-xs text-slate-400" x-text="data.currency"></span></p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Encaissé</p>
                                <p class="text-2xl font-extrabold text-emerald-600 mt-2 leading-none"><span x-text="fmt(data.totals.collected)"></span> <span class="text-xs text-slate-400" x-text="data.currency"></span></p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Dépenses</p>
                                <p class="text-2xl font-extrabold text-amber-600 mt-2 leading-none"><span x-text="fmt(data.totals.expenses)"></span> <span class="text-xs text-slate-400" x-text="data.currency"></span></p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-indigo-600 to-indigo-700 text-white p-5 shadow-sm">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-100">Résultat net</p>
                                <p class="text-2xl font-extrabold mt-2 leading-none"><span x-text="fmt(data.totals.net)"></span> <span class="text-xs text-indigo-200" x-text="data.currency"></span></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
                            {{-- Facturation & créances + audit caisse --}}
                            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-slate-100"><h3 class="text-sm font-bold text-slate-800">Facturation & audit</h3></div>
                                <div class="p-5 space-y-3 text-sm">
                                    <div class="flex items-center justify-between"><span class="text-slate-500">Facturé</span><span class="font-bold text-slate-800"><span x-text="fmt(data.totals.invoiced)"></span> <span class="text-[10px] text-slate-400" x-text="data.currency"></span></span></div>
                                    <div class="flex items-center justify-between"><span class="text-slate-500">Payé</span><span class="font-bold text-emerald-600"><span x-text="fmt(data.totals.paid)"></span> <span class="text-[10px] text-slate-400" x-text="data.currency"></span></span></div>
                                    <div class="flex items-center justify-between border-b border-slate-100 pb-3"><span class="text-slate-500">Créances (dû)</span><span class="font-bold" :class="data.totals.due > 0 ? 'text-red-600':'text-slate-800'"><span x-text="fmt(data.totals.due)"></span> <span class="text-[10px] text-slate-400" x-text="data.currency"></span></span></div>
                                    <div class="flex items-center justify-between pt-1">
                                        <span class="text-slate-500">Écart de caisse (déclaré vs théorique)</span>
                                        <span class="font-bold" :class="data.totals.cash_discrepancy < 0 ? 'text-red-600' : (data.totals.cash_discrepancy > 0 ? 'text-amber-600' : 'text-emerald-600')"><span x-text="fmt(data.totals.cash_discrepancy)"></span> <span class="text-[10px] text-slate-400" x-text="data.currency"></span></span>
                                    </div>
                                </div>
                            </div>

                            {{-- Méthodes de paiement --}}
                            <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-5 py-4 border-b border-slate-100"><h3 class="text-sm font-bold text-slate-800">Encaissements par méthode</h3></div>
                                <div class="p-5 space-y-3">
                                    <template x-for="m in data.payment_methods" :key="m.method">
                                        <div>
                                            <div class="flex items-center justify-between text-xs mb-1">
                                                <span class="font-semibold text-slate-700" x-text="methodLabel(m.method) + ' (' + m.count + ')'"></span>
                                                <span class="font-bold text-slate-800"><span x-text="fmt(m.total)"></span> <span class="text-[10px] text-slate-400" x-text="data.currency"></span></span>
                                            </div>
                                            <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-indigo-500 rounded-full" :style="`width:${(m.total/methodMax*100)}%`"></div></div>
                                        </div>
                                    </template>
                                    <template x-if="data.payment_methods.length === 0"><p class="text-xs text-slate-400">Aucun encaissement sur la période.</p></template>
                                </div>
                            </div>
                        </div>

                        {{-- Détail par établissement --}}
                        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden mb-6">
                            <div class="px-5 py-4 border-b border-slate-100"><h3 class="text-sm font-bold text-slate-800">Détail par établissement</h3></div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                            <th class="px-5 py-3">Établissement</th><th class="px-3 py-3 text-right">Revenu</th><th class="px-3 py-3 text-right">Encaissé</th><th class="px-3 py-3 text-right">Dû</th><th class="px-3 py-3 text-right">Dépenses</th><th class="px-3 py-3 text-right">Écart caisse</th><th class="px-5 py-3 text-right">Net</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <template x-for="e in data.establishments" :key="e.slug">
                                            <tr class="hover:bg-slate-50/60 transition">
                                                <td class="px-5 py-3 font-bold text-slate-800" x-text="e.name"></td>
                                                <td class="px-3 py-3 text-right text-slate-700" x-text="fmt(e.revenue)"></td>
                                                <td class="px-3 py-3 text-right text-emerald-600" x-text="fmt(e.collected)"></td>
                                                <td class="px-3 py-3 text-right" :class="e.due > 0 ? 'text-red-600':'text-slate-500'" x-text="fmt(e.due)"></td>
                                                <td class="px-3 py-3 text-right text-amber-600" x-text="fmt(e.expenses)"></td>
                                                <td class="px-3 py-3 text-right" :class="e.cash_gap < 0 ? 'text-red-600':'text-slate-500'" x-text="fmt(e.cash_gap)"></td>
                                                <td class="px-5 py-3 text-right font-bold text-slate-800" x-text="fmt(e.net)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Dépenses --}}
                        <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                                <h3 class="text-sm font-bold text-slate-800">Dépenses / décaissements</h3>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600" x-text="data.expenses.length + ' opération(s)'"></span>
                            </div>
                            <div class="max-h-[360px] overflow-y-auto">
                                <template x-if="data.expenses.length > 0">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                                <th class="px-5 py-3">Montant</th><th class="px-3 py-3">Motif</th><th class="px-3 py-3">Par</th><th class="px-3 py-3">Établissement</th><th class="px-5 py-3 text-right">Date</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-50">
                                            <template x-for="(e,i) in data.expenses" :key="i">
                                                <tr class="hover:bg-slate-50/60 transition">
                                                    <td class="px-5 py-2.5 font-bold text-amber-700 whitespace-nowrap"><span x-text="fmt(e.amount)"></span> <span class="text-[10px] text-slate-400" x-text="data.currency"></span></td>
                                                    <td class="px-3 py-2.5 text-slate-600" x-text="e.reason"></td>
                                                    <td class="px-3 py-2.5 text-slate-500" x-text="e.user"></td>
                                                    <td class="px-3 py-2.5 font-semibold text-slate-700" x-text="e.establishment"></td>
                                                    <td class="px-5 py-2.5 text-right text-slate-400 whitespace-nowrap" x-text="e.at"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </template>
                                <template x-if="data.expenses.length === 0"><div class="px-5 py-10 text-center text-xs text-slate-400">Aucune dépense enregistrée sur la période.</div></template>
                            </div>
                        </div>

                        <p class="text-[10px] text-slate-400 mt-4 text-right">Rapport généré le <span x-text="data.generated_at"></span></p>
                    </div>
                </template>

                <div x-show="!loading && !data" x-cloak class="rounded-lg border border-red-200 bg-red-50 p-5 text-xs font-bold text-red-700">Impossible de charger le rapport. Réessayez.</div>
            </div>

        @elseif($activeTab === 'roles' && $isTech)
            {{-- ================= RÔLES & PERMISSIONS ================= --}}
            @php
                $roleCatalog     = \App\Support\TenantRoles::catalog();
                $moduleColumns   = \App\Support\TenantRoles::moduleColumns();
                $rolePermissions = \App\Support\TenantRoles::permissions();
                $moduleBadges = [
                    'core' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    'restaurant' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'shop' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'housekeeping' => 'bg-sky-50 text-sky-700 border-sky-200',
                    'accounting' => 'bg-violet-50 text-violet-700 border-violet-200',
                ];
                $platformRoles = [
                    'tech_admin' => ['label' => 'Administrateur technique', 'description' => 'Provisioning, supervision, modules, CMS et cycle de vie des établissements.', 'count' => \App\Models\User::where('role', \App\Models\User::ROLE_TECH_ADMIN)->count()],
                    'owner' => ['label' => 'Propriétaire (business)', 'description' => 'Console business consolidée de ses établissements — pas d\'accès technique.', 'count' => \App\Models\User::where('role', \App\Models\User::ROLE_OWNER)->count()],
                ];
            @endphp
            <div class="mt-6 space-y-8">
                <div>
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Rôles et permissions</h2>
                    <p class="text-xs text-slate-500 mt-1">Consultation des rôles de la plateforme et des rôles opérationnels de l'application établissement — pms documente ces rôles et suit leur répartition, ils sont attribués dans chaque application.</p>
                </div>

                {{-- Rôles plateforme (pms) --}}
                <div>
                    <h3 class="text-sm font-bold text-slate-800 mb-3">Rôles plateforme (pms)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($platformRoles as $key => $role)
                            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-bold text-slate-800">{{ $role['label'] }}</p>
                                    <p class="font-mono text-[10px] text-slate-400 mt-0.5">{{ $key }}</p>
                                    <p class="text-xs text-slate-500 mt-2 leading-relaxed">{{ $role['description'] }}</p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-2xl font-extrabold text-slate-800">{{ $role['count'] }}</p>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-wider">compte(s)</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Rôles opérationnels (application établissement) --}}
                <div>
                    <h3 class="text-sm font-bold text-slate-800 mb-3">Rôles opérationnels (application établissement)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($roleCatalog as $key => $role)
                            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-xs font-bold text-slate-800">{{ $role['label'] }}</p>
                                    <span class="shrink-0 text-[9px] font-bold px-1.5 py-0.5 rounded border uppercase tracking-wider {{ $moduleBadges[$role['module']] ?? 'bg-slate-50 text-slate-500 border-slate-200' }}">
                                        {{ $moduleColumns[$role['module']] ?? $role['module'] }}
                                    </span>
                                </div>
                                <p class="font-mono text-[10px] text-slate-400 mt-0.5">{{ $key }}</p>
                                <p class="text-[11px] text-slate-500 mt-2 leading-relaxed">{{ $role['description'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Permissions par module (matrice consultative) --}}
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h3 class="text-sm font-bold text-slate-800">Permissions par module</h3>
                        <p class="text-[10px] text-slate-400 mt-0.5">Accès de chaque rôle aux modules de l'application (déduit des règles d'accès du template). Un module désactivé pour un établissement reste inaccessible quel que soit le rôle.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                    <th class="px-5 py-3">Rôle</th>
                                    @foreach($moduleColumns as $moduleKey => $moduleLabel)
                                        <th class="px-3 py-3 text-center whitespace-nowrap">{{ $moduleLabel }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                @foreach($roleCatalog as $key => $role)
                                    <tr class="hover:bg-slate-50/60 transition">
                                        <td class="px-5 py-2.5">
                                            <span class="font-bold text-slate-700">{{ $role['label'] }}</span>
                                        </td>
                                        @foreach($moduleColumns as $moduleKey => $moduleLabel)
                                            <td class="px-3 py-2.5 text-center">
                                                @if(in_array($moduleKey, $rolePermissions[$key] ?? [], true))
                                                    <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500" title="Accès"></span>
                                                @else
                                                    <span class="text-slate-200">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Répartition par établissement (live, bases tenants) --}}
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden"
                     x-data="{
                        loading: true,
                        data: null,
                        async refresh() {
                            this.loading = true;
                            try {
                                const r = await fetch('{{ route('tech.roles.distribution') }}', { headers: { 'Accept': 'application/json' } });
                                this.data = await r.json();
                            } catch (e) { this.data = null; }
                            this.loading = false;
                        }
                     }" x-init="refresh()">
                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">Rôles par établissement</h3>
                            <p class="text-[10px] text-slate-400 mt-0.5">Répartition en direct, lue dans la base de chaque établissement joignable.</p>
                        </div>
                        <button type="button" @click="refresh()" :disabled="loading"
                                class="shrink-0 inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition disabled:opacity-50 cursor-pointer">
                            <svg class="h-3.5 w-3.5" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            Actualiser
                        </button>
                    </div>

                    <div x-show="loading && !data" class="p-5">
                        <div class="h-16 rounded-md bg-slate-50 animate-pulse"></div>
                    </div>

                    <template x-if="data">
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                        <th class="px-5 py-3">Établissement</th>
                                        @foreach($roleCatalog as $key => $role)
                                            <th class="px-2 py-3 text-center font-mono normal-case" title="{{ $role['label'] }}">{{ $key }}</th>
                                        @endforeach
                                        <th class="px-3 py-3 text-right">Total</th>
                                        <th class="px-5 py-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <template x-for="e in data.establishments" :key="e.id">
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="px-5 py-3">
                                                <p class="font-bold text-slate-800" x-text="e.name"></p>
                                                <template x-if="!e.reachable">
                                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-red-50 text-red-600 border border-red-200 uppercase">Base injoignable</span>
                                                </template>
                                            </td>
                                            @foreach($roleCatalog as $key => $role)
                                                <td class="px-2 py-3 text-center">
                                                    <span class="font-bold" :class="(e.roles['{{ $key }}'] ?? 0) > 0 ? 'text-slate-800' : 'text-slate-200'" x-text="e.roles['{{ $key }}'] ?? '·'"></span>
                                                </td>
                                            @endforeach
                                            <td class="px-3 py-3 text-right font-extrabold text-slate-800" x-text="e.reachable ? e.total : '—'"></td>
                                            <td class="px-5 py-3 text-right">
                                                <a :href="e.url" class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline">Utilisateurs →</a>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>

                    <div x-show="!loading && !data" x-cloak class="p-5 text-xs font-bold text-red-700 bg-red-50 border-t border-red-100">
                        Impossible de charger la répartition des rôles.
                    </div>
                </div>

                {{-- Rôles personnalisés (à venir) --}}
                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/60 p-5">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-500">Rôles personnalisés</h3>
                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-slate-200 text-slate-500 uppercase tracking-wider">À venir</span>
                    </div>
                    <p class="text-xs text-slate-400 mt-1.5 leading-relaxed">Création de rôles sur mesure par établissement (permissions à la carte) — prévu avec le futur système de permissions par module, une fois la matrice ci-dessus rendue configurable.</p>
                </div>
            </div>
        @elseif($activeTab === 'support' && $isTech)
            {{-- ================= SUPPORT OPÉRATIONNEL ================= --}}
            <div class="mt-6"
                 x-data="{
                    sub: 'read',
                    selected: '',
                    diag: null,
                    diagLoading: false,
                    interventions: null,
                    intLoading: false,
                    intFilter: '',
                    appLogs: null,
                    appLogsLoading: false,
                    appLogsFilter: '',
                    appLogsUnreachable: [],
                    async loadDiag() {
                        if (!this.selected) { this.diag = null; return; }
                        this.diagLoading = true;
                        try {
                            const r = await fetch(`{{ url('tech/support') }}/${this.selected}/diagnostic`, { headers: { 'Accept': 'application/json' } });
                            this.diag = await r.json();
                        } catch (e) { this.diag = null; }
                        this.diagLoading = false;
                    },
                    async loadAppLogs() {
                        this.appLogsLoading = true;
                        try {
                            const url = new URL('{{ route('tech.support.app-logs') }}', window.location.origin);
                            if (this.appLogsFilter) url.searchParams.set('slug', this.appLogsFilter);
                            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            const d = await r.json();
                            this.appLogs = d.logs;
                            this.appLogsUnreachable = d.unreachable ?? [];
                        } catch (e) { this.appLogs = null; }
                        this.appLogsLoading = false;
                    },
                    async loadInterventions() {
                        this.intLoading = true;
                        try {
                            const url = new URL('{{ route('tech.support.interventions') }}', window.location.origin);
                            if (this.intFilter) url.searchParams.set('slug', this.intFilter);
                            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            this.interventions = (await r.json()).interventions;
                        } catch (e) { this.interventions = null; }
                        this.intLoading = false;
                    },
                    dotClass(s) {
                        if (s === 'running') return 'bg-emerald-500';
                        if (!s || s === 'absent' || s === 'exited') return 'bg-slate-300';
                        return 'bg-red-500';
                    }
                 }"
                 x-init="$watch('sub', v => {
                    if (v === 'history' && interventions === null) loadInterventions();
                    if (v === 'logs' && appLogs === null) loadAppLogs();
                 })">

                <div class="mb-5">
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">Support opérationnel</h2>
                    <p class="text-xs text-slate-500 mt-1">Diagnostic en lecture seule des établissements et journal des interventions. Toute consultation est elle-même auditée.</p>
                </div>

                {{-- Sous-onglets internes --}}
                <div class="flex flex-wrap gap-1 border-b border-slate-200 mb-6">
                    @php
                        $supportSubs = [
                            'read' => 'Mode lecture',
                            'logs' => 'Logs applicatifs',
                            'assist' => 'Mode assistance',
                            'justification' => 'Justification',
                            'history' => 'Historique interventions',
                        ];
                    @endphp
                    @foreach($supportSubs as $subKey => $subLabel)
                        <button type="button" @click="sub = '{{ $subKey }}'"
                                class="px-4 py-2.5 text-xs font-bold transition border-b-2 -mb-px cursor-pointer"
                                :class="sub === '{{ $subKey }}' ? 'text-indigo-700 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-800'">
                            {{ $subLabel }}
                        </button>
                    @endforeach
                </div>

                {{-- ---- Mode lecture ---- --}}
                <div x-show="sub === 'read'">
                    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm mb-5">
                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Établissement à diagnostiquer</label>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <select x-model="selected" @change="loadDiag()"
                                    class="flex-1 rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                <option value="">— Choisir un établissement —</option>
                                @foreach($tenants as $t)
                                    <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->slug }})</option>
                                @endforeach
                            </select>
                            <button type="button" @click="loadDiag()" :disabled="!selected || diagLoading"
                                    class="shrink-0 inline-flex items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition disabled:opacity-50 cursor-pointer">
                                <svg class="h-3.5 w-3.5" :class="diagLoading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                Diagnostiquer
                            </button>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2">Lecture seule — aucune donnée de l'établissement n'est modifiée. La consultation est enregistrée dans le journal d'audit.</p>
                    </div>

                    <div x-show="diagLoading" class="rounded-lg border border-slate-200 bg-white p-8 text-center text-xs text-slate-400">Diagnostic en cours…</div>

                    <template x-if="diag && !diagLoading">
                        <div class="grid gap-5 lg:grid-cols-2">
                            {{-- Infrastructure --}}
                            <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
                                    <h3 class="text-sm font-bold text-slate-800">Infrastructure</h3>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                          :class="diag.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'"
                                          x-text="diag.is_active ? 'Actif' : 'Inactif'"></span>
                                </div>
                                <div class="p-5 space-y-3 text-xs">
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Application</span>
                                        <span class="flex items-center gap-1.5 font-semibold text-slate-700"><span class="h-2 w-2 rounded-full" :class="dotClass(diag.app_status)"></span><span x-text="diag.app_status"></span></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">Base de données</span>
                                        <span class="flex items-center gap-1.5 font-semibold text-slate-700"><span class="h-2 w-2 rounded-full" :class="dotClass(diag.db_status)"></span><span x-text="diag.db_status"></span></span>
                                    </div>
                                    <div class="flex items-center justify-between" x-show="diag.has_website">
                                        <span class="text-slate-500">Site vitrine</span>
                                        <span class="flex items-center gap-1.5 font-semibold text-slate-700"><span class="h-2 w-2 rounded-full" :class="dotClass(diag.web_status)"></span><span x-text="diag.web_status ?? 'non provisionné'"></span></span>
                                    </div>
                                    <div class="flex items-center justify-between border-t border-slate-100 pt-3">
                                        <span class="text-slate-500">Provisionné le</span>
                                        <span class="font-semibold text-slate-700" x-text="diag.provisioned_at ?? '—'"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-slate-500">URL locale</span>
                                        <template x-if="diag.app_url"><a :href="diag.app_url" target="_blank" class="font-mono text-indigo-600 hover:underline" x-text="diag.app_url"></a></template>
                                        <template x-if="!diag.app_url"><span class="text-slate-400">—</span></template>
                                    </div>
                                    <div>
                                        <span class="text-slate-500 block mb-1.5">Modules actifs</span>
                                        <div class="flex flex-wrap gap-1.5">
                                            <template x-for="m in diag.modules" :key="m">
                                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-100" x-text="m"></span>
                                            </template>
                                            <template x-if="diag.modules.length === 0"><span class="text-[10px] text-slate-400">Aucun module optionnel</span></template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Données applicatives --}}
                            <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                                <div class="px-5 py-3 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-800">Données applicatives</h3>
                                </div>
                                <template x-if="diag.reachable">
                                    <div class="p-5 grid grid-cols-2 gap-4 text-center">
                                        <div class="rounded-lg bg-slate-50 border border-slate-100 py-3">
                                            <p class="text-2xl font-extrabold text-slate-800" x-text="diag.users"></p>
                                            <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Utilisateurs</p>
                                        </div>
                                        <div class="rounded-lg bg-slate-50 border border-slate-100 py-3">
                                            <p class="text-2xl font-extrabold text-slate-800" x-text="diag.active_users"></p>
                                            <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Comptes actifs</p>
                                        </div>
                                        <div class="rounded-lg bg-slate-50 border border-slate-100 py-3">
                                            <p class="text-2xl font-extrabold text-slate-800" x-text="diag.bookings_total"></p>
                                            <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Réservations</p>
                                        </div>
                                        <div class="rounded-lg bg-slate-50 border border-slate-100 py-3">
                                            <p class="text-2xl font-extrabold text-slate-800" x-text="diag.bookings_today"></p>
                                            <p class="text-[10px] text-slate-400 uppercase tracking-wider mt-0.5">Résa. aujourd'hui</p>
                                        </div>
                                        <div class="col-span-2 text-left border-t border-slate-100 pt-3 text-xs">
                                            <span class="text-slate-500">Dernière réservation : </span>
                                            <span class="font-semibold text-slate-700" x-text="diag.last_booking ?? 'aucune'"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="!diag.reachable">
                                    <div class="p-8 text-center">
                                        <p class="text-xs font-bold text-red-600">Base de données injoignable</p>
                                        <p class="text-[10px] text-slate-400 mt-1">Le container de base de données doit être démarré pour lire les données applicatives.</p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div x-show="!diag && !diagLoading" x-cloak class="rounded-lg border border-dashed border-slate-300 bg-slate-50/60 p-8 text-center text-xs text-slate-400">
                        Sélectionne un établissement pour afficher son diagnostic.
                    </div>
                </div>

                {{-- ---- Logs applicatifs (audit_logs de chaque établissement) ---- --}}
                <div x-show="sub === 'logs'" x-cloak>
                    <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">Logs applicatifs des établissements</h3>
                                <p class="text-[10px] text-slate-400 mt-0.5">Connexions et actions réalisées à l'intérieur de chaque application (utilisateur concerné, module, horodatage). Lecture directe du journal de chaque établissement.</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <select x-model="appLogsFilter" @change="loadAppLogs()"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500">
                                    <option value="">Tous les établissements</option>
                                    @foreach($tenants as $t)
                                        <option value="{{ $t->slug }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" @click="loadAppLogs()" :disabled="appLogsLoading"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition disabled:opacity-50 cursor-pointer">
                                    <svg class="h-3.5 w-3.5" :class="appLogsLoading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                    Actualiser
                                </button>
                            </div>
                        </div>

                        {{-- Bandeau bases injoignables --}}
                        <template x-if="appLogsUnreachable.length > 0">
                            <div class="px-5 py-2 bg-amber-50 border-b border-amber-100 text-[10px] text-amber-700">
                                Bases injoignables (logs non lus) : <span class="font-semibold" x-text="appLogsUnreachable.join(', ')"></span>
                            </div>
                        </template>

                        <div x-show="appLogsLoading" class="p-8 text-center text-xs text-slate-400">Lecture des journaux…</div>

                        <template x-if="appLogs && !appLogsLoading">
                            <div class="overflow-x-auto">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                            <th class="px-5 py-3">Quand</th>
                                            <th class="px-3 py-3">Établissement</th>
                                            <th class="px-3 py-3">Utilisateur</th>
                                            <th class="px-3 py-3">Événement</th>
                                            <th class="px-3 py-3">Action</th>
                                            <th class="px-5 py-3">IP</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        <template x-for="log in appLogs" :key="log.slug + '-' + log.event_type + '-' + log.ts + '-' + log.action">
                                            <tr class="hover:bg-slate-50/60 transition align-top">
                                                <td class="px-5 py-2.5 whitespace-nowrap">
                                                    <p class="font-semibold text-slate-700" x-text="log.at"></p>
                                                    <p class="text-[10px] text-slate-400" x-text="log.ago"></p>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <span class="font-semibold text-slate-700" x-text="log.tenant"></span>
                                                    <p class="font-mono text-[10px] text-slate-400" x-text="log.slug"></p>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <span class="font-semibold text-slate-700" x-text="log.user"></span>
                                                    <p class="text-[10px] text-slate-400" x-text="log.role"></p>
                                                </td>
                                                <td class="px-3 py-2.5">
                                                    <span class="text-[9px] font-bold px-2 py-0.5 rounded uppercase tracking-wider whitespace-nowrap"
                                                          :class="log.event_type === 'failed_login' ? 'bg-red-50 text-red-600 border border-red-200' : (log.event_type === 'login' || log.event_type === 'logout' ? 'bg-sky-50 text-sky-600 border border-sky-200' : 'bg-slate-100 text-slate-600 border border-slate-200')"
                                                          x-text="log.event_type"></span>
                                                    <p class="text-[10px] text-slate-400 mt-1" x-text="log.module"></p>
                                                </td>
                                                <td class="px-3 py-2.5 text-slate-600 max-w-xs" x-text="log.action"></td>
                                                <td class="px-5 py-2.5 font-mono text-[10px] text-slate-400 whitespace-nowrap" x-text="log.ip ?? '—'"></td>
                                            </tr>
                                        </template>
                                        <template x-if="appLogs.length === 0">
                                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-400">Aucun log applicatif pour ce filtre.</td></tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>

                        <div x-show="!appLogsLoading && !appLogs" x-cloak class="p-5 text-xs font-bold text-red-700 bg-red-50">
                            Impossible de charger les logs applicatifs.
                        </div>
                    </div>
                </div>

                {{-- ---- Mode assistance : sessions ouvertes / passées ---- --}}
                <div x-show="sub === 'assist'" x-cloak
                     x-data="{
                        sessions: null,
                        loading: false,
                        async load() {
                            this.loading = true;
                            try {
                                const r = await fetch('{{ route('tech.support.assistance.list') }}', { headers: { 'Accept': 'application/json' } });
                                this.sessions = (await r.json()).sessions;
                            } catch (e) { this.sessions = null; }
                            this.loading = false;
                        },
                        badge(s) {
                            if (s === 'active') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
                            if (s === 'revoked') return 'bg-red-50 text-red-600 border-red-200';
                            if (s === 'expired') return 'bg-slate-100 text-slate-500 border-slate-200';
                            return 'bg-slate-100 text-slate-500 border-slate-200';
                        },
                        copy(url) { navigator.clipboard.writeText(url); }
                     }"
                     x-init="load(); $watch('sub', v => { if (v === 'assist') load(); })">

                    <div class="flex items-center justify-between mb-4">
                        <p class="text-xs text-slate-500 max-w-2xl leading-relaxed">Sessions d'assistance ouvertes via l'onglet Justification. Chaque session fournit un lien d'accès signé et temporaire vers l'application de l'établissement — à ouvrir dans un onglet privé. Toute action y est auditée.</p>
                        <button type="button" @click="load()" :disabled="loading"
                                class="shrink-0 inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition disabled:opacity-50 cursor-pointer">
                            <svg class="h-3.5 w-3.5" :class="loading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                            Actualiser
                        </button>
                    </div>

                    <div x-show="loading && !sessions" class="rounded-lg border border-slate-200 bg-white p-8 text-center text-xs text-slate-400">Chargement…</div>

                    <template x-if="sessions">
                        <div class="space-y-3">
                            <template x-for="s in sessions" :key="s.id">
                                <div class="rounded-lg border bg-white p-4 shadow-sm"
                                     :class="s.live ? 'border-emerald-200' : 'border-slate-200'">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm font-bold text-slate-800" x-text="s.tenant"></span>
                                                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border uppercase tracking-wider" :class="badge(s.status)" x-text="s.status"></span>
                                            </div>
                                            <p class="text-xs text-slate-600 mt-1.5 leading-relaxed" x-text="s.reason"></p>
                                            <p class="text-[10px] text-slate-400 mt-1.5">
                                                Par <span class="font-semibold" x-text="s.admin"></span>
                                                · ouverte le <span x-text="s.opened_at"></span>
                                                <template x-if="s.live"> · <span class="text-emerald-600 font-semibold">expire <span x-text="s.expires_in"></span></span></template>
                                                <template x-if="!s.live"> · expirait le <span x-text="s.expires_at"></span></template>
                                            </p>
                                        </div>
                                        <template x-if="s.live">
                                            <div class="flex flex-col items-end gap-2 shrink-0">
                                                <a :href="s.entry_url" target="_blank" rel="noopener"
                                                   class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                                                    Entrer dans l'assistance
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                                </a>
                                                <div class="flex items-center gap-2">
                                                    <button type="button" @click="copy(s.entry_url)" class="text-[10px] font-semibold text-slate-500 hover:text-slate-800 cursor-pointer">Copier le lien</button>
                                                    <form method="POST" :action="`{{ url('tech/support/assistance') }}/${s.id}/revoke`">
                                                        @csrf
                                                        <button type="submit" class="text-[10px] font-semibold text-red-500 hover:text-red-700 cursor-pointer">Clôturer</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                            <template x-if="sessions.length === 0">
                                <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/60 p-8 text-center text-xs text-slate-400">
                                    Aucune session d'assistance. Ouvre-en une depuis l'onglet Justification.
                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- ---- Justification : ouvre une session d'assistance ---- --}}
                <div x-show="sub === 'justification'" x-cloak>
                    <form method="POST" action="{{ route('tech.support.assistance.open') }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm max-w-2xl">
                        @csrf
                        <h3 class="text-sm font-bold text-slate-800">Justifier et ouvrir une intervention</h3>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Documente le motif avant d'accéder à un établissement. La justification est obligatoire, jointe à l'audit, et ouvre une <strong>session d'assistance</strong> à durée limitée (visible dans l'onglet Mode assistance).</p>
                        <div class="mt-5 space-y-4">
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Établissement concerné</label>
                                <select name="tenant_id" required
                                        class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                    <option value="">— Choisir —</option>
                                    @foreach($tenants as $t)
                                        <option value="{{ $t->id }}" @selected(old('tenant_id') == $t->id)>{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Motif de l'intervention <span class="text-slate-400 normal-case font-normal">(10 caractères min.)</span></label>
                                <textarea name="reason" rows="4" required minlength="10" maxlength="1000" placeholder="Ex : reproduction d'un bug signalé sur la création de réservation…"
                                          class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">{{ old('reason') }}</textarea>
                            </div>
                            <div class="flex items-center gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2">
                                <svg class="h-4 w-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg>
                                <p class="text-[10px] text-amber-700">L'ouverture d'une session est auditée. L'accès expire automatiquement au bout de {{ config('assistance.ttl_minutes', 30) }} minutes.</p>
                            </div>
                            <button type="submit"
                                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm cursor-pointer">
                                Ouvrir la session d'assistance
                            </button>
                        </div>
                    </form>
                </div>

                {{-- ---- Historique interventions ---- --}}
                <div x-show="sub === 'history'" x-cloak>
                    <div class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">Historique des interventions</h3>
                                <p class="text-[10px] text-slate-400 mt-0.5">Actions sensibles effectuées par les administrateurs (80 plus récentes).</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <select x-model="intFilter" @change="loadInterventions()"
                                        class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500">
                                    <option value="">Tous les établissements</option>
                                    @foreach($tenants as $t)
                                        <option value="{{ $t->slug }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                                <button type="button" @click="loadInterventions()" :disabled="intLoading"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition disabled:opacity-50 cursor-pointer">
                                    <svg class="h-3.5 w-3.5" :class="intLoading ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                                    Actualiser
                                </button>
                            </div>
                        </div>

                        <div x-show="intLoading" class="p-8 text-center text-xs text-slate-400">Chargement…</div>

                        <template x-if="interventions && !intLoading">
                            <div class="divide-y divide-slate-50 max-h-[520px] overflow-y-auto">
                                <template x-for="item in interventions" :key="item.id">
                                    <div class="px-5 py-3 flex items-start gap-3 hover:bg-slate-50/60 transition">
                                        <span class="mt-0.5 text-[9px] font-bold px-2 py-0.5 rounded uppercase tracking-wider shrink-0 whitespace-nowrap"
                                              :class="item.event_type.includes('error') || item.event_type.includes('delete') || item.event_type.includes('stop') ? 'bg-red-50 text-red-600 border border-red-200' : (item.event_type === 'support_read' ? 'bg-slate-100 text-slate-500 border border-slate-200' : 'bg-indigo-50 text-indigo-600 border border-indigo-200')"
                                              x-text="item.event_type"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs text-slate-700 leading-snug" x-text="item.description"></p>
                                            <p class="text-[10px] text-slate-400 mt-0.5">
                                                <span class="font-semibold" x-text="item.actor"></span>
                                                · <span x-text="item.at"></span>
                                                · <span x-text="item.ago"></span>
                                            </p>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="interventions.length === 0">
                                    <div class="px-5 py-10 text-center text-xs text-slate-400">Aucune intervention enregistrée pour ce filtre.</div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @elseif($activeTab !== 'audit')
            <!-- Placeholder Layout for other tabs -->
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] mt-6">
                <div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-bold tracking-wider text-slate-400 uppercase">{{ $active['label'] }}</p>
                    <h2 class="mt-2 text-xl font-bold text-slate-800 tracking-tight">{{ $active['title'] }}</h2>
                    <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ $active['description'] }}</p>
                    
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        @foreach($active['items'] as $item)
                            <div class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3">
                                <p class="text-sm font-semibold text-slate-800">{{ $item }}</p>
                                <p class="mt-1 text-xs text-slate-500">Service backend Ã  connecter prochainement.</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                
                <aside class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm self-start">
                    <p class="text-sm font-bold text-slate-800 font-heading">État du module</p>
                    <div class="mt-4 space-y-3 text-xs">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <span class="text-slate-500">Interface</span>
                            <span class="font-bold bg-green-50 text-green-700 px-2 py-0.5 rounded border border-green-200">Prête</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
                            <span class="text-slate-500">Backend</span>
                            <span class="font-bold text-slate-400 bg-slate-50 px-2 py-0.5 rounded border border-slate-200">À développer</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500">Accès</span>
                            <span class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">Administrateur</span>
                        </div>
                    </div>
                </aside>
            </div>
        @else
            <!-- ================= AUDIT & SECURITE LAYOUT ================= -->
            
            <!-- Breadcrumb Path -->
            <p class="text-[10px] font-bold tracking-widest text-indigo-600 uppercase">AUDIT</p>
            
            <!-- Page Title and Subtitle Row -->
            <div class="mt-2 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-2 border-b border-slate-200 pb-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">Audit & Sécurité</h1>
                    <p class="text-xs text-slate-500 mt-1">Suivi des connexions, accès refusés, actions sensibles et interventions admin.</p>
                </div>
                <span class="text-[10px] text-slate-400 font-semibold uppercase tracking-wider block sm:text-right">
                    Dernière mise Ã  jour : {{ now()->translatedFormat('d F Y â€“ H:i') }}
                </span>
            </div>

            <!-- 5 Stats Cards Grid -->
            @if(isset($auditStats))
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mt-6">
                    <!-- Total logs -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4">
                        <div>
                            <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">TOTAL DES LOGS</p>
                            <p class="mt-2 text-3xl font-extrabold text-slate-800">{{ $auditStats['total_logs'] }}</p>
                        </div>
                        <div class="opacity-40">
                            <svg class="h-7 w-7 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                        </div>
                    </div>

                    <!-- Accès refusés -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4 border-red-500">
                        <div>
                            <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">ACCÈS REFUSÉS</p>
                            <p class="mt-2 text-3xl font-extrabold text-red-600">{{ $auditStats['access_denied'] }}</p>
                        </div>
                        <div class="opacity-40">
                            <svg class="h-7 w-7 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>
                    </div>

                    <!-- Échecs connexion -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4 border-emerald-500">
                        <div>
                            <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">ÉCHECS CONNEXION</p>
                            <p class="mt-2 text-3xl font-extrabold text-emerald-600">{{ $auditStats['failed_logins'] }}</p>
                        </div>
                        <div class="opacity-40">
                            <svg class="h-7 w-7 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Total comptes -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4 border-indigo-500">
                        <div>
                            <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">TOTAL COMPTES</p>
                            <p class="mt-2 text-3xl font-extrabold text-indigo-600">{{ $auditStats['total_users'] }}</p>
                        </div>
                        <div class="opacity-40">
                            <svg class="h-7 w-7 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>

                    <!-- Comptes désactivés -->
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 flex justify-between items-center relative overflow-hidden border-t-4 border-amber-500">
                        <div>
                            <p class="text-[9px] font-bold tracking-wider text-slate-400 uppercase">COMPTES DÉSACTIVÉS</p>
                            <p class="mt-2 text-3xl font-extrabold text-amber-600">{{ $auditStats['inactive_users'] }}</p>
                        </div>
                        <div class="opacity-40">
                            <svg class="h-7 w-7 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Legend Banner -->
            <div class="mt-6 bg-white rounded-lg border border-slate-200 px-4 py-3 text-xs text-slate-600 flex flex-wrap items-center gap-x-6 gap-y-2.5 shadow-sm">
                <span class="font-extrabold tracking-wider uppercase text-slate-400 text-[10px]">LÉGENDE :</span>
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-green-500 shadow-sm"></span>
                    <span>Succès / Connexion réussie</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-red-500 shadow-sm"></span>
                    <span>Danger / Accès refusé</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-orange-500 shadow-sm"></span>
                    <span>Avertissement / Action sensible</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-blue-500 shadow-sm"></span>
                    <span>Info / Déconnexion</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full bg-slate-500 shadow-sm"></span>
                    <span>Neutre / Système</span>
                </span>
            </div>

            <!-- Sub-Tabs Navigation -->
            <div class="mt-8 border-b border-slate-200">
                <div class="flex gap-6">
                    <a 
                        href="{{ route('tech.dashboard', ['tab' => 'audit', 'sub' => 'logs']) }}" 
                        class="border-b-2 pb-3 text-sm font-semibold transition {{ $subTab === 'logs' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
                    >
                        Journal d'Audit
                    </a>
                    <a 
                        href="{{ route('tech.dashboard', ['tab' => 'audit', 'sub' => 'users']) }}" 
                        class="border-b-2 pb-3 text-sm font-semibold transition {{ $subTab === 'users' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-800' }}"
                    >
                        Sécurité des Comptes
                    </a>
                </div>
            </div>

            @if($subTab === 'logs')
                <!-- ================= JOURNAL D'AUDIT TAB ================= -->
                
                <!-- Filters Grid Card -->
                <form method="GET" action="{{ route('tech.dashboard') }}" class="mt-6 bg-white border border-slate-200 rounded-lg p-5 shadow-sm">
                    <input type="hidden" name="tab" value="audit">
                    <input type="hidden" name="sub" value="logs">
                    
                    <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
                        <div>
                            <label for="tenant_id" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Établissement</label>
                            <select id="tenant_id" name="tenant_id" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tous les établissements</option>
                                @foreach($tenants as $tenant)
                                    <option value="{{ $tenant->id }}" {{ request('tenant_id') == $tenant->id ? 'selected' : '' }}>
                                        {{ $tenant->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div>
                            <label for="user_id" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Utilisateur</label>
                            <select id="user_id" name="user_id" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tous les utilisateurs</option>
                                @foreach($allUsers as $u)
                                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                        {{ $u->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="event_type" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Type d'événement</label>
                            <select id="event_type" name="event_type" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tous les types</option>
                                <option value="login" {{ request('event_type') === 'login' ? 'selected' : '' }}>Connexion réussie</option>
                                <option value="logout" {{ request('event_type') === 'logout' ? 'selected' : '' }}>Déconnexion</option>
                                <option value="failed_login" {{ request('event_type') === 'failed_login' ? 'selected' : '' }}>Échec de connexion</option>
                                <option value="access_denied" {{ request('event_type') === 'access_denied' ? 'selected' : '' }}>Accès refusé</option>
                                <option value="sensitive_action" {{ request('event_type') === 'sensitive_action' ? 'selected' : '' }}>Action sensible</option>
                                <option value="user_management" {{ request('event_type') === 'user_management' ? 'selected' : '' }}>Gestion des comptes</option>
                            </select>
                        </div>

                        <div>
                            <label for="module" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Module</label>
                            <select id="module" name="module" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <option value="">Tous les modules</option>
                                <option value="auth" {{ request('module') === 'auth' ? 'selected' : '' }}>Authentification</option>
                                <option value="security" {{ request('module') === 'security' ? 'selected' : '' }}>Sécurité</option>
                                <option value="bookings" {{ request('module') === 'bookings' ? 'selected' : '' }}>Réservations</option>
                                <option value="restaurant" {{ request('module') === 'restaurant' ? 'selected' : '' }}>Restaurant</option>
                                <option value="shop" {{ request('module') === 'shop' ? 'selected' : '' }}>Boutique</option>
                                <option value="rooms" {{ request('module') === 'rooms' ? 'selected' : '' }}>Chambres</option>
                                <option value="users" {{ request('module') === 'users' ? 'selected' : '' }}>Membres / Staff</option>
                            </select>
                        </div>

                        <div>
                            <label for="date_from" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Du</label>
                            <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="date_to" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Au</label>
                            <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="mt-1.5 block w-full rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>
                    
                    <div class="mt-4 flex items-center gap-2">
                        <a href="{{ route('tech.dashboard', ['tab' => 'audit', 'sub' => 'logs']) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Réinitialiser
                        </a>
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition">
                            Filtrer
                        </button>
                    </div>
                </form>

                <!-- Responsive Grid Logs List (Table styled) -->
                <div class="mt-6 bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                    <!-- Column Headers (Hidden on Mobile) -->
                    <div class="hidden md:grid grid-cols-[140px_220px_190px_1fr_120px] bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-extrabold uppercase tracking-wider py-3.5 px-5">
                        <div>Date & heure</div>
                        <div>Utilisateur</div>
                        <div>Statut / Module</div>
                        <div>Description</div>
                        <div class="text-right">Détails</div>
                    </div>
                    
                    <div class="divide-y divide-slate-100">
                        @forelse($logs as $log)
                            @php
                                $borderClass = 'border-l-4 border-slate-400';
                                $statusBadgeClass = 'text-slate-700 bg-slate-50 border-slate-200';
                                $statusText = 'Système';
                                $dotClass = 'bg-slate-500';
                                
                                if ($log->event_type === 'login') {
                                    $borderClass = 'border-l-4 border-green-500';
                                    $statusBadgeClass = 'text-green-700 bg-green-50 border-green-200';
                                    $statusText = 'Connexion réussie';
                                    $dotClass = 'bg-green-500';
                                } elseif ($log->event_type === 'access_denied') {
                                    $borderClass = 'border-l-4 border-red-500';
                                    $statusBadgeClass = 'text-red-700 bg-red-50 border-red-200';
                                    $statusText = 'Accès refusé';
                                    $dotClass = 'bg-red-500';
                                } elseif ($log->event_type === 'sensitive_action') {
                                    $borderClass = 'border-l-4 border-orange-500';
                                    $statusBadgeClass = 'text-orange-700 bg-orange-50 border-orange-200';
                                    $statusText = 'Action sensible';
                                    $dotClass = 'bg-orange-500';
                                } elseif ($log->event_type === 'logout') {
                                    $borderClass = 'border-l-4 border-blue-500';
                                    $statusBadgeClass = 'text-blue-700 bg-blue-50 border-blue-200';
                                    $statusText = 'Déconnexion';
                                    $dotClass = 'bg-blue-500';
                                } elseif ($log->event_type === 'failed_login') {
                                    $borderClass = 'border-l-4 border-slate-500';
                                    $statusBadgeClass = 'text-slate-700 bg-slate-50 border-slate-200';
                                    $statusText = 'Échec connexion';
                                    $dotClass = 'bg-slate-500';
                                }
                                
                                $moduleBadgeColors = [
                                    'auth' => 'bg-slate-100 text-slate-600 border-slate-200',
                                    'security' => 'bg-red-50 text-red-600 border-red-100',
                                    'bookings' => 'bg-amber-50 text-amber-600 border-amber-100',
                                    'restaurant' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                    'shop' => 'bg-purple-50 text-purple-600 border-purple-100',
                                    'rooms' => 'bg-indigo-50 text-indigo-600 border-indigo-100',
                                    'users' => 'bg-sky-50 text-sky-600 border-sky-100',
                                ];
                            @endphp
                            <div class="grid grid-cols-1 md:grid-cols-[140px_220px_190px_1fr_120px] items-start py-4 px-5 hover:bg-slate-50/70 transition duration-150 gap-2 md:gap-0 {{ $borderClass }}">
                                <!-- Date & Heure -->
                                <div class="text-xs text-slate-800">
                                    <div class="font-bold">{{ $log->created_at->translatedFormat('d M Y') }}</div>
                                    <div class="text-[10px] text-slate-400 font-semibold mt-0.5">{{ $log->created_at->translatedFormat('H:i') }}</div>
                                </div>
                                
                                <!-- Utilisateur -->
                                <div class="text-xs pr-4">
                                    @if($log->user)
                                        <div class="font-bold text-slate-800 truncate">{{ $log->user->name }}</div>
                                        <div class="text-[10px] text-slate-400 truncate mt-0.5">{{ $log->user->email }}</div>
                                        @if($log->tenant)
                                            <div class="inline-block mt-1 bg-blue-50 text-blue-600 text-[9px] font-bold px-1.5 py-0.5 rounded border border-blue-100 uppercase tracking-wide">
                                                {{ $log->tenant->name }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-slate-400 italic">Visiteur Anonyme</span>
                                    @endif
                                </div>
                                
                                <!-- Statut / Module Badges -->
                                <div class="flex flex-row md:flex-col gap-1.5 items-center md:items-start text-[10px]">
                                    <span class="inline-flex rounded border px-1.5 py-0.5 font-bold uppercase tracking-wider {{ $moduleBadgeColors[$log->module] ?? 'bg-slate-100 text-slate-600 border-slate-200' }}">
                                        {{ $log->module ?? 'global' }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 font-bold {{ $statusBadgeClass }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}"></span>
                                        {{ $statusText }}
                                    </span>
                                </div>
                                
                                <!-- Description -->
                                <div class="text-xs text-slate-800 pr-4 leading-relaxed">
                                    <div class="font-semibold text-slate-800">{{ $log->action }}</div>
                                    <!-- IP & User-Agent Box -->
                                    <div class="mt-1.5 inline-flex items-center gap-2 bg-slate-50 border border-slate-100 rounded px-2.5 py-1 text-[10px] text-slate-400 font-mono w-full max-w-lg shadow-2xs">
                                        <span class="font-bold text-slate-500 bg-slate-200 px-1 rounded text-[8px] tracking-wide">IP</span>
                                        <span class="text-slate-700 font-semibold">{{ $log->ip_address ?? '127.0.0.1' }}</span>
                                        @if($log->user_agent)
                                            <span class="text-slate-300">â€¢</span>
                                            <span class="truncate max-w-[260px] text-slate-600 hover:text-slate-800 cursor-help" title="{{ $log->user_agent }}">{{ $log->user_agent }}</span>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Details Trigger (AlpineJS Popover) -->
                                <div class="text-right text-xs whitespace-nowrap relative self-center md:self-start mt-2 md:mt-0" x-data="{ open: false }">
                                    @if($log->payload)
                                        <button @click="open = !open" type="button" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-bold transition">
                                            <span>Voir variables</span>
                                            <span class="text-[9px]">â†—</span>
                                        </button>
                                        
                                        <!-- Code Popover box -->
                                        <div x-show="open" @click.away="open = false" x-transition class="absolute z-20 mt-2 right-0 w-80 rounded-lg border border-slate-800 bg-slate-950 text-slate-200 text-left p-4 shadow-xl font-mono text-[10px] max-h-64 overflow-auto">
                                            <div class="flex items-center justify-between border-b border-slate-800 pb-2 mb-2">
                                                <span class="font-bold text-slate-400 uppercase tracking-wider text-[9px]">Variables d'événement</span>
                                                <button @click="open = false" class="text-slate-400 hover:text-white">&times;</button>
                                            </div>
                                            <pre class="whitespace-pre-wrap">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-[10px]">Aucune variable</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-slate-400 italic text-xs">
                                Aucun log d'audit correspondant aux critères de filtrage.
                            </div>
                        @endforelse
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="mt-5">
                    {{ $logs->links() }}
                </div>
            @endif

            @if($subTab === 'users')
                <!-- ================= SECURITE DES COMPTES TAB ================= -->
                
                <!-- Search user bar -->
                <form method="GET" action="{{ route('tech.dashboard') }}" class="mt-6 flex gap-2">
                    <input type="hidden" name="tab" value="audit">
                    <input type="hidden" name="sub" value="users">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input 
                            type="text" 
                            name="user_search" 
                            value="{{ request('user_search') }}" 
                            placeholder="Rechercher par nom, email ou rôle..." 
                            class="block w-full rounded-md border border-slate-200 bg-white pl-10 pr-3 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-2xs"
                        >
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2.5 text-xs font-semibold text-white hover:bg-indigo-700 transition shadow-sm">
                        Rechercher
                    </button>
                    @if(request()->filled('user_search'))
                        <a href="{{ route('tech.dashboard', ['tab' => 'audit', 'sub' => 'users']) }}" class="rounded-md border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition flex items-center">
                            Effacer
                        </a>
                    @endif
                </form>

                <!-- Accounts Grid Table -->
                <div class="mt-6 bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                    <!-- Column Headers (Hidden on Mobile) -->
                    <div class="hidden md:grid grid-cols-[260px_160px_190px_190px_1fr] bg-slate-50 border-b border-slate-200 text-slate-400 text-[10px] font-extrabold uppercase tracking-wider py-3.5 px-5">
                        <div>Utilisateur</div>
                        <div>Rôle</div>
                        <div>Dernière Connexion</div>
                        <div>Activité / En ligne</div>
                        <div class="text-right">Actions de Sécurité</div>
                    </div>
                    
                    <div class="divide-y divide-slate-100">
                        @forelse($users as $u)
                            <div class="grid grid-cols-1 md:grid-cols-[260px_160px_190px_190px_1fr] items-center py-4 px-5 hover:bg-slate-50/70 transition duration-150 gap-2.5 md:gap-0">
                                <!-- User Identity -->
                                <div class="text-xs">
                                    <div class="font-bold text-slate-800">{{ $u->name }}</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">{{ $u->email }}</div>
                                    <div class="mt-1">
                                        @if($u->tenant)
                                            <span class="bg-blue-50 text-blue-600 text-[9px] font-bold px-1.5 py-0.5 rounded border border-blue-100 uppercase tracking-wide">
                                                {{ $u->tenant->name }}
                                            </span>
                                        @else
                                            <span class="bg-slate-900 text-white text-[9px] font-bold px-1.5 py-0.5 rounded border border-slate-800 uppercase tracking-wide">
                                                Admin Global
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                
                                <!-- Role -->
                                <div class="text-xs">
                                    <span class="font-semibold text-slate-700 capitalize">{{ str_replace('_', ' ', $u->role) }}</span>
                                </div>
                                
                                <!-- Last Login Timestamp -->
                                <div class="text-xs text-slate-600">
                                    @if($u->last_login_at)
                                        <span class="font-semibold text-slate-700">{{ $u->last_login_at->translatedFormat('d M Y') }}</span>
                                        <div class="text-[10px] text-slate-400 mt-0.5">{{ $u->last_login_at->translatedFormat('H:i') }}</div>
                                    @else
                                        <span class="text-slate-400 italic">Aucune connexion</span>
                                    @endif
                                </div>
                                
                                <!-- Online / Active Status badges -->
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $u->is_active ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                                        {{ $u->is_active ? 'Actif' : 'Désactivé' }}
                                    </span>
                                    
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $u->isOnline() ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $u->isOnline() ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400' }}"></span>
                                        {{ $u->isOnline() ? 'En ligne' : 'Hors ligne' }}
                                    </span>
                                </div>
                                
                                <!-- Security Actions buttons -->
                                <div class="text-right whitespace-nowrap">
                                    @if($u->id !== Auth::id())
                                        <div class="flex md:justify-end gap-2">
                                            <form method="POST" action="{{ route('tech.users.toggle-active', $u) }}" class="inline">
                                                @csrf
                                                <button 
                                                    type="submit" 
                                                    class="rounded-md border px-3 py-1.5 text-xs font-semibold transition shadow-sm {{ $u->is_active ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100 hover:border-red-300' : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100 hover:border-green-300' }}"
                                                >
                                                    {{ $u->is_active ? 'Désactiver' : 'Activer' }}
                                                </button>
                                            </form>
                                            
                                            <form method="POST" action="{{ route('tech.users.reset-password', $u) }}" class="inline" onsubmit="return confirm('Voulez-vous vraiment forcer la réinitialisation du mot de passe de {{ $u->name }} ?')">
                                                @csrf
                                                <button 
                                                    type="submit" 
                                                    class="rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:border-slate-400 transition shadow-sm"
                                                >
                                                    Réinitialiser MDP
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic text-[10px]">Mon compte</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="py-12 text-center text-slate-400 italic text-xs">
                                Aucun utilisateur trouvé.
                            </div>
                        @endforelse
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="mt-5">
                    {{ $users->links() }}
                </div>
            @endif
            
        @endif

     </main>

    <!-- Modal: Créer un Manager pour un établissement ( BUSINESS / Owner space ) -->
    <div 
        x-data="{ 
            open: false, 
            tenantId: null, 
            tenantName: '',
            name: '',
            email: '',
            phone: '',
            password: '',
            errorMsg: '',
            successMsg: '',
            submitting: false
        }"
        @open-create-manager-modal.window="
            open = true; 
            tenantId = $event.detail.tenant_id; 
            tenantName = $event.detail.tenant_name; 
            name = ''; 
            email = ''; 
            phone = ''; 
            password = ''; 
            errorMsg = '';
            successMsg = '';
        "
        x-show="open" 
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4"
        style="display: none;"
        x-transition
    >
        <div class="bg-white rounded-xl border border-slate-200 w-full max-w-md shadow-2xl overflow-hidden" @click.away="if(!submitting) open = false">
            <!-- Header -->
            <div class="bg-slate-900 px-6 py-4 flex items-center justify-between">
                <h3 class="text-sm font-bold text-white tracking-wide">Créer le Manager - <span x-text="tenantName"></span></h3>
                <button type="button" @click="open = false" class="text-slate-400 hover:text-white text-lg font-bold" :disabled="submitting">&times;</button>
            </div>
            
            <!-- Form -->
            <form @submit.prevent="
                submitting = true;
                errorMsg = '';
                successMsg = '';
                fetch('/business/establishments/' + tenantId + '/create-manager', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ name, email, phone, password })
                })
                .then(res => res.json().then(data => ({ status: res.status, body: data })))
                .then(res => {
                    submitting = false;
                    if (res.status === 200 || res.status === 201) {
                        successMsg = 'Le compte manager a été créé avec succès dans l\'établissement !';
                        name = '';
                        email = '';
                        phone = '';
                        password = '';
                        setTimeout(() => { open = false; window.location.reload(); }, 1500);
                    } else {
                        errorMsg = res.body.message || 'Une erreur est survenue lors de la création du manager.';
                    }
                })
                .catch(err => {
                    submitting = false;
                    errorMsg = 'Impossible de se connecter au serveur.';
                });
            " class="p-6 space-y-4">
                
                <template x-if="errorMsg">
                    <div class="rounded-lg bg-red-50 border border-red-150 p-3 text-xs font-semibold text-red-700" x-text="errorMsg"></div>
                </template>
                
                <template x-if="successMsg">
                    <div class="rounded-lg bg-emerald-50 border border-emerald-150 p-3 text-xs font-semibold text-emerald-700" x-text="successMsg"></div>
                </template>

                <!-- Name -->
                <div>
                    <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom complet <span class="text-red-400">*</span></label>
                    <input type="text" x-model="name" required placeholder="Ex: Jean Dupont"
                           class="mt-1 block w-full rounded-lg border border-slate-205 bg-white px-3 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 transition">
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Adresse e-mail <span class="text-red-400">*</span></label>
                    <input type="email" x-model="email" required placeholder="manager@etablissement.com"
                           class="mt-1 block w-full rounded-lg border border-slate-205 bg-white px-3 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 transition">
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Téléphone</label>
                    <input type="text" x-model="phone" placeholder="+237 600 000 000"
                           class="mt-1 block w-full rounded-lg border border-slate-205 bg-white px-3 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 transition">
                </div>

                <!-- Password -->
                <div>
                    <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Mot de passe temporaire <span class="text-red-400">*</span></label>
                    <input type="password" x-model="password" required minlength="4" placeholder="••••••••"
                           class="mt-1 block w-full rounded-lg border border-slate-205 bg-white px-3 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 transition">
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="open = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer" :disabled="submitting">
                        Annuler
                    </button>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition cursor-pointer" :disabled="submitting">
                        <span x-show="submitting">Création...</span>
                        <span x-show="!submitting">Créer le Manager</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
