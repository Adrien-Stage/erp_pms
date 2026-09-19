<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <x-favicon />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wetchah ERP — Matrice des droits · {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 font-sans antialiased">

    <header class="bg-white shadow">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4 px-4 py-5 sm:px-6 lg:px-8">
            <div class="flex items-center gap-3">
                <x-brand variant="mark" class="h-9 shrink-0" />
                <div>
                    <h1 class="text-xl font-semibold leading-tight text-gray-800">Matrice des droits</h1>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $tenant->name }}</p>
                </div>
            </div>
            <a href="{{ route(auth()->user()->isTechAdmin() ? 'tech.establishments.show' : 'business.establishments.show', $tenant) }}"
               class="text-xs font-medium text-gray-500 hover:text-gray-800">&larr; Retour à l'établissement</a>
        </div>
    </header>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-5">

        @if(session('success'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @if($matrice === null)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-6 text-sm text-amber-900">
                <p class="font-semibold">Matrice indisponible</p>
                <p class="mt-1 text-xs leading-relaxed">{{ $raison }}</p>
                <p class="mt-2 text-xs leading-relaxed text-amber-700">
                    Le gabarit des droits vit dans le code de l'établissement : il faut que celui-ci
                    réponde pour savoir quels droits existent. Rien n'est affiché de mémoire, pour ne
                    pas vous faire cocher des cases sans effet.
                </p>
            </div>
        @else
            @php
                // Écarts en vigueur, indexés pour retrouver l'effet d'un couple
                // rôle × droit sans reparcourir la liste à chaque case.
                $enVigueur = [];
                foreach ($matrice['ecarts'] ?? [] as $ecart) {
                    if (($ecart['subject_type'] ?? null) === 'role') {
                        $enVigueur[$ecart['subject_id'] . '|' . $ecart['permission']] = $ecart;
                    }
                }
                $rolesAssignables = collect($matrice['roles'] ?? [])->where('is_assignable', true)->values();
                // Repli si l'établissement tourne une version antérieure de
                // l'application, qui n'annonce pas encore ses portées.
                $portees = $matrice['portees'] ?? [['valeur' => 'etablissement', 'libelle' => "Tout l'établissement"]];
                // preserveKeys : sans lui, groupBy réindexe et le nom du droit,
                // qui est la clé, serait perdu au profit de 0, 1, 2…
                $parModule = collect($matrice['catalogue'] ?? [])
                    ->groupBy(fn ($roles, $droit) => explode('.', $droit)[0], preserveKeys: true);
            @endphp

            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-xs leading-relaxed text-slate-600">
                <p>
                    Chaque case indique si le rôle détient le droit. Les cases pré-cochées viennent du
                    gabarit livré avec l'application ; décocher pose un <strong>refus</strong>, cocher une case
                    vide pose une <strong>autorisation</strong>. Seuls ces écarts sont enregistrés.
                </p>
                <p class="mt-1.5">
                    Un refus l'emporte toujours, même si la personne porte un autre rôle qui accorde le droit.
                    C'est ce qui permet d'écrire « le comptable consulte l'économat mais n'y crée pas d'article »
                    à quelqu'un qui est aussi économe.
                </p>
            </div>

            @if(!empty($matrice['incompatibilites']))
                <details class="rounded-lg border border-slate-200 bg-white px-4 py-3">
                    <summary class="cursor-pointer text-xs font-semibold text-slate-700">
                        Cumuls de rôles refusés ({{ count($matrice['incompatibilites']) }})
                    </summary>
                    <ul class="mt-2 space-y-1.5">
                        @foreach($matrice['incompatibilites'] as $paire)
                            <li class="text-[11px] leading-relaxed text-slate-600">
                                <span class="font-mono font-semibold">{{ implode(' × ', $paire['roles']) }}</span>
                                — {{ $paire['motif'] }}
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            <form method="POST" action="{{ route(auth()->user()->isTechAdmin() ? 'tech.establishments.permissions.update' : 'business.establishments.permissions.update', $tenant) }}">
                @csrf
                @method('PUT')

                @foreach($parModule as $module => $droits)
                    <section class="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <header class="border-b border-slate-100 bg-slate-50 px-4 py-2.5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">{{ $module }}</h3>
                        </header>

                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-slate-100">
                                        <th class="px-4 py-2 text-left font-semibold text-slate-500">Droit</th>
                                        @foreach($rolesAssignables as $role)
                                            <th class="px-2 py-2 text-center font-semibold text-slate-500 whitespace-nowrap">{{ $role['slug'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    @foreach($droits as $droit => $rolesDuGabarit)
                                        <tr>
                                            <td class="px-4 py-1.5 font-mono text-[11px] text-slate-700">{{ $droit }}</td>
                                            @foreach($rolesAssignables as $role)
                                                @php
                                                    $cle      = $role['slug'] . '|' . $droit;
                                                    $gabarit  = in_array($role['slug'], $rolesDuGabarit, true);
                                                    $ecart    = $enVigueur[$cle] ?? null;
                                                    $coche    = $ecart ? $ecart['effect'] === 'allow' : $gabarit;
                                                    $lecture  = str_ends_with($droit, '.voir') || str_ends_with($droit, '.export');
                                                @endphp
                                                <td class="px-2 py-1.5 text-center">
                                                    <input type="checkbox"
                                                           data-gabarit="{{ $gabarit ? '1' : '0' }}"
                                                           data-role="{{ $role['slug'] }}"
                                                           data-droit="{{ $droit }}"
                                                           @checked($coche)
                                                           class="case-droit rounded border-slate-300 {{ $ecart ? 'ring-2 ring-amber-400' : '' }}">

                                                    {{-- Étendue : seules les consultations la portent. Restreindre
                                                         une écriture n'aurait pas de sens ici — c'est le droit
                                                         lui-même qu'on retire. --}}
                                                    @if($lecture)
                                                        <select data-portee-de="{{ $role['slug'] }}|{{ $droit }}"
                                                                class="portee mt-1 block w-full rounded border-slate-200 text-[10px] {{ $coche ? '' : 'invisible' }}">
                                                            @foreach($portees as $portee)
                                                                <option value="{{ $portee['valeur'] }}"
                                                                        @selected(($ecart['scope'] ?? 'etablissement') === $portee['valeur'])>
                                                                    {{ $portee['libelle'] }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach

                <div class="sticky bottom-0 flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-lg">
                    <div class="flex-1">
                        <input type="text" name="motif" id="motif" maxlength="255"
                               placeholder="Motif de la modification — consigné au journal"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-slate-500">
                        <p class="mt-1 text-[11px] text-slate-500"><span id="compteur">0</span> écart(s) au gabarit.</p>
                    </div>
                    <button type="submit" class="shrink-0 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                        Appliquer à l'établissement
                    </button>
                </div>

                <div id="ecarts"></div>
            </form>

            <script>
                // Seuls les écarts au gabarit sont envoyés : le gabarit lui-même
                // vit dans le code de l'établissement et n'a pas à transiter.
                (function () {
                    const cases   = document.querySelectorAll('.case-droit');
                    const panier  = document.getElementById('ecarts');
                    const compteur = document.getElementById('compteur');
                    const motif   = document.getElementById('motif');

                    function recalculer() {
                        panier.innerHTML = '';
                        let n = 0;

                        cases.forEach((c) => {
                            const gabarit = c.dataset.gabarit === '1';
                            const portee  = document.querySelector(
                                `[data-portee-de="${c.dataset.role}|${c.dataset.droit}"]`
                            );
                            const porteeRestreinte = c.checked && portee && portee.value !== 'etablissement';

                            if (c.checked === gabarit && !porteeRestreinte) return;

                            const effet = c.checked ? 'allow' : 'deny';
                            const select = document.querySelector(
                                `[data-portee-de="${c.dataset.role}|${c.dataset.droit}"]`
                            );
                            const champs = [['role', c.dataset.role], ['permission', c.dataset.droit],
                                            ['effect', effet], ['reason', motif.value]];
                            if (select && c.checked) champs.push(['scope', select.value]);

                            champs.forEach(([champ, valeur]) => {
                                const input = document.createElement('input');
                                input.type  = 'hidden';
                                input.name  = `ecarts[${n}][${champ}]`;
                                input.value = valeur;
                                panier.appendChild(input);
                            });
                            n++;
                        });

                        compteur.textContent = n;
                    }

                    // Une portée modifiée sur une case cochée conforme au gabarit
                    // reste un écart : elle doit être envoyée.
                    document.querySelectorAll('.portee').forEach((s) => s.addEventListener('change', recalculer));

                    cases.forEach((c) => c.addEventListener('change', () => {
                        const select = document.querySelector(
                            `[data-portee-de="${c.dataset.role}|${c.dataset.droit}"]`
                        );
                        if (select) select.classList.toggle('invisible', !c.checked);
                        recalculer();
                    }));
                    motif.addEventListener('input', recalculer);
                    recalculer();
                })();
            </script>
        @endif
    </div>
</body>
</html>
