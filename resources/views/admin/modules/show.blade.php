{{--
    Fiche d'un module de l'application établissement.

    Attend : $module (entrée de ModuleCatalog + « slug »), $equipped
             (établissements équipés), $total (établissements).

    Page à part entière, comme le registre des propriétaires : le guide est
    long et son adresse est partageable telle quelle.
--}}
@php
    $accents = [
        'indigo'  => 'bg-indigo-50 text-indigo-600',
        'sky'     => 'bg-sky-50 text-sky-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber'   => 'bg-amber-50 text-amber-600',
        'violet'  => 'bg-violet-50 text-violet-600',
        'blue'    => 'bg-blue-50 text-blue-600',
        'rose'    => 'bg-rose-50 text-rose-600',
        'cyan'    => 'bg-cyan-50 text-cyan-600',
        'fuchsia' => 'bg-fuchsia-50 text-fuchsia-600',
        'slate'   => 'bg-slate-100 text-slate-600',
    ];
    $typeBadges = [
        'core'      => ['label' => 'Module cœur', 'classes' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
        'optionnel' => ['label' => 'Module optionnel', 'classes' => 'bg-amber-50 text-amber-700 border-amber-200'],
        'derive'    => ['label' => 'Module dérivé', 'classes' => 'bg-slate-50 text-slate-600 border-slate-200'],
        'config'    => ['label' => 'Sur configuration', 'classes' => 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200'],
    ];
    $accent = $accents[$module['accent']] ?? $accents['slate'];
    $badge  = $typeBadges[$module['type']] ?? $typeBadges['core'];
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $module['label'] }} — Modules</title>
    <meta name="description" content="{{ $module['tagline'] }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased font-body">

    <header class="sticky top-0 z-30 w-full bg-[#0f172a] border-b border-slate-800 text-white shadow-md">
        <div class="mx-auto px-5 lg:px-8 flex items-center justify-between h-14">
            <div class="flex items-center gap-4 min-w-0">
                <a href="{{ route('tech.dashboard', ['tab' => 'modules']) }}" class="flex items-center gap-2 text-slate-400 hover:text-white transition text-xs font-semibold shrink-0">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Modules
                </a>
                <div class="h-5 w-px bg-slate-700 shrink-0"></div>
                <h1 class="text-sm font-bold text-white leading-none truncate">{{ $module['label'] }}</h1>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-bold text-slate-300 hover:bg-slate-700 hover:text-white transition">
                    Déconnexion
                </button>
            </form>
        </div>
    </header>

    <main class="mx-auto w-full max-w-7xl px-5 py-8 lg:px-8">

        {{-- ==================== EN-TÊTE DU MODULE ==================== --}}
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl {{ $accent }}">
                    <i data-lucide="{{ $module['icon'] }}" class="h-7 w-7"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h2 class="font-display text-2xl font-bold text-slate-900">{{ $module['label'] }}</h2>
                        <span class="rounded border px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $badge['classes'] }}">
                            {{ $badge['label'] }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $module['tagline'] }}</p>
                    <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-slate-500">
                        <span class="inline-flex items-center gap-1.5">
                            <i data-lucide="log-in" class="h-3.5 w-3.5 text-slate-400"></i>
                            {{ $module['entry'] }}
                        </span>
                        @if($module['key'])
                            <span class="inline-flex items-center gap-1.5">
                                <i data-lucide="key-round" class="h-3.5 w-3.5 text-slate-400"></i>
                                <span class="font-mono text-[11px] text-slate-600">{{ $module['key'] }}</span>
                            </span>
                        @endif
                        @if(!empty($module['depends']))
                            <span class="inline-flex items-center gap-1.5 text-amber-700">
                                <i data-lucide="triangle-alert" class="h-3.5 w-3.5"></i>
                                {{ $module['depends'] }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- ==================== COLONNE PRINCIPALE ==================== --}}
            <div class="space-y-6 lg:col-span-2">

                {{-- Guide d'utilisation --}}
                <section id="guide" class="scroll-mt-20 rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <h3 class="text-sm font-bold text-slate-800">Guide d'utilisation</h3>
                        <p class="mt-0.5 text-[10px] text-slate-400">
                            Les gestes du module dans l'ordre où on les fait, tel qu'il fonctionne dans l'application.
                        </p>
                    </div>
                    <ol class="divide-y divide-slate-100">
                        @foreach($module['guide'] as $index => $step)
                            <li class="flex gap-4 px-6 py-5">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-xs font-bold text-indigo-600">
                                    {{ $index + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-800">{{ $step['title'] }}</p>
                                    <p class="mt-1.5 text-xs leading-relaxed text-slate-600">{{ $step['body'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                {{-- Écrans du module --}}
                <section id="ecrans" class="scroll-mt-20 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-6 py-4">
                        <h3 class="text-sm font-bold text-slate-800">Écrans du module</h3>
                        <p class="mt-0.5 text-[10px] text-slate-400">
                            Chemins relatifs à l'adresse de l'établissement — utiles pour guider un manager au téléphone.
                        </p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-slate-100 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                    <th class="px-6 py-3">Écran</th>
                                    <th class="px-3 py-3">Chemin</th>
                                    <th class="px-6 py-3">Rôle de l'écran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($module['screens'] as $screen)
                                    <tr class="align-top">
                                        <td class="px-6 py-3 font-bold text-slate-800 whitespace-nowrap">{{ $screen['label'] }}</td>
                                        <td class="px-3 py-3 font-mono text-[10px] text-slate-500 whitespace-nowrap">{{ $screen['path'] }}</td>
                                        <td class="px-6 py-3 leading-relaxed text-slate-500">{{ $screen['desc'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                {{-- Points de vigilance --}}
                @if(!empty($module['tips']))
                    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-6 py-4">
                            <h3 class="text-sm font-bold text-slate-800">À savoir</h3>
                        </div>
                        <ul class="space-y-3 px-6 py-5">
                            @foreach($module['tips'] as $tip)
                                <li class="flex gap-3">
                                    <i data-lucide="lightbulb" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500"></i>
                                    <span class="text-xs leading-relaxed text-slate-600">{{ $tip }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            {{-- ==================== COLONNE LATÉRALE ==================== --}}
            <div class="space-y-6">

                {{-- Activation --}}
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-bold text-slate-800">Activation</h3>
                    <p class="mt-2 text-xs leading-relaxed text-slate-600">
                        @if($module['type'] === 'core')
                            Livré avec toute application établissement. Ce module n'a pas de clé : il ne peut être ni
                            activé ni désactivé depuis l'ERP.
                        @elseif($module['type'] === 'derive')
                            Pas d'activation propre : il suit le module qui le porte, et disparaît avec lui.
                        @elseif($module['type'] === 'config')
                            Ne dépend pas du sélecteur de modules mais d'un réglage technique dans l'environnement du
                            container de l'établissement.
                        @else
                            Se coche depuis la fiche d'un établissement, onglet <span class="font-semibold">Modules</span>.
                            Appliquer recrée le container applicatif — quelques secondes d'interruption, base de données intacte.
                        @endif
                    </p>
                    @if($module['type'] === 'optionnel')
                        <a href="{{ route('tech.dashboard', ['tab' => 'tenants']) }}"
                           class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700">
                            <i data-lucide="building-2" class="h-3.5 w-3.5"></i>
                            Choisir un établissement
                        </a>
                    @endif
                </section>

                {{-- Fiche technique --}}
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="text-sm font-bold text-slate-800">Fiche technique</h3>
                    </div>
                    <dl class="divide-y divide-slate-100 text-xs">
                        <div class="flex items-center justify-between gap-4 px-5 py-3">
                            <dt class="text-slate-500">Identifiant</dt>
                            <dd class="font-mono text-[11px] text-slate-700">{{ $module['slug'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-5 py-3">
                            <dt class="text-slate-500">Clé TENANT_MODULES</dt>
                            <dd class="font-mono text-[11px] text-slate-700">{{ $module['key'] ?? '—' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 px-5 py-3">
                            <dt class="text-slate-500">Nature</dt>
                            <dd class="font-semibold text-slate-700">{{ $badge['label'] }}</dd>
                        </div>
                        <div class="px-5 py-3">
                            <dt class="text-slate-500">Rôles concernés</dt>
                            <dd class="mt-2 flex flex-wrap gap-1.5">
                                @foreach($module['roles'] as $role)
                                    <span class="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-[10px] text-slate-600">{{ $role }}</span>
                                @endforeach
                            </dd>
                        </div>
                    </dl>
                </section>

                {{-- Établissements équipés --}}
                <section id="etablissements" class="scroll-mt-20 rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-baseline justify-between gap-2 border-b border-slate-100 px-5 py-4">
                        <h3 class="text-sm font-bold text-slate-800">Établissements équipés</h3>
                        @if($module['type'] !== 'config')
                            <span class="text-xs font-extrabold text-slate-800">{{ count($equipped) }}<span class="font-semibold text-slate-400">/{{ $total }}</span></span>
                        @endif
                    </div>
                    @if($module['type'] === 'config')
                        <p class="px-5 py-6 text-xs leading-relaxed text-slate-500">
                            L'activation réelle dépend d'un réglage de l'environnement du container, que l'ERP ne lit pas :
                            cette liste ne peut pas être établie ici.
                        </p>
                    @elseif(count($equipped) === 0)
                        <p class="px-5 py-6 text-center text-xs text-slate-500">
                            Aucun établissement n'a ce module actif.
                        </p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach($equipped as $tenant)
                                <li>
                                    <a href="{{ route('tech.establishments.show', ['tenant' => $tenant, 'section' => 'modules']) }}"
                                       class="flex items-center justify-between gap-3 px-5 py-3 transition hover:bg-slate-50">
                                        <span class="min-w-0">
                                            <span class="block truncate text-xs font-bold text-slate-800">{{ $tenant->name }}</span>
                                            <span class="block font-mono text-[10px] text-slate-400">{{ $tenant->slug }}</span>
                                        </span>
                                        <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-slate-300"></i>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    </main>
</body>
</html>
