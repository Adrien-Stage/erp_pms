<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Propriétaires — Administration</title>
    <meta name="description" content="Registre des propriétaires de la plateforme">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased font-body">

    <header class="sticky top-0 z-30 w-full bg-[#0f172a] border-b border-slate-800 text-white shadow-md">
        <div class="mx-auto px-5 lg:px-8 flex items-center justify-between h-14">
            <div class="flex items-center gap-4">
                <a href="{{ route('tech.dashboard') }}" class="flex items-center gap-2 text-slate-400 hover:text-white transition text-xs font-semibold">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                    Supervision
                </a>
                <div class="h-5 w-px bg-slate-700"></div>
                <h1 class="text-sm font-bold text-white leading-none">Propriétaires</h1>
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

        @if(session('success'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="font-display text-2xl font-bold text-slate-900">Registre des propriétaires</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Les personnes qui détiennent les établissements de la plateforme. Ouvrez une fiche pour accéder
                    directement à l'un de leurs établissements.
                </p>
            </div>
            <form method="GET" action="{{ route('tech.owners.index') }}" class="flex items-center gap-2">
                <input type="search" name="q" value="{{ $search }}" placeholder="Nom, email ou société…"
                       class="w-64 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                <button type="submit" class="rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-slate-700">
                    Rechercher
                </button>
                @if($search !== '')
                    <a href="{{ route('tech.owners.index') }}" class="text-sm text-slate-500 hover:text-slate-800">Réinitialiser</a>
                @endif
            </form>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach([
                ['Propriétaires', $stats['total'], 'text-slate-900'],
                ['Comptes actifs', $stats['actifs'], 'text-emerald-600'],
                ['Comptes désactivés', $stats['inactifs'], 'text-amber-600'],
                ['Établissements détenus', $stats['etablissements'], 'text-indigo-600'],
            ] as [$label, $valeur, $couleur])
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold {{ $couleur }}">{{ $valeur }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            @if($owners->isEmpty())
                <div class="px-6 py-16 text-center">
                    <p class="text-sm text-slate-500">
                        {{ $search !== '' ? 'Aucun propriétaire ne correspond à cette recherche.' : 'Aucun propriétaire enregistré pour le moment.' }}
                    </p>
                    @if($search === '')
                        <p class="mt-1 text-xs text-slate-400">
                            Un propriétaire est créé en même temps que son premier établissement.
                        </p>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Propriétaire</th>
                                <th class="px-5 py-3 font-semibold">Contact</th>
                                <th class="px-5 py-3 font-semibold">Société</th>
                                <th class="px-5 py-3 font-semibold text-center">Établissements</th>
                                <th class="px-5 py-3 font-semibold">Statut</th>
                                <th class="px-5 py-3 font-semibold">Dernière connexion</th>
                                <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($owners as $owner)
                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-3.5">
                                        <a href="{{ route('tech.owners.show', $owner) }}" class="flex items-center gap-3 group">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                                                {{ \Illuminate\Support\Str::of($owner->name)->explode(' ')->take(2)->map(fn ($m) => mb_strtoupper(mb_substr($m, 0, 1)))->implode('') ?: 'P' }}
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate font-semibold text-slate-900 group-hover:text-indigo-600">{{ $owner->name }}</span>
                                                @if($owner->nationality)
                                                    <span class="block text-[11px] text-slate-400">{{ $owner->nationality }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="block text-slate-700">{{ $owner->email }}</span>
                                        <span class="block text-[11px] text-slate-400">{{ $owner->phone ?: '—' }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-slate-600">{{ $owner->company_name ?: '—' }}</td>
                                    <td class="px-5 py-3.5 text-center">
                                        <span class="inline-flex min-w-[2rem] items-center justify-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700">
                                            {{ $owner->tenants_count }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5">
                                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-bold
                                            {{ $owner->is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-red-200 bg-red-50 text-red-700' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $owner->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                            {{ $owner->is_active ? 'Actif' : 'Désactivé' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3.5 text-[12px] text-slate-500">
                                        {{ $owner->last_login_at?->diffForHumans() ?? 'Jamais' }}
                                    </td>
                                    <td class="px-5 py-3.5 text-right">
                                        @include('admin.owners.partials.row-menu', ['owner' => $owner])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </main>

    @include('admin.owners.partials.edit-modal')

</body>
</html>
