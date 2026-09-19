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

                <div class="mb-3 flex items-center justify-end gap-2">
                    <button type="button" data-plier="ouvrir"
                            class="rounded-lg border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50">
                        Tout déplier
                    </button>
                    <button type="button" data-plier="fermer"
                            class="rounded-lg border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50">
                        Tout replier
                    </button>
                </div>

                @foreach($parModule as $module => $droits)
                    @php
                        // Combien de droits ce module compte-t-il d'écarts au
                        // gabarit ? Affiché sur l'en-tête replié, pour qu'on
                        // sache où regarder sans tout déplier.
                        $ecartsDuModule = 0;
                        foreach ($droits as $droit => $rolesDuGabarit) {
                            foreach ($rolesAssignables as $role) {
                                if (isset($enVigueur[$role['slug'] . '|' . $droit])) {
                                    $ecartsDuModule++;
                                }
                            }
                        }
                    @endphp

                    {{-- Chaque module se replie : la matrice complète fait plus
                         de deux cents lignes, et on n'en règle qu'une à la fois.
                         Les cases d'un module replié restent dans le document,
                         donc une modification faite puis repliée part quand même
                         à l'enregistrement. --}}
                    <details class="module group mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white"
                             @if($ecartsDuModule > 0) open @endif>
                        <summary class="flex cursor-pointer select-none items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-2.5 hover:bg-slate-100">
                            <div class="flex items-center gap-2">
                                <svg class="h-3.5 w-3.5 text-slate-400 transition-transform group-open:rotate-90"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">{{ $module }}</h3>
                                <span class="text-[11px] font-normal text-slate-400">{{ count($droits) }} droit(s)</span>
                            </div>

                            <span class="badge-ecarts rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 {{ $ecartsDuModule > 0 ? '' : 'hidden' }}">
                                <span class="compte">{{ $ecartsDuModule }}</span> écart(s)
                            </span>
                        </summary>

                        {{-- Le cadre défile ; les en-têtes n'en sortent pas. Sans
                             cela, une matrice de dix rôles sur cinquante droits
                             se lit en devinant à quelle colonne et à quelle ligne
                             appartient la case qu'on coche. --}}
                        <div class="matrice max-h-[65vh] overflow-auto">
                            <table class="w-full border-separate border-spacing-0 text-xs">
                                <thead>
                                    <tr>
                                        {{-- Coin : figé dans les deux sens, donc au-dessus des deux. --}}
                                        <th class="sticky left-0 top-0 z-30 min-w-[16rem] border-b border-r border-slate-200 bg-slate-50 px-4 py-2 text-left font-semibold text-slate-500">
                                            Droit
                                        </th>
                                        @foreach($rolesAssignables as $role)
                                            <th class="sticky top-0 z-20 border-b border-slate-200 bg-slate-50 px-2 py-2 text-center font-semibold text-slate-500 whitespace-nowrap"
                                                title="{{ $role['name'] }}">
                                                {{ $role['slug'] }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($droits as $droit => $rolesDuGabarit)
                                        <tr class="group">
                                            <th scope="row"
                                                class="sticky left-0 z-10 border-b border-r border-slate-100 bg-white px-4 py-1.5 text-left font-mono text-[11px] font-normal text-slate-700 group-hover:bg-slate-50">
                                                {{ $droit }}
                                            </th>
                                            @foreach($rolesAssignables as $role)
                                                @php
                                                    $cle      = $role['slug'] . '|' . $droit;
                                                    $gabarit  = in_array($role['slug'], $rolesDuGabarit, true);
                                                    $ecart    = $enVigueur[$cle] ?? null;
                                                    $coche    = $ecart ? $ecart['effect'] === 'allow' : $gabarit;
                                                    $lecture  = str_ends_with($droit, '.voir') || str_ends_with($droit, '.export');
                                                @endphp
                                                <td class="border-b border-slate-100 px-2 py-1.5 text-center group-hover:bg-slate-50">
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
                    </details>
                @endforeach

                <div class="sticky bottom-0 flex items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-lg">
                    <div class="flex-1">
                        {{-- Sans « name » : la valeur n'est pas postée telle quelle,
                             elle est recopiée sur chaque écart du lot. Un attribut
                             « name » laisserait croire qu'elle compte seule. --}}
                        <input type="text" id="motif" maxlength="255"
                               placeholder="Motif de la modification — consigné au journal et sur chaque droit modifié"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-slate-500">
                        <p class="mt-1 text-[11px] text-slate-500">
                            <span id="compteur">0</span> écart(s) au gabarit.
                            <span id="exigence" class="hidden font-medium text-amber-700">Indiquez un motif pour enregistrer.</span>
                        </p>
                    </div>
                    <button type="submit" id="appliquer" disabled
                            class="shrink-0 rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300">
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
                        rafraichirBadges();

                        // L'application REMPLACE les écarts portés par les rôles :
                        // valider un lot vide effacerait les surcharges en place.
                        // Et une matrice de droits qui change sans motif écrit ne
                        // se contrôle pas six mois plus tard.
                        const motifManquant = n > 0 && motif.value.trim() === '';
                        document.getElementById('exigence').classList.toggle('hidden', !motifManquant);
                        document.getElementById('appliquer').disabled = n === 0 || motifManquant;
                    }

                    // Le badge de chaque module compte ses propres écarts : on
                    // voit qu'un module replié contient des modifications en
                    // attente, sans avoir à le rouvrir.
                    function rafraichirBadges() {
                        document.querySelectorAll('.module').forEach((module) => {
                            let n = 0;

                            module.querySelectorAll('.case-droit').forEach((c) => {
                                const gabarit = c.dataset.gabarit === '1';
                                const portee  = module.querySelector(
                                    `[data-portee-de="${c.dataset.role}|${c.dataset.droit}"]`
                                );
                                const restreinte = c.checked && portee && portee.value !== 'etablissement';
                                if (c.checked !== gabarit || restreinte) n++;
                            });

                            const badge = module.querySelector('.badge-ecarts');
                            badge.querySelector('.compte').textContent = n;
                            badge.classList.toggle('hidden', n === 0);
                        });
                    }

                    document.querySelectorAll('[data-plier]').forEach((b) => {
                        b.addEventListener('click', () => {
                            const ouvrir = b.dataset.plier === 'ouvrir';
                            document.querySelectorAll('.module').forEach((m) => (m.open = ouvrir));
                        });
                    });

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
