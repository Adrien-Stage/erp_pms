<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <x-favicon />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wetchah ERP — Corbeille · {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans antialiased">
@php
    $entrees = $resultat['entrees'] ?? [];
    $pagination = $resultat['pagination'] ?? ['page' => 1, 'pages' => 1, 'total' => count($entrees)];
    $choix = $resultat['filtres'] ?? ['types' => [], 'modules' => [], 'auteurs' => [], 'etats' => ['dans' => 'Dans la corbeille', 'restauree' => 'Restaurées', 'tout' => 'Toutes']];
    $date = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—';
    $champ = 'w-full rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';
    $filtresCourants = array_filter($filtres, fn ($v) => $v !== null && $v !== '');
@endphp

    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4 px-4 py-5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <x-brand variant="mark" class="h-9 shrink-0" />
                <div>
                    <h1 class="text-xl font-semibold leading-tight text-gray-800">Corbeille</h1>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $tenant->name }} · réservée à l'administrateur TECH</p>
                </div>
            </div>
            <a href="{{ route('tech.establishments.show', $tenant) }}" class="text-xs font-medium text-gray-500 hover:text-gray-800">&larr; Retour à l'établissement</a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-5">

        @if(session('success'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-xs leading-relaxed text-slate-600">
            Ce que les employés de l'établissement ont effacé par un bouton « Supprimer » : la fiche, ce qui est parti
            avec elle, ses liens et ses fichiers. Personne dans l'hôtel ne voit cette corbeille.
            <strong>Restaurer</strong> remet la fiche telle qu'elle était ; l'établissement refuse, avec le motif, ce
            qu'il ne peut plus accepter (un élément du même nom recréé, une catégorie elle-même à la corbeille…).
            <strong>Purger</strong> efface pour de bon. Chaque action est tracée, ici et dans l'établissement.
        </div>

        @if(! $resultat['ok'])
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-sm text-amber-900">
                <p class="font-semibold">Corbeille indisponible</p>
                <p class="mt-1 text-xs leading-relaxed">{{ $resultat['message'] }}</p>
            </div>
        @else
            {{-- Filtres --}}
            <form method="GET" action="{{ route('tech.establishments.corbeille', $tenant) }}" class="rounded-lg border border-slate-200 bg-white p-4" aria-label="Filtrer la corbeille">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-7">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-slate-600">État</span>
                        <select name="etat" class="{{ $champ }}">
                            @foreach($choix['etats'] as $cle => $libelle)
                                <option value="{{ $cle }}" @selected(($filtres['etat'] ?? 'dans') === $cle)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-slate-600">Type</span>
                        <select name="type" class="{{ $champ }}">
                            <option value="">Tous</option>
                            @foreach($choix['types'] as $cle => $libelle)
                                <option value="{{ $cle }}" @selected(($filtres['type'] ?? '') === $cle)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-slate-600">Service</span>
                        <select name="module" class="{{ $champ }}">
                            <option value="">Tous</option>
                            @foreach($choix['modules'] as $cle => $libelle)
                                <option value="{{ $cle }}" @selected(($filtres['module'] ?? '') === $cle)>{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-slate-600">Supprimé par</span>
                        <select name="auteur" class="{{ $champ }}">
                            <option value="">Tous</option>
                            @foreach($choix['auteurs'] as $auteur)
                                <option value="{{ $auteur }}" @selected(($filtres['auteur'] ?? '') === $auteur)>{{ $auteur }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-slate-600">Du</span>
                        <input type="date" name="du" value="{{ $filtres['du'] ?? '' }}" class="{{ $champ }}">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-slate-600">Au</span>
                        <input type="date" name="au" value="{{ $filtres['au'] ?? '' }}" class="{{ $champ }}">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold text-slate-600">Recherche</span>
                        <input type="search" name="q" value="{{ $filtres['q'] ?? '' }}" placeholder="Nom, numéro…" class="{{ $champ }}">
                    </label>
                </div>
                <div class="mt-3 flex items-center justify-end gap-2">
                    @if(count($filtresCourants) > 1 || ($filtresCourants['etat'] ?? 'dans') !== 'dans')
                        <a href="{{ route('tech.establishments.corbeille', $tenant) }}" class="text-xs font-medium text-slate-500 hover:text-slate-800">Effacer les filtres</a>
                    @endif
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2">Filtrer</button>
                </div>
            </form>

            {{-- Entrées --}}
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
                    <p class="text-xs font-semibold text-slate-700">{{ $pagination['total'] ?? count($entrees) }} entrée(s)</p>
                </div>
                @if($entrees === [])
                    <p class="px-4 py-10 text-center text-sm text-slate-500">Rien dans la corbeille pour ces filtres.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <caption class="sr-only">Entrées de la corbeille de {{ $tenant->name }}</caption>
                            <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th scope="col" class="px-4 py-2 text-left font-semibold">Élément</th>
                                    <th scope="col" class="px-4 py-2 text-left font-semibold">Service</th>
                                    <th scope="col" class="px-4 py-2 text-left font-semibold">Supprimé</th>
                                    <th scope="col" class="px-4 py-2 text-left font-semibold">Contenu</th>
                                    <th scope="col" class="px-4 py-2 text-right font-semibold"><span class="sr-only">Actions</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($entrees as $entree)
                                    @php $contenu = $entree['contenu'] ?? []; @endphp
                                    <tr class="align-top {{ $entree['restaure_le'] ? 'bg-emerald-50/40' : '' }}">
                                        <td class="px-4 py-3">
                                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $entree['type'] }}</span>
                                            <span class="block text-sm font-medium text-slate-900">{{ $entree['libelle'] }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ $entree['module_nom'] ?? $entree['module'] }}</td>
                                        <td class="px-4 py-3 text-slate-600">
                                            <span class="block">{{ $date($entree['supprime_le']) }}</span>
                                            <span class="block text-slate-500">par {{ $entree['supprime_par'] ?? '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">
                                            @if(($contenu['lignes_emportees'] ?? 0) > 0)<span class="block">{{ $contenu['lignes_emportees'] }} ligne(s) emportée(s)</span>@endif
                                            @if(($contenu['liens'] ?? 0) > 0)<span class="block">{{ $contenu['liens'] }} lien(s) à rétablir</span>@endif
                                            @if(($contenu['fichiers'] ?? 0) > 0)<span class="block">{{ $contenu['fichiers'] }} fichier(s)</span>@endif
                                            @if(array_sum($contenu) === 0)<span class="text-slate-400">La fiche seule</span>@endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if($entree['restaure_le'])
                                                <span class="inline-block rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-800">
                                                    Restauré le {{ $date($entree['restaure_le']) }}
                                                </span>
                                                <span class="mt-0.5 block text-[10px] text-slate-500">par {{ $entree['restaure_par'] }}</span>
                                            @else
                                                <div class="flex flex-wrap justify-end gap-2">
                                                    <form method="POST" action="{{ route('tech.establishments.corbeille.restaurer', ['tenant' => $tenant, 'entree' => $entree['id']]) }}"
                                                          onsubmit="return confirm('Restaurer « {{ addslashes($entree['libelle']) }} » dans l\'établissement ?');">
                                                        @csrf
                                                        <input type="hidden" name="libelle" value="{{ $entree['libelle'] }}">
                                                        @foreach($filtresCourants as $cle => $valeur)
                                                            <input type="hidden" name="filtres[{{ $cle }}]" value="{{ $valeur }}">
                                                        @endforeach
                                                        <button type="submit" class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Restaurer</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('tech.establishments.corbeille.purger', ['tenant' => $tenant, 'entree' => $entree['id']]) }}"
                                                          onsubmit="return confirm('Effacer définitivement « {{ addslashes($entree['libelle']) }} » ? Il ne pourra plus être restauré.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="libelle" value="{{ $entree['libelle'] }}">
                                                        @foreach($filtresCourants as $cle => $valeur)
                                                            <input type="hidden" name="filtres[{{ $cle }}]" value="{{ $valeur }}">
                                                        @endforeach
                                                        <button type="submit" class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Purger</button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(($pagination['pages'] ?? 1) > 1)
                    <nav class="flex items-center justify-between border-t border-slate-100 px-4 py-2.5 text-xs" aria-label="Pages de la corbeille">
                        <span class="text-slate-500">Page {{ $pagination['page'] }} sur {{ $pagination['pages'] }}</span>
                        <span class="flex gap-2">
                            @if($pagination['page'] > 1)
                                <a href="{{ route('tech.establishments.corbeille', array_merge(['tenant' => $tenant], $filtresCourants, ['page' => $pagination['page'] - 1])) }}" class="rounded-md border border-slate-300 px-3 py-1 font-medium text-slate-700 hover:bg-slate-50">Précédente</a>
                            @endif
                            @if($pagination['page'] < $pagination['pages'])
                                <a href="{{ route('tech.establishments.corbeille', array_merge(['tenant' => $tenant], $filtresCourants, ['page' => $pagination['page'] + 1])) }}" class="rounded-md border border-slate-300 px-3 py-1 font-medium text-slate-700 hover:bg-slate-50">Suivante</a>
                            @endif
                        </span>
                    </nav>
                @endif
            </div>
        @endif
    </main>
</body>
</html>
