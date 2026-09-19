<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <x-favicon />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $owner->name }} — Propriétaire</title>
    <meta name="description" content="Fiche du propriétaire {{ $owner->name }} et de ses établissements">
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
                <a href="{{ route('tech.owners.index') }}" class="flex items-center gap-2 text-slate-400 hover:text-white transition text-xs font-semibold">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Propriétaires
                </a>
                <div class="h-5 w-px bg-slate-700"></div>
                <div>
                    <h1 class="text-sm font-bold text-white leading-none">{{ $owner->name }}</h1>
                    <p class="text-[10px] text-slate-400">{{ $owner->email }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-bold border
                    {{ $owner->is_active ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $owner->is_active ? 'bg-green-400' : 'bg-red-400' }}"></span>
                    {{ $owner->is_active ? 'Actif' : 'Désactivé' }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-bold text-slate-300 hover:bg-slate-700 hover:text-white transition">
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-5 py-8 lg:px-8">

        @if(session('success'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Coordonnées --}}
            <section class="lg:col-span-1">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">
                            {{ \Illuminate\Support\Str::of($owner->name)->explode(' ')->take(2)->map(fn ($m) => mb_strtoupper(mb_substr($m, 0, 1)))->implode('') ?: 'P' }}
                        </span>
                        <div class="min-w-0">
                            <h2 class="truncate font-display text-lg font-bold text-slate-900">{{ $owner->name }}</h2>
                            <p class="text-xs text-slate-500">Propriétaire</p>
                        </div>
                    </div>

                    <dl class="space-y-3 text-sm">
                        @foreach([
                            ['Email', $owner->email],
                            ['Téléphone', $owner->phone],
                            ['Société', $owner->company_name],
                            ['Nationalité', $owner->nationality],
                            ['Dernière connexion', $owner->last_login_at?->diffForHumans()],
                            ['Compte créé', $owner->created_at?->translatedFormat('j F Y')],
                        ] as [$label, $valeur])
                            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-2 last:border-0">
                                <dt class="shrink-0 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                                <dd class="min-w-0 break-words text-right text-slate-700">{{ $valeur ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
                        <button type="button" onclick="window.openOwnerEdit(this)"
                                data-id="{{ $owner->id }}"
                                data-name="{{ $owner->name }}"
                                data-email="{{ $owner->email }}"
                                data-phone="{{ $owner->phone }}"
                                data-company="{{ $owner->company_name }}"
                                data-nationality="{{ $owner->nationality }}"
                                data-action="{{ route('tech.owners.update', $owner) }}"
                                class="flex-1 rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-slate-700">
                            Modifier
                        </button>

                        <form method="POST" action="{{ route('tech.owners.toggle-active', $owner) }}" class="flex-1"
                              @if($owner->is_active) onsubmit="return confirm('Désactiver le compte de {{ addslashes($owner->name) }} ?');" @endif>
                            @csrf
                            <button type="submit" class="w-full rounded-lg border px-3 py-2 text-xs font-semibold transition
                                {{ $owner->is_active ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50' }}">
                                {{ $owner->is_active ? 'Désactiver' : 'Activer' }}
                            </button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('tech.owners.destroy', $owner) }}" class="mt-2"
                          onsubmit="return confirm('Supprimer définitivement le compte de {{ addslashes($owner->name) }} ? Cette action est irréversible.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50">
                            Supprimer le compte
                        </button>
                    </form>

                    @if($owner->tenants->isNotEmpty())
                        <p class="mt-2 text-[11px] leading-relaxed text-slate-400">
                            La suppression est refusée tant que ce propriétaire détient des établissements :
                            ils seraient effacés avec lui.
                        </p>
                    @endif
                </div>
            </section>

            {{-- Établissements --}}
            <section class="lg:col-span-2">
                <div class="mb-4 flex items-end justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-bold text-slate-900">Ses établissements</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Ouvrez une fiche pour gérer l'établissement, sans repasser par l'onglet Établissements.
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-bold text-slate-700">
                        {{ $owner->tenants->count() }}
                    </span>
                </div>

                @if($owner->tenants->isEmpty())
                    <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                        <p class="text-sm text-slate-500">Ce propriétaire ne détient aucun établissement.</p>
                        <a href="{{ route('tech.establishments.create') }}"
                           class="mt-3 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-indigo-700">
                            Créer un établissement
                        </a>
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach($owner->tenants as $tenant)
                            @php
                                $docker = match($tenant->docker_status) {
                                    'running'  => ['Conteneurs actifs', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500'],
                                    'creating' => ['Provisioning…',     'bg-amber-50 text-amber-700 border-amber-200',     'bg-amber-500'],
                                    'error'    => ['Erreur conteneurs', 'bg-red-50 text-red-700 border-red-200',           'bg-red-500'],
                                    default    => ['Conteneurs arrêtés','bg-slate-100 text-slate-600 border-slate-200',    'bg-slate-400'],
                                };
                            @endphp
                            <a href="{{ route('tech.establishments.show', $tenant) }}"
                               class="group flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                                <div class="mb-3 flex items-start gap-3">
                                    @if(!empty($tenant->settings['logo']))
                                        <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="" class="h-9 w-9 shrink-0 rounded object-contain">
                                    @else
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-indigo-50">
                                            <svg class="h-5 w-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                                            </svg>
                                        </span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <h3 class="truncate font-semibold text-slate-900 transition group-hover:text-indigo-600">{{ $tenant->name }}</h3>
                                        <p class="truncate font-mono text-[11px] text-slate-400">{{ $tenant->slug }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold
                                        {{ $tenant->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' }}">
                                        {{ $tenant->is_active ? 'Actif' : 'Inactif' }}
                                    </span>
                                </div>

                                <div class="mb-3 flex flex-wrap gap-1.5">
                                    @foreach(array_slice($tenant->modules ?? [], 0, 4) as $module)
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium capitalize text-slate-600">{{ $module }}</span>
                                    @endforeach
                                    @if(count($tenant->modules ?? []) > 4)
                                        <span class="text-[10px] text-slate-400">+{{ count($tenant->modules) - 4 }}</span>
                                    @endif
                                </div>

                                <div class="mt-auto flex items-center justify-between border-t border-slate-100 pt-3">
                                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[10px] font-semibold {{ $docker[1] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $docker[2] }}"></span>
                                        {{ $docker[0] }}
                                    </span>
                                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600">
                                        Gérer
                                        <svg class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                        </svg>
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </main>

    @include('admin.owners.partials.edit-modal')

</body>
</html>
