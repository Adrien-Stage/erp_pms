@php
    $sidebarMenus = [
        'overview' => ['label' => 'Vue d\'ensemble', 'icon' => 'chart'],
        'info' => ['label' => 'Informations', 'icon' => 'building'],
        'users' => ['label' => 'Utilisateurs', 'icon' => 'users'],
        'theme' => ['label' => 'Thème & Couleurs', 'icon' => 'palette'],
        'modules' => ['label' => 'Modules', 'icon' => 'puzzle'],
    ];
    // Onglet visible uniquement si le module "website" est actif — sinon rien
    // à configurer (aucun container "web" provisionné pour cet établissement).
    if (in_array('website', $tenant->modules ?? [])) {
        $sidebarMenus['site-content'] = ['label' => 'Contenu du site', 'icon' => 'globe'];
    }
    $sidebarMenus['settings'] = ['label' => 'Paramètres', 'icon' => 'cog'];
    $section = request('section', 'overview');
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — Administration</title>
    <meta name="description" content="Espace de gestion de l'établissement {{ $tenant->name }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased font-body">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-30 w-full bg-[#0f172a] border-b border-slate-800 text-white shadow-md">
        <div class="mx-auto px-5 lg:px-8 flex items-center justify-between h-14">
            <div class="flex items-center gap-4">
                <a href="{{ route('tech.dashboard', ['tab' => 'tenants']) }}" class="flex items-center gap-2 text-slate-400 hover:text-white transition text-xs font-semibold">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Établissements
                </a>
                <div class="h-5 w-px bg-slate-700"></div>
                <div class="flex items-center gap-3">
                    @if(!empty($tenant->settings['logo']))
                        <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="{{ $tenant->name }}" class="h-7 object-contain">
                    @else
                        <div class="h-7 w-7 rounded bg-indigo-600/20 flex items-center justify-center">
                            <svg class="h-4 w-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                            </svg>
                        </div>
                    @endif
                    <div>
                        <h1 class="text-sm font-bold text-white leading-none">{{ $tenant->name }}</h1>
                        <p class="text-[10px] text-slate-400 font-mono">{{ $tenant->slug }}</p>
                    </div>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                @php
                    $dockerBadge = match($tenant->docker_status) {
                        'running'  => ['label' => 'Conteneurs actifs',  'classes' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30', 'dot' => 'bg-emerald-400'],
                        'creating' => ['label' => 'Provisioning…',      'classes' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',       'dot' => 'bg-amber-400 animate-pulse'],
                        'error'    => ['label' => 'Erreur conteneurs',  'classes' => 'bg-red-500/10 text-red-400 border-red-500/30',             'dot' => 'bg-red-400'],
                        default    => ['label' => 'Conteneurs arrêtés', 'classes' => 'bg-slate-500/10 text-slate-400 border-slate-500/30',       'dot' => 'bg-slate-400'],
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $dockerBadge['classes'] }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $dockerBadge['dot'] }}"></span>
                    {{ $dockerBadge['label'] }}
                </span>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $tenant->is_active ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30' }}">
                    {{ $tenant->is_active ? 'Actif' : 'Inactif' }}
                </span>

                {{-- Liens d'accès directs --}}
                @if($tenant->hasGrc())
                    <a href="{{ $tenant->grcUrl() }}" target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-md bg-teal-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-teal-500 transition shadow-sm border border-teal-400/30"
                       title="Ouvrir la plateforme Wetchah_GRC (Contrôle de Gestion & Audit)">
                        <svg class="h-3.5 w-3.5 text-teal-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                        <span>Ouvrir GRC</span>
                        <svg class="h-3 w-3 text-teal-200 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                @endif
                @if($tenant->appUrl())
                    <a href="{{ $tenant->appUrl() }}" target="_blank"
                       class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-500 transition shadow-sm border border-indigo-400/30"
                       title="Ouvrir l'application PMS de l'établissement">
                        <span>Ouvrir App</span>
                        <svg class="h-3 w-3 text-indigo-200 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                @endif

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-bold text-slate-300 hover:bg-slate-700 hover:text-white transition">
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </header>

    <div class="flex min-h-[calc(100vh-3.5rem)]">
        <!-- ======= SIDEBAR ======= -->
        <aside class="fixed top-14 left-0 bottom-0 w-60 overflow-y-auto bg-white border-r border-slate-200 shadow-sm">
            <nav class="p-4 space-y-1">
                @foreach($sidebarMenus as $key => $menu)
                    <a 
                        href="{{ route('tech.establishments.show', ['tenant' => $tenant, 'section' => $key]) }}"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-semibold transition {{ $section === $key ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 shadow-sm' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 border border-transparent' }}"
                    >
                        @if($menu['icon'] === 'chart')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                            </svg>
                        @elseif($menu['icon'] === 'building')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                            </svg>
                        @elseif($menu['icon'] === 'users')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                            </svg>
                        @elseif($menu['icon'] === 'palette')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" />
                            </svg>
                        @elseif($menu['icon'] === 'puzzle')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.087c0-.355.186-.676.401-.959.221-.29.349-.634.349-1.003 0-1.036-1.007-1.875-2.25-1.875s-2.25.84-2.25 1.875c0 .369.128.713.349 1.003.215.283.401.604.401.959v0a.64.64 0 01-.657.643 48.421 48.421 0 01-4.163-.3c.186 1.613.66 3.129 1.41 4.483l.278.483a.798.798 0 00.984.332 48.027 48.027 0 005.15-.87.634.634 0 00.387-.88c-.55-1.15-.894-2.418-1.012-3.77a.637.637 0 01.594-.673h.055c.353 0 .673.186.959.401.29.221.634.349 1.003.349 1.035 0 1.875-1.007 1.875-2.25s-.84-2.25-1.875-2.25c-.37 0-.713.128-1.003.349-.286.215-.606.401-.96.401v0a.64.64 0 01-.643-.657 48.421 48.421 0 01.3-4.163c-1.613.186-3.129.66-4.483 1.41l-.483.278a.798.798 0 00-.332.984c.38.807.686 1.658.914 2.545" />
                            </svg>
                        @elseif($menu['icon'] === 'cog')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        @elseif($menu['icon'] === 'globe')
                            <svg class="h-4 w-4 shrink-0 {{ $section === $key ? 'text-indigo-500' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.6 9h16.8M3.6 15h16.8M11.5 3a17 17 0 000 18M12.5 3a17 17 0 010 18" />
                            </svg>
                        @endif
                        {{ $menu['label'] }}
                    </a>
                @endforeach
            </nav>

            <!-- Accès Directs aux Portails -->
            <div class="border-t border-slate-100 p-4 mt-2">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Accès Directs</p>
                <div class="space-y-1.5">
                    @if($tenant->appUrl())
                        <a href="{{ $tenant->appUrl() }}" target="_blank"
                           class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-semibold text-indigo-700 bg-indigo-50/70 hover:bg-indigo-100/70 border border-indigo-100 transition">
                            <span class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ $tenant->docker_status === 'running' ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                                <span>Application PMS</span>
                            </span>
                            <span class="font-mono text-[10px] text-indigo-500">:{{ $tenant->app_port }} ↗</span>
                        </a>
                    @endif

                    @if($tenant->hasGrc())
                        <a href="{{ $tenant->grcUrl() }}" target="_blank"
                           class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-semibold text-teal-700 bg-teal-50/70 hover:bg-teal-100/70 border border-teal-200/80 transition"
                           title="Ouvrir la plateforme de Contrôle de Gestion & GRC (Wetchah_GRC)">
                            <span class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ $tenant->docker_grc_container ? 'bg-teal-500' : 'bg-slate-300' }}"></span>
                                <span>Wetchah_GRC</span>
                            </span>
                            <span class="font-mono text-[10px] text-teal-600">:{{ $tenant->resolvedGrcPort() }} ↗</span>
                        </a>
                    @endif

                    @if($tenant->websiteUrl())
                        <a href="{{ $tenant->websiteUrl() }}" target="_blank"
                           class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-semibold text-rose-700 bg-rose-50/70 hover:bg-rose-100/70 border border-rose-100 transition">
                            <span class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full {{ $tenant->docker_web_container ? 'bg-rose-500' : 'bg-slate-300' }}"></span>
                                <span>Site Web</span>
                            </span>
                            <span class="font-mono text-[10px] text-rose-500">↗</span>
                        </a>
                    @endif
                </div>
            </div>

            <!-- Sidebar Footer -->
            <div class="border-t border-slate-100 p-4 mt-4">
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-3">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Statistiques</p>
                    <div class="space-y-1.5 text-xs text-slate-600">
                        <div class="flex justify-between">
                            <span>Utilisateurs</span>
                            <span class="font-bold text-slate-800">{{ $tenant->users_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Chambres</span>
                            <span class="font-bold text-slate-800">{{ $tenant->rooms_count ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Réservations</span>
                            <span class="font-bold text-slate-800">{{ $tenant->bookings_count ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ======= MAIN CONTENT ======= -->
        <main class="flex-1 ml-60 p-8">
            <!-- Flash Messages -->
            @if(session('success'))
                <div class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4 text-xs font-bold text-green-800 shadow-sm flex items-center gap-2">
                    <svg class="h-4 w-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4 shadow-sm">
                    <ul class="list-disc list-inside text-xs text-red-700 space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ==================== ÉCHEC DE PROVISIONING : RELANCE ==================== --}}
            @if($tenant->docker_status === 'error' && !session('start_provisioning'))
                <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4 shadow-sm flex items-center justify-between gap-4">
                    <div class="flex items-center gap-2 text-xs font-bold text-red-800">
                        <svg class="h-4 w-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                        <span>Le provisioning Docker a échoué pour cet établissement.</span>
                    </div>
                    <form action="{{ route('tech.establishments.provision', $tenant) }}" method="POST">
                        @csrf
                        <button type="submit" class="shrink-0 rounded-lg bg-red-600 px-4 py-2 text-xs font-bold text-white hover:bg-red-700 transition shadow-sm cursor-pointer">
                            Relancer le provisioning
                        </button>
                    </form>
                </div>
            @endif

            {{-- ==================== LOGS DE PROVISIONING (SSE) ==================== --}}
            @if(session('start_provisioning') || $tenant->docker_status === 'creating')
                <div class="mb-8 bg-slate-900 rounded-xl border border-slate-800 shadow-2xl overflow-hidden" id="provisioning-log-widget">
                    <div class="bg-slate-950 px-6 py-4 flex items-center justify-between border-b border-slate-850">
                        <div class="flex items-center gap-3">
                            <span class="flex h-2.5 w-2.5 relative">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-indigo-500"></span>
                            </span>
                            <div>
                                <h3 class="text-xs font-bold text-white uppercase tracking-wider">Provisioning Docker en cours…</h3>
                                <p class="text-[10px] text-slate-400">Configuration de l'instance et exécution des migrations en direct</p>
                            </div>
                        </div>
                        <span class="text-[10px] font-mono text-indigo-400 bg-indigo-950/50 border border-indigo-900/30 rounded px-2 py-0.5" id="provisioning-status">EN COURS</span>
                    </div>
                    <div class="p-6">
                        <div id="log-output" class="font-mono text-xs text-slate-300 space-y-1.5 bg-slate-950/70 rounded-lg p-5 border border-slate-850 overflow-y-auto h-72 scrollbar-thin scrollbar-thumb-slate-800" style="word-break: break-word; overflow-wrap: break-word;">
                            <div class="text-slate-500 italic">[Connexion au flux de logs...]</div>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-[10px] text-slate-400">
                            <span>Veuillez ne pas fermer cette page pendant l'opération.</span>
                            <div class="flex items-center gap-3">
                                <button type="button" onclick="copyLogText('log-output', this)"
                                        class="rounded border border-slate-700 bg-slate-800 px-2.5 py-1 text-[10px] font-bold text-slate-300 hover:bg-slate-700 hover:text-white transition cursor-pointer">
                                    Copier les logs
                                </button>
                                <span class="font-mono" id="log-time-elapsed">Durée : 0s</span>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const logOutput = document.getElementById('log-output');
                    const statusBadge = document.getElementById('provisioning-status');
                    const timeElapsedSpan = document.getElementById('log-time-elapsed');
                    const streamUrl = '{{ route("tech.establishments.provision.stream", $tenant) }}';
                    
                    let secondsElapsed = 0;
                    const timer = setInterval(() => {
                        secondsElapsed++;
                        timeElapsedSpan.textContent = `Durée : ${secondsElapsed}s`;
                    }, 1000);

                    // Clear placeholder log
                    logOutput.innerHTML = '';

                    const evtSource = new EventSource(streamUrl);

                    evtSource.onmessage = function (event) {
                        try {
                            const data = JSON.parse(event.data);
                            const line = document.createElement('div');
                            line.className = 'flex gap-2.5 items-start py-0.5 border-b border-slate-900/10';
                            
                            const timeSpan = document.createElement('span');
                            timeSpan.className = 'text-slate-500 shrink-0 font-semibold select-none';
                            timeSpan.textContent = `[${data.time}]`;
                            
                            const msgSpan = document.createElement('span');
                            msgSpan.className = {
                                'success': 'text-emerald-400 font-semibold',
                                'error':   'text-red-400 font-semibold',
                                'warning': 'text-amber-400',
                                'info':    'text-slate-300',
                            }[data.level] || 'text-slate-300';
                            msgSpan.textContent = data.message;
                            
                            line.appendChild(timeSpan);
                            line.appendChild(msgSpan);
                            logOutput.appendChild(line);
                            logOutput.scrollTop = logOutput.scrollHeight;

                            if (data.step === 'done' || data.step === 'finished') {
                                clearInterval(timer);
                                statusBadge.textContent = 'TERMINÉ';
                                statusBadge.className = 'text-[10px] font-mono text-emerald-400 bg-emerald-950/50 border border-emerald-900/30 rounded px-2 py-0.5';
                                evtSource.close();
                                setTimeout(() => location.reload(), 2000);
                            }
                            
                            if (data.level === 'error' || data.step === 'error') {
                                clearInterval(timer);
                                statusBadge.textContent = 'ÉCHEC';
                                statusBadge.className = 'text-[10px] font-mono text-red-400 bg-red-950/50 border border-red-900/30 rounded px-2 py-0.5';
                                evtSource.close();
                            }
                        } catch (e) {
                            console.error("Error parsing event stream payload:", e);
                        }
                    };

                    evtSource.onerror = function () {
                        clearInterval(timer);
                        evtSource.close();
                    };
                });
                </script>
            @endif

            {{-- ==================== VUE D'ENSEMBLE ==================== --}}
            @if($section === 'overview')
                <div class="mb-6">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Vue d'ensemble</h2>
                    <p class="text-xs text-slate-500 mt-1">Tableau de bord de l'établissement {{ $tenant->name }}</p>
                </div>

                <!-- KPI Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-indigo-100 p-2.5">
                                <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Utilisateurs</p>
                                <p class="text-2xl font-extrabold text-slate-800">{{ $tenant->users_count }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-emerald-100 p-2.5">
                                <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 0h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Chambres</p>
                                <p class="text-2xl font-extrabold text-slate-800">{{ $tenant->rooms_count ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-amber-100 p-2.5">
                                <svg class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Réservations</p>
                                <p class="text-2xl font-extrabold text-slate-800">{{ $tenant->bookings_count ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center gap-3">
                            <div class="rounded-lg bg-violet-100 p-2.5">
                                <svg class="h-5 w-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Devise</p>
                                <p class="text-2xl font-extrabold text-slate-800">{{ $tenant->currency }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Portails & Accès Opérationnels -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                            </svg>
                            <h3 class="text-sm font-bold text-slate-800">Accès directs aux services de l'établissement</h3>
                        </div>
                        <span class="text-[10px] text-slate-400">Liens directs d'exploitation</span>
                    </div>
                    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <!-- Application PMS -->
                        <div class="rounded-xl border border-slate-200 p-4 bg-slate-50/50 flex flex-col justify-between gap-3">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-800">Application PMS</span>
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold {{ $tenant->docker_status === 'running' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">
                                        {{ $tenant->docker_status === 'running' ? 'En ligne' : 'Arrêté' }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">Hébergement, réservations, caisse et restauration.</p>
                            </div>
                            @if($tenant->appUrl())
                                <a href="{{ $tenant->appUrl() }}" target="_blank"
                                   class="inline-flex items-center justify-center gap-1.5 w-full rounded-lg bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                                    <span>Ouvrir l'application</span>
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                </a>
                            @endif
                        </div>

                        <!-- Contrôle de Gestion & GRC -->
                        <div class="rounded-xl border p-4 flex flex-col justify-between gap-3 {{ $tenant->hasGrc() ? 'border-teal-200 bg-teal-50/30' : 'border-slate-200 bg-slate-50/20 opacity-60' }}">
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-slate-800">Wetchah_GRC</span>
                                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-teal-100 text-teal-800 uppercase">Module 4</span>
                                    </div>
                                    @if($tenant->hasGrc())
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold bg-emerald-100 text-emerald-700">
                                            Actif (:{{ $tenant->resolvedGrcPort() }})
                                        </span>
                                    @else
                                        <span class="text-[9px] text-slate-400 font-semibold">Non activé</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">Gouvernance, risques 5x5, conformité, audits et contrôle de gestion.</p>
                            </div>
                            @if($tenant->hasGrc())
                                <a href="{{ $tenant->grcUrl() }}" target="_blank"
                                   class="inline-flex items-center justify-center gap-1.5 w-full rounded-lg bg-teal-600 px-3 py-2 text-xs font-bold text-white hover:bg-teal-700 transition shadow-sm">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                    </svg>
                                    <span>Ouvrir Wetchah_GRC</span>
                                    <svg class="h-3.5 w-3.5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                </a>
                            @else
                                <a href="{{ route('tech.establishments.show', ['tenant' => $tenant, 'section' => 'modules']) }}"
                                   class="inline-flex items-center justify-center gap-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                                    <span>Activer le module GRC →</span>
                                </a>
                            @endif
                        </div>

                        <!-- Site Vitrine (Public) -->
                        @php
                            $isWebActive = in_array('website', $tenant->modules ?? [], true);
                        @endphp
                        <div class="rounded-xl border p-4 flex flex-col justify-between gap-3 {{ $isWebActive ? 'border-rose-200 bg-rose-50/30' : 'border-slate-200 bg-slate-50/20 opacity-60' }}">
                            <div>
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-slate-800">Site Web Vitrine</span>
                                        <span class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-rose-100 text-rose-800 uppercase">Module 3</span>
                                    </div>
                                    @if($isWebActive)
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold bg-emerald-100 text-emerald-700">
                                            Actif
                                        </span>
                                    @else
                                        <span class="text-[9px] text-slate-400 font-semibold">Non activé</span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-slate-500 mt-1">Site vitrine public, catalogue et réservations en ligne.</p>
                            </div>
                            @if($isWebActive && $tenant->websiteUrl())
                                <a href="{{ $tenant->websiteUrl() }}" target="_blank"
                                   class="inline-flex items-center justify-center gap-1.5 w-full rounded-lg bg-rose-600 px-3 py-2 text-xs font-bold text-white hover:bg-rose-700 transition shadow-sm">
                                    <span>Ouvrir le Site Web</span>
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                </a>
                            @else
                                <a href="{{ route('tech.establishments.show', ['tenant' => $tenant, 'section' => 'modules']) }}"
                                   class="inline-flex items-center justify-center gap-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                                    <span>Activer le module Web →</span>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Info Summary -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="text-sm font-bold text-slate-800">Informations générales</h3>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nom</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Slug</dt>
                                <dd class="mt-1 font-mono text-slate-600">{{ $tenant->slug }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pays</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->settings['country'] ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Adresse</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->address ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Téléphone</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->phone ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email</dt>
                                <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->email ?? 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <!-- Propriétaire (owner / compte business) -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mt-6">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-2">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                        </svg>
                        <h3 class="text-sm font-bold text-slate-800">Propriétaire (compte business)</h3>
                    </div>
                    @if($tenant->owner)
                        <div class="p-6">
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                                <div>
                                    <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nom</dt>
                                    <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->owner->name }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Entreprise</dt>
                                    <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->owner->company_name ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email</dt>
                                    <dd class="mt-1 font-semibold text-slate-800">
                                        @if($tenant->owner->email)
                                            <a href="mailto:{{ $tenant->owner->email }}" class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $tenant->owner->email }}</a>
                                        @else N/A @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Téléphone</dt>
                                    <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->owner->phone ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nationalité</dt>
                                    <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->owner->nationality ?? 'N/A' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Statut du compte</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $tenant->owner->is_active ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $tenant->owner->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                            {{ $tenant->owner->is_active ? 'Actif' : 'Inactif' }}
                                        </span>
                                    </dd>
                                </div>
                                @if($tenant->owner->last_login_at)
                                    <div>
                                        <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dernière connexion</dt>
                                        <dd class="mt-1 font-semibold text-slate-800">{{ $tenant->owner->last_login_at->format('d/m/Y à H:i') }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @else
                        <div class="p-6 text-sm text-slate-400 italic">Aucun propriétaire rattaché à cet établissement.</div>
                    @endif
                </div>

            {{-- ==================== INFORMATIONS ==================== --}}
            @elseif($section === 'info')
                <div class="mb-6">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Informations de l'Établissement</h2>
                    <p class="text-xs text-slate-500 mt-1">Modifier le nom, l'adresse et les coordonnées</p>
                </div>

                <form action="{{ route('tech.establishments.update', $tenant) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    @csrf
                    <div class="p-6 space-y-5">
                        <!-- Logo -->
                        <div x-data="{ logoPreview: '{{ !empty($tenant->settings['logo']) ? asset('storage/' . $tenant->settings['logo']) : '' }}' }">
                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-2">Logo</label>
                            <div class="flex items-center gap-5">
                                <div class="h-16 w-24 bg-slate-950 border border-slate-700 rounded-lg flex items-center justify-center overflow-hidden">
                                    <template x-if="logoPreview"><img :src="logoPreview" class="h-14 object-contain"></template>
                                    <template x-if="!logoPreview">
                                        <svg class="h-6 w-6 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                                    </template>
                                </div>
                                <input type="file" name="logo" accept="image/*" @change="logoPreview = URL.createObjectURL($event.target.files[0])" class="text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom <span class="text-red-400">*</span></label>
                                <input type="text" name="name" value="{{ old('name', $tenant->name) }}" required class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Slug <span class="text-red-400">*</span></label>
                                <input type="text" name="slug" value="{{ old('slug', $tenant->slug) }}" required class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Pays</label>
                                <input type="text" name="country" value="{{ old('country', $tenant->settings['country'] ?? 'Cameroun') }}" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Devise <span class="text-red-400">*</span></label>
                                <input type="text" name="currency" value="{{ old('currency', $tenant->currency) }}" required maxlength="3" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono uppercase outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Adresse</label>
                            <input type="text" name="address" value="{{ old('address', $tenant->address) }}" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Téléphone</label>
                                <input type="text" name="phone" value="{{ old('phone', $tenant->phone) }}" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Email</label>
                                <input type="email" name="email" value="{{ old('email', $tenant->email) }}" class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>

            {{-- ==================== UTILISATEURS ==================== --}}
            @elseif($section === 'users')
                <div class="mb-6 flex items-baseline justify-between" x-data="{
                    showCreateManagerModal: false,
                    submitting: false,
                    errorMsg: '',
                    generatedPassword: null,
                    name: '', email: '', phone: '',
                    resetForm() { this.name = ''; this.email = ''; this.phone = ''; this.errorMsg = ''; this.generatedPassword = null; },
                    submit() {
                        this.submitting = true;
                        this.errorMsg = '';
                        fetch('{{ route('tech.establishments.create-manager', $tenant) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ name: this.name, email: this.email, phone: this.phone })
                        })
                        .then(res => res.json().then(data => ({ status: res.status, body: data })))
                        .then(res => {
                            this.submitting = false;
                            if (res.status === 201) {
                                this.generatedPassword = res.body.generated_password;
                            } else {
                                this.errorMsg = res.body.message || 'Une erreur est survenue lors de la création du manager.';
                            }
                        })
                        .catch(() => {
                            this.submitting = false;
                            this.errorMsg = 'Impossible de se connecter au serveur.';
                        });
                    },
                    showCreateControllerModal: false,
                    submittingController: false,
                    errorMsgController: '',
                    generatedPasswordController: null,
                    nameController: '', emailController: '', phoneController: '',
                    resetControllerForm() { this.nameController = ''; this.emailController = ''; this.phoneController = ''; this.errorMsgController = ''; this.generatedPasswordController = null; },
                    submitController() {
                        this.submittingController = true;
                        this.errorMsgController = '';
                        fetch('{{ route('tech.establishments.create-controller', $tenant) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ name: this.nameController, email: this.emailController, phone: this.phoneController })
                        })
                        .then(res => res.json().then(data => ({ status: res.status, body: data })))
                        .then(res => {
                            this.submittingController = false;
                            if (res.status === 201) {
                                this.generatedPasswordController = res.body.generated_password;
                            } else {
                                this.errorMsgController = res.body.message || 'Une erreur est survenue lors de la création du contrôleur.';
                            }
                        })
                        .catch(() => {
                            this.submittingController = false;
                            this.errorMsgController = 'Impossible de se connecter au serveur.';
                        });
                    }
                }">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Utilisateurs</h2>
                        <p class="text-xs text-slate-500 mt-1">{{ $tenantUsers->count() }} utilisateur(s) rattaché(s) à {{ $tenant->name }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="showCreateManagerModal = true; resetForm()"
                                class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Créer un manager
                        </button>
                        <button type="button" @click="showCreateControllerModal = true; resetControllerForm()"
                                class="inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-teal-700 transition cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                            Créer un contrôleur GRC
                        </button>
                    </div>

                    <!-- Create Manager Modal -->
                    <div x-show="showCreateManagerModal"
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         x-cloak>
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden max-w-md w-full"
                             @click.away="if (!submitting) { showCreateManagerModal = generatedPassword ? showCreateManagerModal : false }">

                            <!-- Header -->
                            <div class="bg-slate-950 px-6 py-5 flex items-center gap-3">
                                <div class="rounded-lg bg-indigo-500/20 p-2">
                                    <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 19.235V18a3.75 3.75 0 013.75-3.75h1.5A3.75 3.75 0 0112 18v1.235" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-white tracking-wide">Créer un manager — <span class="font-mono">{{ $tenant->slug }}</span></h3>
                            </div>

                            <!-- Formulaire (avant création) -->
                            <template x-if="!generatedPassword">
                                <form @submit.prevent="submit()" class="p-6 space-y-4">
                                    <p class="text-xs text-slate-600 leading-relaxed">
                                        Crée un compte manager (directeur) directement dans la base applicative de cet établissement. Un mot de passe sera généré automatiquement.
                                    </p>

                                    <template x-if="errorMsg">
                                        <div class="rounded-lg bg-red-50 border border-red-150 p-3 text-xs font-semibold text-red-700" x-text="errorMsg"></div>
                                    </template>

                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Nom complet <span class="text-red-400">*</span></label>
                                        <input type="text" x-model="name" required placeholder="Ex: Jean Dupont"
                                               class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Adresse e-mail <span class="text-red-400">*</span></label>
                                        <input type="email" x-model="email" required placeholder="manager@etablissement.com"
                                               class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Téléphone</label>
                                        <input type="text" x-model="phone" placeholder="+237 600 000 000"
                                               class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-2">
                                        <button type="button" @click="showCreateManagerModal = false" :disabled="submitting"
                                                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                                            Annuler
                                        </button>
                                        <button type="submit" :disabled="submitting"
                                                class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer shadow-sm">
                                            <span x-show="submitting">Création…</span>
                                            <span x-show="!submitting">Créer le manager</span>
                                        </button>
                                    </div>
                                </form>
                            </template>

                            <!-- Succès : identifiants générés -->
                            <template x-if="generatedPassword">
                                <div class="p-6 space-y-4">
                                    <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-xs font-semibold text-emerald-700 flex items-center gap-2">
                                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                        <span>Manager créé. Transmets-lui ces identifiants — le mot de passe ne sera plus jamais affiché.</span>
                                    </div>
                                    <div class="space-y-2">
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Email</label>
                                            <div class="font-mono text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800" x-text="email"></div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Mot de passe généré</label>
                                            <div class="flex items-center gap-2">
                                                <div class="font-mono text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 flex-1" x-text="generatedPassword"></div>
                                                <button type="button"
                                                        @click="navigator.clipboard.writeText(generatedPassword); $el.textContent = 'Copié !'; setTimeout(() => $el.textContent = 'Copier', 1500)"
                                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-[10px] font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer shrink-0">
                                                    Copier
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Accès applicatif</label>
                                            <a href="http://localhost:{{ $tenant->app_port }}/login" target="_blank" class="font-mono text-xs text-indigo-600 hover:text-indigo-800 hover:underline">
                                                http://localhost:{{ $tenant->app_port }}/login
                                            </a>
                                        </div>
                                    </div>
                                    <div class="flex justify-end pt-2">
                                        <button type="button" @click="showCreateManagerModal = false; window.location.reload();"
                                                class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition cursor-pointer shadow-sm">
                                            Terminer
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Create Controller Modal -->
                    <div x-show="showCreateControllerModal"
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         x-cloak>
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden max-w-md w-full"
                             @click.away="if (!submittingController) { showCreateControllerModal = generatedPasswordController ? showCreateControllerModal : false }">

                            <!-- Header -->
                            <div class="bg-slate-950 px-6 py-5 flex items-center gap-3">
                                <div class="rounded-lg bg-teal-500/20 p-2">
                                    <svg class="h-5 w-5 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-white tracking-wide">Créer un Contrôleur de Gestion / Auditeur — <span class="font-mono">{{ $tenant->slug }}</span></h3>
                            </div>

                            <!-- Formulaire (avant création) -->
                            <template x-if="!generatedPasswordController">
                                <form @submit.prevent="submitController()" class="p-6 space-y-4">
                                    <p class="text-xs text-slate-600 leading-relaxed">
                                        Crée un compte avec le rôle <strong>controller</strong> rattaché à cet établissement. Ce compte disposera des accès au module Wetchah_GRC. Un mot de passe sera généré automatiquement.
                                    </p>

                                    <template x-if="errorMsgController">
                                        <div class="rounded-lg bg-red-50 border border-red-150 p-3 text-xs font-semibold text-red-700" x-text="errorMsgController"></div>
                                    </template>

                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Nom complet <span class="text-red-400">*</span></label>
                                        <input type="text" x-model="nameController" required placeholder="Ex: Paul Atangana"
                                               class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-700 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Adresse e-mail <span class="text-red-400">*</span></label>
                                        <input type="email" x-model="emailController" required placeholder="controleur@etablissement.com"
                                               class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-700 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Téléphone</label>
                                        <input type="text" x-model="phoneController" placeholder="+237 600 000 000"
                                               class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-700 outline-none focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 transition">
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-2">
                                        <button type="button" @click="showCreateControllerModal = false" :disabled="submittingController"
                                                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                                            Annuler
                                        </button>
                                        <button type="submit" :disabled="submittingController"
                                                class="rounded-lg bg-teal-600 px-4 py-2 text-xs font-bold text-white hover:bg-teal-700 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer shadow-sm">
                                            <span x-show="submittingController">Création…</span>
                                            <span x-show="!submittingController">Créer le contrôleur</span>
                                        </button>
                                    </div>
                                </form>
                            </template>

                            <!-- Succès (mot de passe affiché) -->
                            <template x-if="generatedPasswordController">
                                <div class="p-6 space-y-4">
                                    <div class="rounded-lg bg-emerald-50 border border-emerald-150 p-4 text-xs text-emerald-800 space-y-1">
                                        <div class="font-bold text-sm text-emerald-900">✅ Contrôleur de gestion créé avec succès !</div>
                                        <p class="text-emerald-700 leading-relaxed">
                                            Copiez ces identifiants dès maintenant. Le mot de passe ne sera plus jamais affiché.
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Email</label>
                                            <div class="font-mono text-xs bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800" x-text="emailController"></div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Mot de passe généré</label>
                                            <div class="flex items-center gap-2">
                                                <div class="font-mono text-sm bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-slate-800 flex-1" x-text="generatedPasswordController"></div>
                                                <button type="button"
                                                        @click="navigator.clipboard.writeText(generatedPasswordController); $el.textContent = 'Copié !'; setTimeout(() => $el.textContent = 'Copier', 1500)"
                                                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-[10px] font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer shrink-0">
                                                    Copier
                                                </button>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Accès Portail GRC</label>
                                            <a href="{{ $tenant->grcUrl() }}/login" target="_blank" class="font-mono text-xs text-teal-600 hover:text-teal-800 hover:underline">
                                                {{ $tenant->grcUrl() }}/login
                                            </a>
                                        </div>
                                    </div>
                                    <div class="flex justify-end pt-2">
                                        <button type="button" @click="showCreateControllerModal = false; window.location.reload();"
                                                class="rounded-lg bg-teal-600 px-4 py-2 text-xs font-bold text-white hover:bg-teal-700 transition cursor-pointer shadow-sm">
                                            Terminer
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-5 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom</th>
                                <th class="px-5 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Email</th>
                                <th class="px-5 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Accès</th>
                                <th class="px-5 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase">Statut</th>
                                <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($tenantUsers as $user)
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => $user->id]) }}"
                                           class="font-semibold text-slate-800 hover:text-indigo-600 hover:underline">
                                            {{ $user->name }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600 font-mono">{{ $user->email }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap gap-1">
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-700">
                                                {{ $user->role }}
                                            </span>
                                            {{-- Rôles du pivot, avec leur niveau : c'est ce que
                                                 wetchah_app applique réellement par module. --}}
                                            @foreach($user->roles ?? [] as $r)
                                                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium
                                                    {{ $r['level'] === 'read' ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}"
                                                    title="{{ $r['module'] }} — {{ $r['level'] === 'read' ? 'lecture seule' : 'lecture/écriture' }}">
                                                    {{ $r['name'] }}
                                                    <span class="opacity-60">{{ $r['level'] === 'read' ? 'L' : 'L/É' }}</span>
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $user->is_active ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $user->is_active ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                            {{ $user->is_active ? 'Actif' : 'Inactif' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @include('admin.tenants.partials.user-menu', ['tenant' => $tenant, 'user' => $user])
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-sm text-slate-400">
                                        Aucun utilisateur rattaché à cet établissement.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @include('admin.tenants.partials.user-edit-modal', ['tenant' => $tenant, 'tenantRoles' => $tenantRoles ?? collect()])

            {{-- ==================== THÈME ==================== --}}
            @elseif($section === 'theme')
                <div class="mb-6">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Thème & Couleurs</h2>
                    <p class="text-xs text-slate-500 mt-1">Personnalisez l'apparence de l'interface de {{ $tenant->name }}</p>
                </div>

                <form action="{{ route('tech.establishments.update', $tenant) }}" method="POST" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" x-data="{
                    p: '{{ $tenant->settings['theme']['primary'] ?? '#391F0E' }}',
                    s: '{{ $tenant->settings['theme']['secondary'] ?? '#CCAB87' }}',
                    a: '{{ $tenant->settings['theme']['accent'] ?? '#EED4A3' }}',
                    d: '{{ $tenant->settings['theme']['dark'] ?? '#0F0201' }}',
                    sd: '{{ $tenant->settings['theme']['surface_dark'] ?? '#2C1810' }}',
                    tl: '{{ $tenant->settings['theme']['text_on_light'] ?? '#391F0E' }}',
                    td: '{{ $tenant->settings['theme']['text_on_dark'] ?? '#CCAB87' }}'
                }">
                    @csrf
                    <!-- Hidden fields to keep existing data -->
                    <input type="hidden" name="name" value="{{ $tenant->name }}">
                    <input type="hidden" name="slug" value="{{ $tenant->slug }}">
                    <input type="hidden" name="currency" value="{{ $tenant->currency }}">

                    <div class="p-6 space-y-6">
                        <!-- Live Preview -->
                        <div class="rounded-xl border border-slate-200 overflow-hidden">
                            <div class="px-4 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase bg-slate-50 border-b border-slate-200">Aperçu en temps réel</div>
                            <div class="p-4 flex items-center gap-4">
                                <div class="rounded-lg overflow-hidden shadow-md border border-slate-200 w-52 shrink-0">
                                    <div class="h-12 flex items-center justify-center" :style="'background-color:' + p">
                                        <span class="text-[10px] font-bold tracking-widest uppercase" :style="'color:' + td">{{ $tenant->name }}</span>
                                    </div>
                                    <div class="h-7 flex items-center justify-center" :style="'background-color:' + s">
                                        <span class="text-[9px] font-semibold" :style="'color:' + tl">Navigation</span>
                                    </div>
                                    <div class="h-12 bg-white flex items-center justify-center">
                                        <span class="text-[9px] text-slate-400">Contenu principal</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Color Pickers -->
                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Couleur Primaire</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="p" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[primary]" x-model="p" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Couleur Secondaire</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="s" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[secondary]" x-model="s" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 uppercase">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Accent</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="a" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[accent]" x-model="a" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Fond Sombre</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="d" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[dark]" x-model="d" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Surface Sombre</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="sd" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[surface_dark]" x-model="sd" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none uppercase">
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Texte sur Fond Clair</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="tl" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[text_on_light]" x-model="tl" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Texte sur Fond Sombre</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="td" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[text_on_dark]" x-model="td" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none uppercase">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex justify-end">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                            Enregistrer le thème
                        </button>
                    </div>
                </form>

            {{-- ==================== MODULES ==================== --}}
            @elseif($section === 'modules')
                @php
                    $moduleDefs = [
                        'restaurant'   => ['label' => 'Restaurant', 'desc' => 'Menus, commandes, facturation, portail QR, garde-manger.', 'icon' => 'utensils'],
                        'shop'         => ['label' => 'Boutique', 'desc' => 'Articles, point de vente, gestion de caisse.', 'icon' => 'store'],
                        'housekeeping' => ['label' => 'Housekeeping', 'desc' => 'Planification et suivi du nettoyage des chambres.', 'icon' => 'brush-cleaning'],
                        'discussions'  => ['label' => 'Discussions', 'desc' => 'Messagerie interne entre membres du personnel.', 'icon' => 'message-circle'],
                        'analytics'    => ['label' => 'Analytics', 'desc' => 'Tour de contrôle : statistiques et tableaux de bord.', 'icon' => 'chart-column'],
                        'ledger'       => ['label' => 'Comptabilité avancée', 'desc' => 'Grand livre SYSCOHADA : plan de comptes, journaux, balance, clôture, comptes de tiers et lettrage, factures fournisseurs et retenues à la source, analytique. La comptabilité de caisse reste active sans ce module.', 'icon' => 'book-open'],
                        'api'          => ['label' => 'API d\'intégration', 'desc' => 'Expose des routes API sécurisées pour alimenter le site vitrine, le portail GRC et connecter des applications tierces.', 'icon' => 'plug'],
                        'website'      => ['label' => 'Site web', 'desc' => 'Site vitrine public (chambres, menu, contenu CMS) — provisionne un 3ᵉ container. Nécessite l\'API d\'intégration active.', 'icon' => 'globe'],
                        'grc'          => ['label' => 'Contrôle de gestion & GRC', 'desc' => 'Plateforme de Gouvernance, Risques & Conformité (Wetchah_GRC) — provisionne un 4ᵉ container dédié. Nécessite l\'API d\'intégration active.', 'icon' => 'shield-check'],
                    ];
                    $tenantModules = $tenant->modules ?? [];
                    // Établissement jamais passé par ce sélecteur (aucune des clés
                    // canoniques présente) : tout est actif aujourd'hui côté
                    // application, on part de cet état plutôt que de tout décocher.
                    $legacyAllEnabled = empty(array_intersect(array_keys($moduleDefs), $tenantModules));
                @endphp
                <div class="mb-6">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Modules</h2>
                    <p class="text-xs text-slate-500 mt-1">Active ou désactive des fonctionnalités métier pour {{ $tenant->name }}. Les modules cœur (Chambres, Réservations, Clients, Utilisateurs) restent toujours actifs.</p>
                </div>

                <form action="{{ route('tech.establishments.modules', $tenant) }}" method="POST" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden"
                    x-data="{
                        modules: {
                            @foreach($moduleDefs as $key => $def)
                            {{ $key }}: {{ ($legacyAllEnabled || in_array($key, $tenantModules)) ? 'true' : 'false' }},
                            @endforeach
                        },
                        toggleModuleDependency(name) {
                            // Site web ou GRC nécessite l'API d'intégration : l'activer force l'API
                            if ((name === 'website' && this.modules.website) || (name === 'grc' && this.modules.grc)) {
                                this.modules.api = true;
                            }
                            // Désactiver l'API désactive automatiquement le site web et le GRC
                            if (name === 'api' && !this.modules.api) {
                                this.modules.website = false;
                                this.modules.grc = false;
                            }
                        }
                    }">
                    @csrf
                    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach($moduleDefs as $key => $def)
                            <label class="relative flex flex-col gap-3 rounded-xl border p-4 cursor-pointer select-none transition hover:bg-slate-50"
                                   :class="modules.{{ $key }} ? 'border-indigo-600 ring-2 ring-indigo-50 bg-indigo-50/10' : 'border-slate-200'">
                                {{-- Case à cocher d'UI uniquement (sans name : elle ne se soumet pas).
                                     La soumission passe par le champ caché ci-dessous, présent
                                     seulement quand le module est activé — évite l'ambiguïté d'une
                                     case liée en x-model, qui empêchait la désactivation de prendre. --}}
                                <input type="checkbox" hidden
                                       x-model="modules.{{ $key }}"
                                       @if(in_array($key, ['api', 'website', 'grc'], true)) @change="toggleModuleDependency('{{ $key }}')" @endif>
                                <template x-if="modules.{{ $key }}">
                                    <input type="hidden" name="modules[]" value="{{ $key }}">
                                </template>
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg transition"
                                         :class="modules.{{ $key }} ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400'">
                                        <i data-lucide="{{ $def['icon'] }}" class="h-5 w-5"></i>
                                    </div>
                                    <div class="mt-1.5 h-5 w-9 shrink-0 rounded-full transition-colors"
                                         :class="modules.{{ $key }} ? 'bg-indigo-600' : 'bg-slate-200'">
                                        <div class="h-4 w-4 mt-0.5 rounded-full bg-white shadow transition-transform"
                                             :class="modules.{{ $key }} ? 'translate-x-[18px]' : 'translate-x-0.5'"></div>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <span class="text-xs font-bold text-slate-800">{{ $def['label'] }}</span>
                                    <p class="text-[10px] text-slate-500 leading-relaxed">{{ $def['desc'] }}</p>

                                    @if($key === 'grc')
                                        <template x-if="modules.grc">
                                            <div class="pt-2" @click.stop>
                                                <a href="{{ $tenant->grcUrl() }}" target="_blank"
                                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-teal-50 border border-teal-200 text-[10px] font-bold text-teal-700 hover:bg-teal-100 transition shadow-2xs">
                                                    <svg class="h-3 w-3 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                                    <span>Ouvrir la plateforme GRC (:{{ $tenant->resolvedGrcPort() }}) ↗</span>
                                                </a>
                                            </div>
                                        </template>
                                    @elseif($key === 'website' && $tenant->websiteUrl())
                                        <template x-if="modules.website">
                                            <div class="pt-2" @click.stop>
                                                <a href="{{ $tenant->websiteUrl() }}" target="_blank"
                                                   class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-rose-50 border border-rose-200 text-[10px] font-bold text-rose-700 hover:bg-rose-100 transition shadow-2xs">
                                                    <svg class="h-3 w-3 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                                                    <span>Ouvrir le Site Web ↗</span>
                                                </a>
                                            </div>
                                        </template>
                                    @endif
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-between gap-4">
                        <p class="text-[10px] text-slate-400">Appliquer recrée le container applicatif (même version, base de données intacte) — quelques secondes d'interruption. Activer « Site web » pour la première fois télécharge son image, ce qui peut prendre plus de temps.</p>
                        <button type="submit" class="shrink-0 rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                            Appliquer les modules
                        </button>
                    </div>
                </form>

            {{-- ==================== CONTENU DU SITE (CMS) ==================== --}}
            @elseif($section === 'site-content')
                @include('admin.tenants.partials.site-editors', ['tenant' => $tenant])

                @include('admin.tenants.partials.site-content-form', [
                    'tenant'    => $tenant,
                    'actionUrl' => route('tech.establishments.site-content', $tenant),
                ])


            {{-- ==================== PARAMÈTRES (SETTINGS) ==================== --}}
            @elseif($section === 'settings')
                <div class="mb-6">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Paramètres</h2>
                    <p class="text-xs text-slate-500 mt-1">Gérer les configurations et la suppression de l'établissement {{ $tenant->name }}</p>
                </div>

                <div class="space-y-6" x-data="{
                    showDeleteModal: false, confirmSlug: '',
                    showUpdateModal: false, availableTags: [], selectedTag: '', loadingTags: false, updating: false,
                    showWebModal: false, webUpdating: false,
                    showGrcModal: false, grcUpdating: false
                }">
                    <!-- Technical details card (read-only) -->
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                            <div class="rounded-lg bg-indigo-600/20 p-2">
                                <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-white tracking-wide">Configuration de l'instance</h3>
                                <p class="text-[10px] text-slate-400">Paramètres de base et infrastructure associée</p>
                            </div>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs border-b border-slate-100">
                            <div class="space-y-3">
                                <div>
                                    <span class="text-slate-450 font-semibold">Identifiant système (Slug) :</span>
                                    <span class="font-mono bg-slate-50 border border-slate-200 px-2 py-0.5 rounded ml-2">{{ $tenant->slug }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-450 font-semibold">Port applicatif (Hôte) :</span>
                                    <a href="http://localhost:{{ $tenant->app_port }}" target="_blank" class="font-mono bg-slate-50 border border-slate-200 px-2 py-0.5 rounded ml-2 text-indigo-600 hover:text-indigo-800 hover:underline">{{ $tenant->app_port }}</a>
                                </div>
                                <div>
                                    <span class="text-slate-450 font-semibold">Port PostgreSQL (Hôte) :</span>
                                    <span class="font-mono bg-slate-50 border border-slate-200 px-2 py-0.5 rounded ml-2">{{ $tenant->db_port ?? 5432 }}</span>
                                </div>
                                @if($tenant->hasGrc())
                                <div>
                                    <span class="text-slate-450 font-semibold">Port GRC (Hôte) :</span>
                                    <a href="{{ $tenant->grcUrl() }}" target="_blank" class="font-mono bg-teal-50 border border-teal-200 px-2 py-0.5 rounded ml-2 text-teal-700 hover:text-teal-900 hover:underline">{{ $tenant->resolvedGrcPort() }} (Portail GRC ↗)</a>
                                </div>
                                @endif
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <span class="text-slate-450 font-semibold">Base de données :</span>
                                    <span class="font-mono ml-2">{{ $tenant->db_name }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-450 font-semibold">Utilisateur DB :</span>
                                    <span class="font-mono ml-2">{{ $tenant->db_username ?? 'pms' }}</span>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Version Card -->
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100">
                            <h3 class="text-sm font-bold text-slate-800">Version de l'application</h3>
                            <p class="text-[10px] text-slate-500 mt-0.5">Image Docker (GHCR) figée pour cet établissement</p>
                        </div>
                        <div class="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
                            <div class="text-xs">
                                <span class="text-slate-450 font-semibold">Digest actuel :</span>
                                <span class="font-mono bg-slate-50 border border-slate-200 px-2 py-0.5 rounded ml-2">
                                    {{ $tenant->docker_image_tag ? \Illuminate\Support\Str::limit($tenant->docker_image_tag, 22, '…') : 'Non résolu' }}
                                </span>
                            </div>
                            <button type="button"
                                    @click="showUpdateModal = true; loadingTags = true; fetch('{{ route('tech.establishments.versions', $tenant) }}').then(r => r.json()).then(data => { availableTags = data.tags; selectedTag = data.tags[0] ?? ''; loadingTags = false; })"
                                    class="shrink-0 rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm cursor-pointer">
                                Vérifier les mises à jour
                            </button>
                        </div>
                    </div>

                    {{-- Version du site vitrine (module website uniquement) --}}
                    @if(in_array('website', $tenant->modules ?? []))
                        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100">
                                <h3 class="text-sm font-bold text-slate-800">Version du site vitrine</h3>
                                <p class="text-[10px] text-slate-500 mt-0.5">Image Docker du site public, figée pour cet établissement — mise à jour vers la dernière version publiée</p>
                            </div>
                            <div class="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
                                <div class="text-xs">
                                    <span class="text-slate-450 font-semibold">Digest actuel :</span>
                                    <span class="font-mono bg-slate-50 border border-slate-200 px-2 py-0.5 rounded ml-2">
                                        {{ $tenant->web_image_tag ? \Illuminate\Support\Str::limit($tenant->web_image_tag, 22, '…') : 'Non résolu' }}
                                    </span>
                                </div>
                                <button type="button"
                                        @click="showWebModal = true"
                                        class="shrink-0 rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm cursor-pointer">
                                    Mettre à jour le site
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- Version du module GRC (module grc uniquement) --}}
                    @if(in_array('grc', $tenant->modules ?? []))
                        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b border-slate-100">
                                <h3 class="text-sm font-bold text-slate-800">Version du module GRC</h3>
                                <p class="text-[10px] text-slate-500 mt-0.5">Image Docker du contrôle de gestion, des risques et de la conformité, figée pour cet établissement — mise à jour vers la dernière version publiée</p>
                            </div>
                            <div class="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
                                <div class="text-xs">
                                    <span class="text-slate-450 font-semibold">Digest actuel :</span>
                                    <span class="font-mono bg-slate-50 border border-slate-200 px-2 py-0.5 rounded ml-2">
                                        {{ $tenant->grc_image_tag ? \Illuminate\Support\Str::limit($tenant->grc_image_tag, 22, '…') : 'Non résolu' }}
                                    </span>
                                </div>
                                <button type="button"
                                        @click="showGrcModal = true"
                                        class="shrink-0 rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm cursor-pointer">
                                    Mettre à jour le GRC
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- Données de démonstration --}}
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-100">
                            <h3 class="text-sm font-bold text-slate-800">Données de démonstration</h3>
                            <p class="text-[10px] text-slate-500 mt-0.5">
                                Peuple l'établissement d'environ 20 enregistrements fictifs par module actif —
                                clients, chambres, réservations, folios, commandes, stock et charges
                            </p>
                        </div>
                        <div class="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
                            <div class="text-xs space-y-1.5">
                                @if($demoDataInstalled)
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                        <span class="font-semibold text-slate-700">Jeu de démonstration déjà présent</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 leading-relaxed max-w-lg">
                                        Relancer l'action ne crée aucun doublon : seuls les enregistrements manquants
                                        sont ajoutés. Utile après l'activation d'un nouveau module.
                                    </p>
                                @else
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-2 w-2 rounded-full bg-slate-300"></span>
                                        <span class="font-semibold text-slate-700">Aucune donnée de démonstration</span>
                                    </div>
                                    <p class="text-[10px] text-amber-700 leading-relaxed max-w-lg font-medium">
                                        Ces données se mêlent aux données réelles : à réserver aux établissements
                                        de démonstration ou de formation. Le retrait reste possible ensuite, mais
                                        épargne tout ce qui aura servi entre-temps.
                                    </p>
                                @endif
                            </div>

                            {{-- Mêmes classes que les autres boutons d'action de cet
                                 écran : la feuille de style compilée ne contient que
                                 les utilitaires déjà employés dans le projet, et une
                                 teinte inédite laisserait un bouton sans fond — donc
                                 un libellé blanc sur blanc, invisible. --}}
                            @if($tenant->docker_status === 'running')
                                <div class="shrink-0 flex items-center gap-2">
                                    <form method="POST" action="{{ route('tech.establishments.demo-data', $tenant) }}"
                                          x-data="{ envoi: false }"
                                          @submit="envoi = true">
                                        @csrf
                                        <button type="submit"
                                                :disabled="envoi"
                                                class="shrink-0 rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm cursor-pointer">
                                            <span x-show="!envoi">
                                                {{ $demoDataInstalled ? 'Compléter le jeu' : 'Installer les données' }}
                                            </span>
                                            <span x-show="envoi" style="display:none;">Installation…</span>
                                        </button>
                                    </form>

                                    {{-- Purge : proposée seulement s'il y a quelque chose à
                                         retirer, et confirmée en deux temps. Une suppression
                                         dans une base en service ne doit pas tenir à un clic
                                         mal placé. --}}
                                    @if($demoDataInstalled)
                                        <form method="POST" action="{{ route('tech.establishments.demo-data.purge', $tenant) }}"
                                              x-data="{ confirme: false, envoi: false }"
                                              @submit="envoi = true">
                                            @csrf
                                            @method('DELETE')

                                            <button type="button" x-show="!confirme" @click="confirme = true"
                                                    class="shrink-0 rounded-lg border border-red-200 bg-red-50 px-5 py-2.5 text-xs font-bold text-red-700 hover:bg-red-100 transition cursor-pointer">
                                                Purger
                                            </button>

                                            <span x-show="confirme" style="display:none;" class="flex items-center gap-2">
                                                <button type="submit" :disabled="envoi"
                                                        class="shrink-0 rounded-lg bg-red-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-red-700 transition shadow-sm cursor-pointer">
                                                    <span x-show="!envoi">Confirmer la purge</span>
                                                    <span x-show="envoi" style="display:none;">Retrait…</span>
                                                </button>
                                                <button type="button" @click="confirme = false"
                                                        class="text-xs font-bold text-slate-500 hover:text-slate-700 cursor-pointer">
                                                    Annuler
                                                </button>
                                            </span>
                                        </form>
                                    @endif
                                </div>
                            @else
                                <span class="shrink-0 rounded-lg bg-slate-300 px-5 py-2.5 text-xs font-bold text-slate-500 cursor-not-allowed"
                                      title="Le container de l'établissement doit être démarré">
                                    Container arrêté
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Danger Zone Card -->
                    <div class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden">
                        <div class="bg-red-500/5 px-6 py-4 flex items-center gap-3 border-b border-red-100">
                            <div class="rounded-lg bg-red-100 p-2">
                                <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-red-850 tracking-wide font-heading">Zone de Danger</h3>
                                <p class="text-[10px] text-red-500/80">Actions irréversibles et critiques</p>
                            </div>
                        </div>
                        <div class="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
                            <div class="max-w-lg">
                                <h4 class="text-xs font-bold text-slate-800">Supprimer cet établissement</h4>
                                <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">
                                    Cette opération arrêtera et supprimera définitivement les conteneurs Docker (applicatif et base de données) ainsi que toutes les données associées de l'établissement. L'enregistrement système SQLite sera également supprimé.
                                </p>
                            </div>
                            <button @click="showDeleteModal = true" type="button" class="shrink-0 rounded-lg bg-red-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-red-700 transition shadow-sm hover:shadow-md cursor-pointer">
                                Supprimer l'établissement
                            </button>
                        </div>
                    </div>

                    <!-- Alpine.js Confirmation Modal -->
                    <div x-show="showDeleteModal" 
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         x-cloak>
                        
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden max-w-md w-full"
                             @click.away="showDeleteModal = false; confirmSlug = ''"
                             x-transition:enter="transition ease-out duration-300 transform scale-95"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-200 transform scale-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">
                            
                            <!-- Header -->
                            <div class="bg-slate-950 px-6 py-5 flex items-center gap-3">
                                <div class="rounded-lg bg-red-500/20 p-2">
                                    <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-white tracking-wide">Confirmation de suppression</h3>
                            </div>

                            <!-- Form -->
                            <form action="{{ route('tech.establishments.destroy', $tenant) }}" method="POST">
                                @csrf
                                @method('DELETE')

                                <div class="p-6 space-y-4">
                                    <p class="text-xs text-slate-600 leading-relaxed">
                                        Êtes-vous absolument sûr ? Cette action est définitive.
                                        Pour confirmer la suppression de <strong>{{ $tenant->name }}</strong>, veuillez saisir son identifiant unique <strong><span class="font-mono bg-slate-50 border border-slate-200 px-1 rounded">{{ $tenant->slug }}</span></strong> ci-dessous :
                                    </p>

                                    <div>
                                        <input type="text" x-model="confirmSlug" placeholder="Ex: {{ $tenant->slug }}"
                                               class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-700 font-mono outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 transition uppercase">
                                    </div>
                                </div>

                                <!-- Footer Actions -->
                                <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3">
                                    <button @click="showDeleteModal = false; confirmSlug = ''" type="button" 
                                            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                                        Annuler
                                    </button>
                                    <button type="submit" 
                                            :disabled="confirmSlug.trim().toLowerCase() !== '{{ $tenant->slug }}'"
                                            class="rounded-lg bg-red-600 px-4 py-2 text-xs font-bold text-white hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer shadow-sm shadow-red-200">
                                        Confirmer la suppression
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Update Version Modal -->
                    <div x-show="showUpdateModal"
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         x-cloak>

                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden max-w-lg w-full"
                             @click.away="if (!updating) { showUpdateModal = false }"
                             x-transition:enter="transition ease-out duration-300 transform scale-95"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-200 transform scale-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">

                            <!-- Header -->
                            <div class="bg-slate-950 px-6 py-5 flex items-center gap-3">
                                <div class="rounded-lg bg-indigo-500/20 p-2">
                                    <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-white tracking-wide">Mettre à jour la version</h3>
                            </div>

                            <!-- Form (avant lancement) -->
                            <div class="p-6 space-y-4" x-show="!updating">
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Le container applicatif sera recréé avec la nouvelle image. La base de données et son volume ne sont pas affectés. Les migrations s'exécutent automatiquement au démarrage.
                                </p>

                                <div x-show="loadingTags" class="text-xs text-slate-400 italic">Chargement des versions disponibles…</div>

                                <div x-show="!loadingTags">
                                    <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Version cible</label>
                                    <select x-model="selectedTag" class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                        <template x-for="t in availableTags" :key="t">
                                            <option :value="t" x-text="t === 'latest' ? 'latest (dernière version publiée)' : t"></option>
                                        </template>
                                    </select>
                                    <p x-show="availableTags.length === 0" class="text-xs text-red-500 mt-2">Aucune version disponible sur le registre.</p>
                                </div>
                            </div>

                            <!-- Logs en direct (pendant la mise à jour) -->
                            <div x-show="updating" class="p-6">
                                <div id="update-log-output" class="font-mono text-xs text-slate-300 space-y-1.5 bg-slate-950/70 rounded-lg p-5 border border-slate-850 overflow-y-auto h-64" style="word-break: break-word; overflow-wrap: break-word;"></div>
                                <div class="mt-3 flex justify-end">
                                    <button type="button" onclick="copyLogText('update-log-output', this)"
                                            class="rounded border border-slate-700 bg-slate-800 px-2.5 py-1 text-[10px] font-bold text-slate-300 hover:bg-slate-700 hover:text-white transition cursor-pointer">
                                        Copier les logs
                                    </button>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3">
                                <button @click="showUpdateModal = false" type="button" x-show="!updating"
                                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                                    Annuler
                                </button>
                                <button type="button" x-show="!updating"
                                        :disabled="!selectedTag"
                                        @click="updating = true; startUpdateStream(selectedTag)"
                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer shadow-sm">
                                    Lancer la mise à jour
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Update Website Modal -->
                    <div x-show="showWebModal"
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         x-cloak>

                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden max-w-lg w-full"
                             @click.away="if (!webUpdating) { showWebModal = false }"
                             x-transition:enter="transition ease-out duration-300 transform scale-95"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-200 transform scale-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">

                            <!-- Header -->
                            <div class="bg-slate-950 px-6 py-5 flex items-center gap-3">
                                <div class="rounded-lg bg-indigo-500/20 p-2">
                                    <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-white tracking-wide">Mettre à jour le site vitrine</h3>
                            </div>

                            <!-- Confirmation (avant lancement) -->
                            <div class="p-6 space-y-4" x-show="!webUpdating">
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Le container du site public sera recréé avec la dernière image publiée sur le registre (tag « latest »). Le contenu marketing et la configuration de l'établissement ne sont pas affectés. Si le site est déjà à jour, aucune action n'est effectuée.
                                </p>
                            </div>

                            <!-- Logs en direct (pendant la mise à jour) -->
                            <div x-show="webUpdating" class="p-6">
                                <div id="web-update-log-output" class="font-mono text-xs text-slate-300 space-y-1.5 bg-slate-950/70 rounded-lg p-5 border border-slate-850 overflow-y-auto h-64" style="word-break: break-word; overflow-wrap: break-word;"></div>
                                <div class="mt-3 flex justify-end">
                                    <button type="button" onclick="copyLogText('web-update-log-output', this)"
                                            class="rounded border border-slate-700 bg-slate-800 px-2.5 py-1 text-[10px] font-bold text-slate-300 hover:bg-slate-700 hover:text-white transition cursor-pointer">
                                        Copier les logs
                                    </button>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3">
                                <button @click="showWebModal = false" type="button" x-show="!webUpdating"
                                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                                    Annuler
                                </button>
                                <button type="button" x-show="!webUpdating"
                                        @click="webUpdating = true; startWebsiteUpdateStream()"
                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition cursor-pointer shadow-sm">
                                    Lancer la mise à jour
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Update GRC Modal -->
                    <div x-show="showGrcModal"
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         x-cloak>

                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden max-w-lg w-full"
                             @click.away="if (!grcUpdating) { showGrcModal = false }"
                             x-transition:enter="transition ease-out duration-300 transform scale-95"
                             x-transition:enter-start="opacity-0 scale-95"
                             x-transition:enter-end="opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-200 transform scale-100"
                             x-transition:leave-start="opacity-100 scale-100"
                             x-transition:leave-end="opacity-0 scale-95">

                            <!-- Header -->
                            <div class="bg-slate-950 px-6 py-5 flex items-center gap-3">
                                <div class="rounded-lg bg-indigo-500/20 p-2">
                                    <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </div>
                                <h3 class="text-sm font-bold text-white tracking-wide">Mettre à jour le module GRC</h3>
                            </div>

                            <!-- Confirmation (avant lancement) -->
                            <div class="p-6 space-y-4" x-show="!grcUpdating">
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Le container du module GRC sera recréé avec la dernière image publiée sur le registre (tag « latest »). Les risques, missions d'audit, politiques et incidents déjà saisis sont conservés — ils vivent dans un volume distinct du container. Si le module est déjà à jour, aucune action n'est effectuée.
                                </p>
                            </div>

                            <!-- Logs en direct (pendant la mise à jour) -->
                            <div x-show="grcUpdating" class="p-6">
                                <div id="grc-update-log-output" class="font-mono text-xs text-slate-300 space-y-1.5 bg-slate-950/70 rounded-lg p-5 border border-slate-850 overflow-y-auto h-64" style="word-break: break-word; overflow-wrap: break-word;"></div>
                                <div class="mt-3 flex justify-end">
                                    <button type="button" onclick="copyLogText('grc-update-log-output', this)"
                                            class="rounded border border-slate-700 bg-slate-800 px-2.5 py-1 text-[10px] font-bold text-slate-300 hover:bg-slate-700 hover:text-white transition cursor-pointer">
                                        Copier les logs
                                    </button>
                                </div>
                            </div>

                            <!-- Footer Actions -->
                            <div class="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3">
                                <button @click="showGrcModal = false" type="button" x-show="!grcUpdating"
                                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                                    Annuler
                                </button>
                                <button type="button" x-show="!grcUpdating"
                                        @click="grcUpdating = true; startGrcUpdateStream()"
                                        class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition cursor-pointer shadow-sm">
                                    Lancer la mise à jour
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                function startUpdateStream(tag) {
                    const logOutput = document.getElementById('update-log-output');
                    logOutput.innerHTML = '';
                    const streamUrl = '{{ route("tech.establishments.update-version.stream", $tenant) }}?tag=' + encodeURIComponent(tag);
                    const evtSource = new EventSource(streamUrl);

                    evtSource.onmessage = function (event) {
                        try {
                            const data = JSON.parse(event.data);
                            const line = document.createElement('div');
                            line.className = 'flex gap-2.5 items-start py-0.5 border-b border-slate-900/10';

                            const timeSpan = document.createElement('span');
                            timeSpan.className = 'text-slate-500 shrink-0 font-semibold select-none';
                            timeSpan.textContent = `[${data.time}]`;

                            const msgSpan = document.createElement('span');
                            msgSpan.className = {
                                'success': 'text-emerald-400 font-semibold',
                                'error':   'text-red-400 font-semibold',
                                'warning': 'text-amber-400',
                                'info':    'text-slate-300',
                            }[data.level] || 'text-slate-300';
                            msgSpan.textContent = data.message;

                            line.appendChild(timeSpan);
                            line.appendChild(msgSpan);
                            logOutput.appendChild(line);
                            logOutput.scrollTop = logOutput.scrollHeight;

                            if (data.step === 'done' || data.step === 'finished') {
                                evtSource.close();
                                setTimeout(() => location.reload(), 1500);
                            }
                            if (data.level === 'error' || data.step === 'error') {
                                evtSource.close();
                            }
                        } catch (e) {
                            console.error('Error parsing update stream payload:', e);
                        }
                    };

                    evtSource.onerror = function () {
                        evtSource.close();
                    };
                }

                function startWebsiteUpdateStream() {
                    const logOutput = document.getElementById('web-update-log-output');
                    logOutput.innerHTML = '';
                    const streamUrl = '{{ route("tech.establishments.update-website.stream", $tenant) }}';
                    const evtSource = new EventSource(streamUrl);

                    evtSource.onmessage = function (event) {
                        try {
                            const data = JSON.parse(event.data);
                            const line = document.createElement('div');
                            line.className = 'flex gap-2.5 items-start py-0.5 border-b border-slate-900/10';

                            const timeSpan = document.createElement('span');
                            timeSpan.className = 'text-slate-500 shrink-0 font-semibold select-none';
                            timeSpan.textContent = `[${data.time}]`;

                            const msgSpan = document.createElement('span');
                            msgSpan.className = {
                                'success': 'text-emerald-400 font-semibold',
                                'error':   'text-red-400 font-semibold',
                                'warning': 'text-amber-400',
                                'info':    'text-slate-300',
                            }[data.level] || 'text-slate-300';
                            msgSpan.textContent = data.message;

                            line.appendChild(timeSpan);
                            line.appendChild(msgSpan);
                            logOutput.appendChild(line);
                            logOutput.scrollTop = logOutput.scrollHeight;

                            if (data.step === 'done' || data.step === 'finished') {
                                evtSource.close();
                                setTimeout(() => location.reload(), 1500);
                            }
                            if (data.level === 'error' || data.step === 'error') {
                                evtSource.close();
                            }
                        } catch (e) {
                            console.error('Error parsing website update stream payload:', e);
                        }
                    };

                    evtSource.onerror = function () {
                        evtSource.close();
                    };
                }

                /**
                 * Branche un flux SSE de mise à jour sur une zone de logs.
                 * Même rendu que les deux fonctions ci-dessus, factorisé :
                 * seules l'URL et la cible changent d'une mise à jour à l'autre.
                 */
                function streamUpdateLog(streamUrl, outputId, label) {
                    const logOutput = document.getElementById(outputId);
                    logOutput.innerHTML = '';
                    const evtSource = new EventSource(streamUrl);

                    evtSource.onmessage = function (event) {
                        try {
                            const data = JSON.parse(event.data);
                            const line = document.createElement('div');
                            line.className = 'flex gap-2.5 items-start py-0.5 border-b border-slate-900/10';

                            const timeSpan = document.createElement('span');
                            timeSpan.className = 'text-slate-500 shrink-0 font-semibold select-none';
                            timeSpan.textContent = `[${data.time}]`;

                            const msgSpan = document.createElement('span');
                            msgSpan.className = {
                                'success': 'text-emerald-400 font-semibold',
                                'error':   'text-red-400 font-semibold',
                                'warning': 'text-amber-400',
                                'info':    'text-slate-300',
                            }[data.level] || 'text-slate-300';
                            msgSpan.textContent = data.message;

                            line.appendChild(timeSpan);
                            line.appendChild(msgSpan);
                            logOutput.appendChild(line);
                            logOutput.scrollTop = logOutput.scrollHeight;

                            if (data.step === 'done' || data.step === 'finished') {
                                evtSource.close();
                                setTimeout(() => location.reload(), 1500);
                            }
                            if (data.level === 'error' || data.step === 'error') {
                                evtSource.close();
                            }
                        } catch (e) {
                            console.error(`Error parsing ${label} update stream payload:`, e);
                        }
                    };

                    evtSource.onerror = function () {
                        evtSource.close();
                    };
                }

                function startGrcUpdateStream() {
                    streamUpdateLog(
                        '{{ route("tech.establishments.update-grc.stream", $tenant) }}',
                        'grc-update-log-output',
                        'GRC'
                    );
                }
                </script>

            {{-- ==================== MODULES / SETTINGS (placeholder) ==================== --}}
            @else
                <div class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="rounded-2xl bg-slate-100 p-6 mb-5">
                        <svg class="h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17l-5.384-3.19A.745.745 0 015.65 11.6V5.98a.75.75 0 01.416-.672l5.384-3.19a.75.75 0 01.768 0l5.384 3.19c.255.151.416.42.416.672v5.62a.745.745 0 01-.378.38l-5.384 3.19a.75.75 0 01-.768 0zM12 9.75a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-700">Section en cours de développement</h3>
                    <p class="text-xs text-slate-400 mt-2 max-w-sm">Cette fonctionnalité sera disponible dans une prochaine mise à jour de la plateforme.</p>
                </div>
            @endif
        </main>
    </div>

    <script>
    function copyLogText(containerId, buttonEl) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const text = Array.from(container.children)
            .map(line => line.textContent)
            .join('\n');

        navigator.clipboard.writeText(text).then(() => {
            const original = buttonEl.textContent;
            buttonEl.textContent = 'Copié !';
            setTimeout(() => { buttonEl.textContent = original; }, 1500);
        }).catch(() => {
            const original = buttonEl.textContent;
            buttonEl.textContent = 'Échec de la copie';
            setTimeout(() => { buttonEl.textContent = original; }, 1500);
        });
    }
    </script>
</body>
</html>
