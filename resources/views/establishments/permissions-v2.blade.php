@php
    /**
     * Droits & rôles d'un établissement — écran v2.
     *
     * Attend : $tenant, $matrice (API v2 de l'établissement), $prefixe
     * (« tech. » ou « business. »), $onglet, $versions.
     *
     * Les droits se lisent en couches : le modèle livré avec l'application,
     * la couche de la console — la seule que cet écran modifie —, la couche
     * de l'hôtel, puis les exceptions nominatives. Un refus, quelle que soit
     * sa couche, l'emporte.
     */
    $roles = collect($matrice['roles'] ?? [])->keyBy('slug');

    // Colonnes : les rôles que la console règle, plus l'administrateur, montré
    // mais figé — il consulte tout et n'écrit que la configuration et les
    // comptes, par construction.
    $services = [
        'it' => 'Informatique', 'direction' => 'Direction', 'hebergement' => 'Hébergement',
        'housekeeping' => 'Housekeeping', 'restaurant' => 'Restaurant', 'boutique' => 'Boutique',
        'economat' => 'Économat', 'comptabilite' => 'Finances', 'controle' => 'Contrôle',
    ];
    $groupeDe = fn (array $r): string => $r['level'] === null ? 'controle' : ($r['module'] ?? 'autre');
    $colonnes = $roles
        ->filter(fn (array $r) => ($r['reglable'] ?? false) || $r['slug'] === 'admin')
        ->sortBy(fn (array $r) => sprintf('%02d-%d-%s',
            array_search($groupeDe($r), array_keys($services), true) === false ? 99 : array_search($groupeDe($r), array_keys($services), true),
            $r['level'] ?? 9, $r['name']))
        ->values();
    $groupes = $colonnes->groupBy($groupeDe);

    // Couches posées sur les rôles, et exceptions nominatives.
    $console = [];
    $hotel = [];
    $nominatives = [];
    foreach ($matrice['ecarts'] ?? [] as $ecart) {
        if (($ecart['subject_type'] ?? null) === 'role') {
            $cle = $ecart['subject_id'] . '|' . $ecart['permission'];
            if (($ecart['origin'] ?? 'erp') === 'erp') {
                $console[$cle] = $ecart;
            } else {
                $hotel[$cle] = $ecart;
            }
        } else {
            $nominatives[] = $ecart;
        }
    }

    $comptes = collect($matrice['comptes'] ?? [])->keyBy('id');
    $nomDuCompte = fn ($id) => $comptes[(int) $id]['name'] ?? "Compte #{$id}";

    // Exceptions nominatives par case : celles des personnes qui portent le
    // rôle de la colonne.
    $nominativesParCase = [];
    foreach ($nominatives as $ecart) {
        $compte = $comptes[(int) $ecart['subject_id']] ?? null;
        foreach ($compte['roles'] ?? [] as $slug) {
            $nominativesParCase[$slug . '|' . $ecart['permission']][] =
                $compte['name'] . ' : ' . ($ecart['effect'] === 'deny' ? 'refus' : 'autorisation');
        }
    }

    $ecritures = array_flip($matrice['ecritures'] ?? []);
    $bornes = $matrice['droits_bornes'] ?? [];
    $portees = $matrice['portees'] ?? [];
    $parModule = collect($matrice['catalogue'] ?? [])
        ->groupBy(fn ($r, $droit) => explode('.', $droit)[0], preserveKeys: true);

    $administrateurs = collect($matrice['comptes'] ?? [])->filter(fn (array $c) => in_array('admin', $c['roles'], true))->values();
    $restrictions = collect($matrice['restrictions'] ?? []);
    $constats = collect($matrice['constats'] ?? []);
    $echues = collect($matrice['exceptions_echues'] ?? []);

    // Dérogations en vigueur : autorisations de la console qui ouvrent un cumul.
    $derogations = collect($console)
        ->filter(fn ($e, $cle) => $e['effect'] === 'allow' && isset(($matrice['cumuls'] ?? [])[$cle]));

    $onglets = [
        'matrice'         => ['Matrice', null],
        'administrateurs' => ['Comptes administrateurs', $administrateurs->count()],
        'exceptions'      => ['Exceptions', count($nominatives) + $restrictions->count() + count($hotel)],
        'alertes'         => ['Alertes', $constats->count() + $echues->count() + $derogations->count()],
        'historique'      => ['Historique', $versions->count()],
    ];

    $donneesJs = [
        'cumuls'  => $matrice['cumuls'] ?? (object) [],
        'regles'  => $matrice['regles_de_cumul'] ?? [],
        'noms'    => $roles->map(fn ($r) => $r['name'])->all(),
        'apercu'  => route($prefixe . 'establishments.permissions.preview', $tenant),
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <x-favicon />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Wetchah ERP — Droits & rôles · {{ $tenant->name }}</title>
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
                    <h1 class="text-xl font-semibold leading-tight text-gray-800">Droits &amp; rôles</h1>
                    <p class="mt-0.5 text-xs text-gray-500">{{ $tenant->name }}</p>
                </div>
            </div>
            <a href="{{ route($prefixe . 'establishments.show', $tenant) }}"
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
        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-800">
                <ul class="list-disc space-y-0.5 pl-5">
                    @foreach($errors->all() as $erreur)<li>{{ $erreur }}</li>@endforeach
                </ul>
            </div>
        @endif
        @if(session('identifiants'))
            {{-- Montrés une seule fois : la console ne garde aucun mot de passe. --}}
            <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                <p class="font-semibold">Identifiants à transmettre — ils ne seront plus affichés.</p>
                <p class="mt-1 font-mono">{{ session('identifiants')['email'] }} · {{ session('identifiants')['password'] }}</p>
            </div>
        @endif

        <nav class="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1" role="tablist" aria-label="Rubriques">
            @foreach($onglets as $cle => [$libelle, $compte])
                <button type="button" role="tab" data-onglet="{{ $cle }}"
                        aria-selected="{{ $onglet === $cle ? 'true' : 'false' }}"
                        class="onglet rounded-lg px-3 py-2 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400
                            {{ $onglet === $cle ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                    {{ $libelle }}
                    @if($compte !== null)
                        <span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px] {{ $onglet === $cle ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">{{ $compte }}</span>
                    @endif
                </button>
            @endforeach
        </nav>

        {{-- ==================== MATRICE ==================== --}}
        <section data-panneau="matrice" class="{{ $onglet === 'matrice' ? '' : 'hidden' }} space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-xs leading-relaxed text-slate-600">
                <p>
                    Chaque case dit si le rôle détient le droit. Le <strong>modèle</strong> vient de l'application ;
                    cet écran règle la <strong>couche de la console</strong> — cocher une case vide pose une
                    autorisation, décocher une case du modèle pose un refus. La <strong>couche de l'hôtel</strong>
                    et les <strong>exceptions nominatives</strong> sont montrées, jamais modifiées d'ici.
                    Un refus, quelle que soit sa couche, l'emporte.
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px]">
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded border-2 border-amber-400"></span> Écart de la console</span>
                    <span class="inline-flex items-center gap-1.5"><span class="rounded bg-red-100 px-1 font-bold text-red-700">H</span> Refus posé par l'hôtel</span>
                    <span class="inline-flex items-center gap-1.5"><span class="rounded bg-emerald-100 px-1 font-bold text-emerald-700">H</span> Autorisation posée par l'hôtel</span>
                    <span class="inline-flex items-center gap-1.5"><span class="rounded bg-sky-100 px-1 font-bold text-sky-700">N</span> Exceptions nominatives</span>
                    <span class="inline-flex items-center gap-1.5"><span class="inline-block h-3 w-3 rounded border-2 border-red-500"></span> Cumul de fonctions incompatibles</span>
                </div>
            </div>

            <form method="POST" id="form-matrice"
                  action="{{ route($prefixe . 'establishments.permissions.update', $tenant) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="empreinte" value="{{ $matrice['empreinte'] ?? '' }}">

                <div class="sticky top-0 z-40 mb-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="relative min-w-[14rem] flex-1">
                            <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <label for="recherche" class="sr-only">Filtrer les droits</label>
                            <input type="search" id="recherche" autocomplete="off"
                                   placeholder="Filtrer un droit — « economat », « supprimer », « ledger.periods »…"
                                   class="w-full rounded-lg border border-slate-300 py-1.5 pl-8 pr-3 text-xs outline-none focus:border-slate-500">
                        </div>
                        <label for="filtre-role" class="sr-only">Rôle affiché</label>
                        <select id="filtre-role" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs outline-none focus:border-slate-500">
                            <option value="">Tous les rôles</option>
                            @foreach($groupes as $groupe => $membres)
                                <optgroup label="{{ $services[$groupe] ?? $groupe }}">
                                    @foreach($membres as $role)
                                        <option value="{{ $role['slug'] }}">{{ $role['name'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        <label class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs text-slate-600">
                            <input type="checkbox" id="filtre-ecarts" class="rounded border-slate-300">
                            Écarts seulement
                        </label>
                        <div class="ml-auto flex items-center gap-2">
                            <button type="button" data-plier="ouvrir" class="rounded-lg border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50">Tout déplier</button>
                            <button type="button" data-plier="fermer" class="rounded-lg border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-600 hover:bg-slate-50">Tout replier</button>
                        </div>
                    </div>
                    <p id="resultat-filtre" class="mt-1.5 hidden text-[11px] text-slate-500"></p>
                </div>

                @foreach($parModule as $module => $droits)
                    @php
                        $ecartsDuModule = 0;
                        foreach ($droits as $droit => $detenteurs) {
                            foreach ($colonnes as $role) {
                                if (isset($console[$role['slug'] . '|' . $droit]) || isset($hotel[$role['slug'] . '|' . $droit])) {
                                    $ecartsDuModule++;
                                }
                            }
                        }
                    @endphp
                    <details class="module group mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white" @if($ecartsDuModule > 0) open @endif>
                        <summary class="flex cursor-pointer select-none items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-2.5 hover:bg-slate-100">
                            <div class="flex items-center gap-2">
                                <svg class="h-3.5 w-3.5 text-slate-400 transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                </svg>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">{{ $module }}</h3>
                                <span class="text-[11px] font-normal text-slate-400">{{ count($droits) }} droit(s)</span>
                            </div>
                            <span class="badge-ecarts rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 {{ $ecartsDuModule > 0 ? '' : 'hidden' }}">
                                <span class="compte">{{ $ecartsDuModule }}</span> écart(s)
                            </span>
                        </summary>

                        <div class="matrice max-h-[65vh] overflow-auto">
                            <table class="w-full border-separate border-spacing-0 text-xs">
                                <thead>
                                    <tr>
                                        <th class="sticky left-0 top-0 z-30 min-w-[16rem] border-b border-r border-slate-200 bg-slate-50 px-4 py-2 text-left font-semibold text-slate-500">
                                            Droit
                                        </th>
                                        @foreach($colonnes as $index => $role)
                                            @php
                                                $groupe = $groupeDe($role);
                                                $debutDeGroupe = $index === 0 || $groupeDe($colonnes[$index - 1]) !== $groupe;
                                            @endphp
                                            <th data-colonne="{{ $role['slug'] }}" scope="col"
                                                class="sticky top-0 z-20 min-w-[5.5rem] border-b border-slate-200 bg-slate-50 px-2 py-1.5 text-center align-bottom font-semibold text-slate-600 {{ $debutDeGroupe ? 'border-l-2 border-l-slate-300' : '' }}"
                                                title="{{ $role['description'] ?? $role['name'] }}">
                                                @if($debutDeGroupe)
                                                    <span class="block text-left text-[9px] font-bold uppercase tracking-wider text-slate-400">{{ $services[$groupe] ?? $groupe }}</span>
                                                @endif
                                                <span class="block text-[10px] leading-tight">{{ $role['name'] }}</span>
                                                <span class="mt-0.5 block text-[9px] font-normal text-slate-400">
                                                    {{ $role['level'] === null ? 'Transversal' : 'N' . $role['level'] }} · {{ $role['titulaires'] ?? 0 }} pers.
                                                </span>
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($droits as $droit => $detenteurs)
                                        <tr class="group">
                                            <th scope="row" class="sticky left-0 z-10 border-b border-r border-slate-100 bg-white px-4 py-1.5 text-left font-mono text-[11px] font-normal text-slate-700 group-hover:bg-slate-50">
                                                {{ $droit }}
                                                @if(!isset($ecritures[$droit]))
                                                    <span class="ml-1 rounded bg-slate-100 px-1 font-sans text-[9px] text-slate-500">lecture</span>
                                                @endif
                                            </th>
                                            @foreach($colonnes as $index => $role)
                                                @php
                                                    $cle = $role['slug'] . '|' . $droit;
                                                    $gabarit = in_array($role['slug'], $detenteurs, true);
                                                    $ecartConsole = $console[$cle] ?? null;
                                                    $ecartHotel = $hotel[$cle] ?? null;
                                                    $coche = $ecartConsole ? $ecartConsole['effect'] === 'allow' : $gabarit;
                                                    $fige = $role['slug'] === 'admin';
                                                    $debutDeGroupe = $index === 0 || $groupeDe($colonnes[$index - 1]) !== $groupeDe($role);
                                                    $nominativesIci = $nominativesParCase[$cle] ?? [];
                                                @endphp
                                                <td data-colonne="{{ $role['slug'] }}"
                                                    class="cellule border-b border-slate-100 px-2 py-1.5 text-center group-hover:bg-slate-50 {{ $debutDeGroupe ? 'border-l-2 border-l-slate-200' : '' }}">
                                                    <div class="inline-flex items-center gap-1">
                                                        <input type="checkbox"
                                                               aria-label="{{ $role['name'] }} — {{ $droit }}"
                                                               data-role="{{ $role['slug'] }}"
                                                               data-droit="{{ $droit }}"
                                                               data-gabarit="{{ $gabarit ? '1' : '0' }}"
                                                               data-console="{{ $ecartConsole['effect'] ?? '' }}"
                                                               data-portee-initiale="{{ $ecartConsole['scope'] ?? '' }}"
                                                               data-raison="{{ $ecartConsole['reason'] ?? '' }}"
                                                               @checked($coche)
                                                               @disabled($fige)
                                                               @if($fige) title="L'administrateur ne se règle pas : il consulte tout et n'écrit que la configuration et les comptes." @endif
                                                               class="{{ $fige ? '' : 'case-droit' }} rounded border-slate-300 {{ $ecartConsole ? 'ring-2 ring-amber-400' : '' }}">
                                                        @if($ecartHotel)
                                                            <span class="rounded px-1 text-[9px] font-bold {{ $ecartHotel['effect'] === 'deny' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700' }}"
                                                                  title="Posé par l'hôtel : {{ $ecartHotel['effect'] === 'deny' ? 'refus' : 'autorisation' }}{{ !empty($ecartHotel['reason']) ? ' — ' . $ecartHotel['reason'] : '' }}">H</span>
                                                        @endif
                                                        @if($nominativesIci !== [])
                                                            <span class="rounded bg-sky-100 px-1 text-[9px] font-bold text-sky-700" title="{{ implode(' ; ', $nominativesIci) }}">N{{ count($nominativesIci) }}</span>
                                                        @endif
                                                    </div>
                                                    @if(in_array($droit, $bornes, true) && !$fige)
                                                        <label class="sr-only" for="portee-{{ md5($cle) }}">Portée</label>
                                                        <select id="portee-{{ md5($cle) }}"
                                                                data-portee-de="{{ $cle }}"
                                                                class="portee mt-1 block w-full rounded border-slate-200 text-[10px] {{ $coche ? '' : 'invisible' }}">
                                                            @foreach($portees as $portee)
                                                                <option value="{{ $portee['valeur'] }}" @selected(($ecartConsole['scope'] ?? 'etablissement') === $portee['valeur'])>{{ $portee['libelle'] }}</option>
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

                {{-- Cumuls ouverts par le lot : à reconduire ou à motiver. --}}
                <div id="panneau-cumuls" class="mb-3 hidden rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-900">
                    <p class="font-semibold">Ce lot ouvre des cumuls de fonctions incompatibles.</p>
                    <ul id="liste-cumuls" class="mt-1.5 list-disc space-y-0.5 pl-5"></ul>
                    <label class="mt-2 inline-flex items-start gap-2 font-semibold">
                        <input type="checkbox" name="derogation" value="1" id="derogation" class="mt-0.5 rounded border-red-300">
                        J'accorde ces dérogations à la séparation des tâches, pour le motif indiqué ci-dessous.
                    </label>
                </div>

                {{-- Aperçu : qui gagne ou perd quoi, calculé par l'établissement. --}}
                <div id="panneau-apercu" class="mb-3 hidden rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs text-slate-700" aria-live="polite"></div>

                <div class="sticky bottom-0 z-40 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-lg">
                    <div class="min-w-[16rem] flex-1">
                        <label for="motif" class="sr-only">Motif</label>
                        <input type="text" id="motif" name="motif" maxlength="255" value="{{ old('motif') }}"
                               placeholder="Motif de la modification — consigné dans l'historique et sur chaque droit modifié"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-slate-500">
                        <p class="mt-1 text-[11px] text-slate-500">
                            <span id="compteur">0</span> case(s) modifiée(s).
                            <span id="exigence" class="hidden font-medium text-amber-700"></span>
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="button" id="voir-apercu" disabled
                                class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                            Voir l'aperçu
                        </button>
                        <button type="submit" id="appliquer" disabled
                                class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300">
                            Appliquer à l'établissement
                        </button>
                    </div>
                </div>

                <div id="ecarts"></div>
            </form>
        </section>

        {{-- ==================== COMPTES ADMINISTRATEURS ==================== --}}
        <section data-panneau="administrateurs" class="{{ $onglet === 'administrateurs' ? '' : 'hidden' }} space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-xs leading-relaxed text-slate-600">
                L'administrateur est le service informatique de l'hôtel : il crée et tient tous les autres comptes,
                managers compris, attribue les rôles et règle la configuration. Il consulte tous les services sans y
                saisir d'opération. Ses comptes ne se créent que d'ici : personne, dans l'établissement, n'accorde un
                niveau égal au sien.
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-left text-xs">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Nom</th>
                            <th class="px-4 py-3">E-mail</th>
                            <th class="px-4 py-3">Dernière connexion</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($administrateurs as $admin)
                            <tr>
                                <td class="px-4 py-3 font-semibold text-slate-800">{{ $admin['name'] }}</td>
                                <td class="px-4 py-3 font-mono text-slate-600">{{ $admin['email'] }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $admin['derniere_connexion'] ? \Illuminate\Support\Carbon::parse($admin['derniere_connexion'])->format('d/m/Y H:i') : 'Jamais' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold {{ $admin['actif'] ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' }}">
                                        {{ $admin['actif'] ? 'Actif' : 'Désactivé' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <form method="POST" action="{{ route($prefixe . 'establishments.admins.update', ['tenant' => $tenant, 'compte' => $admin['id']]) }}"
                                              onsubmit="return confirm('Réinitialiser le mot de passe de {{ addslashes($admin['name']) }} ? Un mot de passe sera tiré au hasard et affiché une fois.');">
                                            @csrf
                                            <input type="hidden" name="action" value="reinitialiser">
                                            <input type="hidden" name="nom" value="{{ $admin['email'] }}">
                                            <button type="submit" class="rounded-lg border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">Réinitialiser le mot de passe</button>
                                        </form>
                                        <form method="POST" action="{{ route($prefixe . 'establishments.admins.update', ['tenant' => $tenant, 'compte' => $admin['id']]) }}"
                                              @if($admin['actif']) onsubmit="return confirm('Désactiver {{ addslashes($admin['name']) }} ? Ce compte ne pourra plus se connecter.');" @endif>
                                            @csrf
                                            <input type="hidden" name="action" value="{{ $admin['actif'] ? 'desactiver' : 'reactiver' }}">
                                            <input type="hidden" name="nom" value="{{ $admin['name'] }}">
                                            <button type="submit" class="rounded-lg border px-2.5 py-1 text-[11px] font-semibold {{ $admin['actif'] ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' : 'border-emerald-200 bg-emerald-50 text-emerald-800 hover:bg-emerald-100' }}">
                                                {{ $admin['actif'] ? 'Désactiver' : 'Réactiver' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-xs text-slate-500">
                                    Aucun administrateur. D'ici sa création, le manager gère les comptes du personnel.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route($prefixe . 'establishments.admins.store', $tenant) }}"
                  class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
                @csrf
                <h3 class="text-sm font-bold text-slate-800 sm:col-span-2">Créer un compte administrateur</h3>
                <div>
                    <label for="admin-nom" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Nom complet</label>
                    <input type="text" id="admin-nom" name="name" required maxlength="255" value="{{ old('name') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-slate-500">
                </div>
                <div>
                    <label for="admin-email" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Adresse e-mail</label>
                    <input type="email" id="admin-email" name="email" required maxlength="255" value="{{ old('email') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-slate-500">
                </div>
                <div>
                    <label for="admin-telephone" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Téléphone</label>
                    <input type="text" id="admin-telephone" name="phone" maxlength="30" value="{{ old('phone') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-slate-500">
                </div>
                <div>
                    <label for="admin-mdp" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Mot de passe <span class="normal-case font-medium">(vide : tiré au hasard)</span></label>
                    <input type="password" id="admin-mdp" name="password" minlength="8" maxlength="255" autocomplete="new-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-slate-500">
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">Créer l'administrateur</button>
                </div>
            </form>
        </section>

        {{-- ==================== EXCEPTIONS ==================== --}}
        <section data-panneau="exceptions" class="{{ $onglet === 'exceptions' ? '' : 'hidden' }} space-y-4">
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-xs leading-relaxed text-slate-600">
                Écarts que l'établissement porte en dehors de la couche de la console. Ils se règlent dans l'application,
                par son administrateur : la console les montre, elle n'y touche pas.
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-800">Exceptions nominatives en vigueur</h3>
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        <tr><th class="px-4 py-2">Personne</th><th class="px-4 py-2">Droit</th><th class="px-4 py-2">Effet</th><th class="px-4 py-2">Posée par</th><th class="px-4 py-2">Motif</th><th class="px-4 py-2">Jusqu'au</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($nominatives as $e)
                            <tr>
                                <td class="px-4 py-2 font-semibold text-slate-800">{{ $nomDuCompte($e['subject_id']) }}</td>
                                <td class="px-4 py-2 font-mono text-slate-600">{{ $e['permission'] }}</td>
                                <td class="px-4 py-2">{{ $e['effect'] === 'deny' ? 'Refus' : 'Autorisation' }}</td>
                                <td class="px-4 py-2">{{ ($e['origin'] ?? 'erp') === 'erp' ? 'Console' : 'Hôtel' }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $e['reason'] ?? '—' }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ !empty($e['expires_at']) ? \Illuminate\Support\Carbon::parse($e['expires_at'])->format('d/m/Y') : 'Sans échéance' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-4 text-center text-slate-500">Aucune.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-800">Restrictions de service</h3>
                <p class="px-4 pt-2 text-[11px] text-slate-500">Posées autrefois depuis la console sur des personnes : elles l'emportent sur leurs rôles.</p>
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        <tr><th class="px-4 py-2">Personne</th><th class="px-4 py-2">Service</th><th class="px-4 py-2">Niveau</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($restrictions as $r)
                            <tr>
                                <td class="px-4 py-2 font-semibold text-slate-800">{{ $nomDuCompte($r['user_id']) }}</td>
                                <td class="px-4 py-2 font-mono text-slate-600">{{ $r['service'] }}</td>
                                <td class="px-4 py-2">{{ $r['niveau'] === 'none' ? 'Exclu' : 'Lecture seule' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-4 text-center text-slate-500">Aucune.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-800">Écarts de rôle posés par l'hôtel</h3>
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        <tr><th class="px-4 py-2">Rôle</th><th class="px-4 py-2">Droit</th><th class="px-4 py-2">Effet</th><th class="px-4 py-2">Motif</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($hotel as $e)
                            <tr>
                                <td class="px-4 py-2 font-semibold text-slate-800">{{ $roles[$e['subject_id']]['name'] ?? $e['subject_id'] }}</td>
                                <td class="px-4 py-2 font-mono text-slate-600">{{ $e['permission'] }}</td>
                                <td class="px-4 py-2">{{ $e['effect'] === 'deny' ? 'Refus' : 'Autorisation' }}</td>
                                <td class="px-4 py-2 text-slate-500">{{ $e['reason'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-4 text-center text-slate-500">Aucun.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ==================== ALERTES ==================== --}}
        <section data-panneau="alertes" class="{{ $onglet === 'alertes' ? '' : 'hidden' }} space-y-4">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-800">Revue des comptes</h3>
                <ul class="divide-y divide-slate-100">
                    @forelse($constats as $c)
                        <li class="px-4 py-3 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold
                                    {{ $c['gravite'] === 'à corriger' ? 'bg-red-100 text-red-700' : ($c['gravite'] === 'à confirmer' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $c['gravite'] }}
                                </span>
                                <span class="font-semibold text-slate-800">{{ $c['constat'] }}</span>
                            </div>
                            <p class="mt-1 text-slate-600">{{ $c['decision'] }}</p>
                            @if(!empty($c['comptes']))
                                <p class="mt-1 font-mono text-[11px] text-slate-500">{{ implode(' · ', $c['comptes']) }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-4 text-center text-xs text-slate-500">Rien à signaler.</li>
                    @endforelse
                </ul>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-800">Dérogations en vigueur dans la couche de la console</h3>
                <ul class="divide-y divide-slate-100">
                    @forelse($derogations as $cle => $e)
                        @php [$slug, $droit] = explode('|', $cle, 2); @endphp
                        <li class="px-4 py-3 text-xs">
                            <span class="font-semibold text-slate-800">{{ $roles[$slug]['name'] ?? $slug }}</span>
                            reçoit <span class="font-mono">{{ $droit }}</span>
                            @foreach(($matrice['cumuls'] ?? [])[$cle] ?? [] as $i)
                                <span class="block text-slate-600">— {{ ($matrice['regles_de_cumul'] ?? [])[$i]['motif'] ?? '' }}</span>
                            @endforeach
                            @if(!empty($e['reason']))
                                <span class="block text-[11px] text-slate-500">Motif : {{ $e['reason'] }}</span>
                            @endif
                        </li>
                    @empty
                        <li class="px-4 py-4 text-center text-xs text-slate-500">Aucune.</li>
                    @endforelse
                </ul>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <h3 class="border-b border-slate-100 px-4 py-3 text-sm font-bold text-slate-800">Exceptions arrivées à échéance</h3>
                <ul class="divide-y divide-slate-100">
                    @forelse($echues as $e)
                        <li class="px-4 py-3 text-xs">
                            <span class="font-semibold text-slate-800">{{ $nomDuCompte($e['subject_id']) }}</span>
                            — <span class="font-mono">{{ $e['permission'] }}</span>
                            ({{ $e['effect'] === 'deny' ? 'refus' : 'autorisation' }}),
                            échue le {{ \Illuminate\Support\Carbon::parse($e['expires_at'])->format('d/m/Y') }}.
                            Elle ne s'applique plus : la renouveler ou la retirer dans l'application.
                        </li>
                    @empty
                        <li class="px-4 py-4 text-center text-xs text-slate-500">Aucune.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        {{-- ==================== HISTORIQUE ==================== --}}
        <section data-panneau="historique" class="{{ $onglet === 'historique' ? '' : 'hidden' }} space-y-3">
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-xs leading-relaxed text-slate-600">
                Chaque enregistrement laisse ici l'état complet de la couche de la console. Revenir à une version la
                renvoie telle quelle, et l'inscrit comme une nouvelle version : l'histoire ne se réécrit pas.
            </div>

            @forelse($versions as $index => $version)
                @php
                    $precedente = $versions[$index + 1] ?? null;
                    $avant = $precedente?->parCase() ?? [];
                    $apres = $version->parCase();
                    $ajoutes = array_diff_key($apres, $avant);
                    $retires = array_diff_key($avant, $apres);
                    $modifies = array_filter(
                        array_intersect_key($apres, $avant),
                        fn ($e, $cle) => ($e['effect'] ?? null) !== ($avant[$cle]['effect'] ?? null)
                            || ($e['scope'] ?? null) !== ($avant[$cle]['scope'] ?? null),
                        ARRAY_FILTER_USE_BOTH
                    );
                @endphp
                <details class="rounded-xl border border-slate-200 bg-white">
                    <summary class="flex cursor-pointer flex-wrap items-center gap-2 px-4 py-3 text-xs">
                        <span class="rounded bg-slate-900 px-1.5 py-0.5 font-mono text-[10px] font-bold text-white">v{{ $version->numero }}</span>
                        <span class="font-semibold text-slate-800">
                            {{ match ($version->nature) { 'etat_initial' => 'État initial', 'retour' => 'Retour', default => 'Modification' } }}
                        </span>
                        <span class="text-slate-500">{{ $version->created_at?->format('d/m/Y H:i') }} · {{ $version->auteur ?? 'Console' }}</span>
                        <span class="text-slate-500">· {{ count($version->ecarts ?? []) }} écart(s)</span>
                        @if($precedente)
                            <span class="text-slate-500">· +{{ count($ajoutes) }} −{{ count($retires) }} ~{{ count($modifies) }}</span>
                        @endif
                        @if($version->derogation)
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-700">Dérogation</span>
                        @endif
                        <span class="w-full text-slate-600">{{ $version->motif }}</span>
                    </summary>
                    <div class="space-y-3 border-t border-slate-100 px-4 py-3 text-xs">
                        @if($precedente)
                            <ul class="space-y-0.5 font-mono text-[11px]">
                                @foreach($ajoutes as $cle => $e)
                                    <li class="text-emerald-700">+ {{ $cle }} → {{ $e['effect'] }}{{ !empty($e['scope']) ? ' (' . $e['scope'] . ')' : '' }}</li>
                                @endforeach
                                @foreach($retires as $cle => $e)
                                    <li class="text-red-700">− {{ $cle }} ({{ $e['effect'] }} retiré)</li>
                                @endforeach
                                @foreach($modifies as $cle => $e)
                                    <li class="text-amber-700">~ {{ $cle }} → {{ $e['effect'] }}{{ !empty($e['scope']) ? ' (' . $e['scope'] . ')' : '' }}</li>
                                @endforeach
                                @if($ajoutes === [] && $retires === [] && $modifies === [])
                                    <li class="font-sans text-slate-500">Mêmes écarts que la version précédente.</li>
                                @endif
                            </ul>
                        @endif

                        @if($index > 0)
                            <form method="POST" action="{{ route($prefixe . 'establishments.permissions.restore', ['tenant' => $tenant, 'version' => $version]) }}"
                                  class="flex flex-wrap items-center gap-2" onsubmit="return confirm('Revenir à la version {{ $version->numero }} ? La couche de la console sera remplacée.');">
                                @csrf
                                <label for="motif-retour-{{ $version->id }}" class="sr-only">Motif du retour</label>
                                <input type="text" id="motif-retour-{{ $version->id }}" name="motif" required maxlength="255"
                                       placeholder="Pourquoi revenir à cette version ?"
                                       class="min-w-[16rem] flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs outline-none focus:border-slate-500">
                                @if($version->derogation)
                                    <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-red-800">
                                        <input type="checkbox" name="derogation" value="1" required class="rounded border-red-300">
                                        Je reconduis ses dérogations
                                    </label>
                                @endif
                                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                    Revenir à cette version
                                </button>
                            </form>
                        @else
                            <p class="text-[11px] text-slate-500">Version en vigueur.</p>
                        @endif
                    </div>
                </details>
            @empty
                <p class="rounded-xl border border-slate-200 bg-white px-4 py-6 text-center text-xs text-slate-500">
                    Aucun enregistrement depuis la console pour l'instant. Le premier conservera aussi l'état trouvé dans l'établissement.
                </p>
            @endforelse
        </section>
    </div>

    <script type="application/json" id="matrice-donnees">{!! json_encode($donneesJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
    <script>
        (function () {
            const donnees = JSON.parse(document.getElementById('matrice-donnees').textContent);

            // ── Onglets ───────────────────────────────────────────────
            // Tous les panneaux sont dans la page : changer d'onglet ne perd
            // rien de ce qu'on a coché.
            document.querySelectorAll('.onglet').forEach((bouton) => {
                bouton.addEventListener('click', () => {
                    const cible = bouton.dataset.onglet;
                    document.querySelectorAll('[data-panneau]').forEach((p) => p.classList.toggle('hidden', p.dataset.panneau !== cible));
                    document.querySelectorAll('.onglet').forEach((b) => {
                        const actif = b === bouton;
                        b.setAttribute('aria-selected', actif ? 'true' : 'false');
                        b.classList.toggle('bg-slate-900', actif);
                        b.classList.toggle('text-white', actif);
                        b.classList.toggle('text-slate-600', !actif);
                        b.classList.toggle('hover:bg-slate-100', !actif);
                        const pastille = b.querySelector('span');
                        if (pastille) {
                            pastille.classList.toggle('bg-white/20', actif);
                            pastille.classList.toggle('bg-slate-100', !actif);
                            pastille.classList.toggle('text-slate-500', !actif);
                        }
                    });
                    const url = new URL(window.location.href);
                    url.searchParams.set('onglet', cible);
                    history.replaceState(null, '', url);
                });
            });

            // ── Matrice ───────────────────────────────────────────────
            const formulaire = document.getElementById('form-matrice');
            const cases = [...document.querySelectorAll('.case-droit')];
            const motif = document.getElementById('motif');
            const compteur = document.getElementById('compteur');
            const exigence = document.getElementById('exigence');
            const derogation = document.getElementById('derogation');
            const boutonApercu = document.getElementById('voir-apercu');
            const boutonAppliquer = document.getElementById('appliquer');
            const panneauApercu = document.getElementById('panneau-apercu');
            const panneauCumuls = document.getElementById('panneau-cumuls');
            const listeCumuls = document.getElementById('liste-cumuls');
            const touchees = new Set();
            let apercuAJour = false;

            const portee = (c) => document.querySelector(`[data-portee-de="${c.dataset.role}|${c.dataset.droit}"]`);
            const cle = (c) => `${c.dataset.role}|${c.dataset.droit}`;

            // Écart de la console tel qu'il était à l'ouverture de l'écran.
            function initial(c) {
                if (!c.dataset.console) return null;
                return { effect: c.dataset.console, scope: c.dataset.porteeInitiale || null, reason: c.dataset.raison || null };
            }

            // Écart voulu pour une case touchée : aucun si elle revient au modèle.
            function voulu(c) {
                const gabarit = c.dataset.gabarit === '1';
                const p = portee(c);
                const restreinte = c.checked && p && p.value !== 'etablissement';
                if (c.checked === gabarit && !restreinte) return null;
                return { effect: c.checked ? 'allow' : 'deny', scope: restreinte ? p.value : null };
            }

            function ecartDe(c) {
                return touchees.has(cle(c)) ? voulu(c) : initial(c);
            }

            function differe(a, b) {
                if (a === null || b === null) return a !== b;
                return a.effect !== b.effect || (a.scope || null) !== (b.scope || null);
            }

            // Le lot complet de la couche : l'établissement la remplace en entier.
            function lot() {
                const ecarts = [];
                cases.forEach((c) => {
                    const e = ecartDe(c);
                    if (e === null) return;
                    const inchange = !differe(e, initial(c));
                    ecarts.push({
                        role: c.dataset.role,
                        permission: c.dataset.droit,
                        effect: e.effect,
                        scope: e.scope,
                        // Un écart inchangé garde son motif ; un nouveau prend celui du lot.
                        reason: inchange ? (initial(c).reason || motif.value) : motif.value,
                    });
                });
                return ecarts;
            }

            function cumulsDuLot() {
                const cumuls = [];
                cases.forEach((c) => {
                    const e = ecartDe(c);
                    c.classList.remove('ring-red-500');
                    if (e === null || e.effect !== 'allow' || c.dataset.gabarit === '1') return;
                    const indices = donnees.cumuls[cle(c)] || [];
                    if (indices.length === 0) return;
                    c.classList.remove('ring-amber-400');
                    c.classList.add('ring-2', 'ring-red-500');
                    indices.forEach((i) => cumuls.push({
                        role: c.dataset.role,
                        droit: c.dataset.droit,
                        regle: donnees.regles[i],
                        nouveau: touchees.has(cle(c)) && differe(e, initial(c)),
                    }));
                });
                return cumuls;
            }

            function recalculer() {
                let changements = 0;
                cases.forEach((c) => {
                    const change = touchees.has(cle(c)) && differe(voulu(c), initial(c));
                    if (change) changements++;
                    c.classList.toggle('ring-2', change || c.dataset.console !== '');
                    c.classList.toggle('ring-amber-400', change || c.dataset.console !== '');
                });
                compteur.textContent = changements;

                const cumuls = cumulsDuLot();
                listeCumuls.innerHTML = '';
                cumuls.forEach((cu) => {
                    const li = document.createElement('li');
                    const nom = donnees.noms[cu.role] || cu.role;
                    li.textContent = `${nom} reçoit ${cu.droit}${cu.nouveau ? '' : ' (dérogation déjà en vigueur)'} — ${cu.regle.motif}`;
                    listeCumuls.appendChild(li);
                });
                panneauCumuls.classList.toggle('hidden', cumuls.length === 0);

                // Sans changement, rien ne part : les exigences ne valent qu'avec un lot.
                const manques = [];
                if (changements > 0 && motif.value.trim() === '') manques.push('indiquez un motif');
                if (changements > 0 && cumuls.length > 0 && !derogation.checked) manques.push('accordez ou retirez les dérogations');
                if (changements > 0 && !apercuAJour) manques.push("consultez l'aperçu");
                exigence.textContent = manques.length ? 'Pour enregistrer : ' + manques.join(', ') + '.' : '';
                exigence.classList.toggle('hidden', manques.length === 0);

                boutonApercu.disabled = changements === 0;
                boutonAppliquer.disabled = changements === 0 || manques.length > 0;
                rafraichirBadges();
            }

            function toucher(c) {
                touchees.add(cle(c));
                apercuAJour = false;
                panneauApercu.classList.add('hidden');
                recalculer();
            }

            function rafraichirBadges() {
                document.querySelectorAll('.module').forEach((module) => {
                    let n = 0;
                    module.querySelectorAll('.case-droit').forEach((c) => { if (ecartDe(c) !== null) n++; });
                    const badge = module.querySelector('.badge-ecarts');
                    badge.querySelector('.compte').textContent = n;
                    badge.classList.toggle('hidden', n === 0);
                });
            }

            cases.forEach((c) => c.addEventListener('change', () => {
                const p = portee(c);
                if (p) p.classList.toggle('invisible', !c.checked);
                toucher(c);
            }));
            document.querySelectorAll('.portee').forEach((p) => p.addEventListener('change', () => {
                const [role, droit] = p.dataset.porteeDe.split('|');
                const c = cases.find((x) => x.dataset.role === role && x.dataset.droit === droit);
                if (c) toucher(c);
            }));
            motif.addEventListener('input', recalculer);
            derogation.addEventListener('change', recalculer);

            // ── Aperçu ────────────────────────────────────────────────
            function puce(texte, classes) {
                const span = document.createElement('span');
                span.className = 'mr-1 mb-1 inline-block rounded px-1.5 py-0.5 font-mono text-[10px] ' + classes;
                span.textContent = texte;
                return span;
            }

            boutonApercu.addEventListener('click', async () => {
                boutonApercu.disabled = true;
                panneauApercu.classList.remove('hidden');
                panneauApercu.textContent = "Calcul de l'aperçu par l'établissement…";

                try {
                    const reponse = await fetch(donnees.apercu, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ ecarts: lot() }),
                    });
                    const corps = await reponse.json();
                    panneauApercu.innerHTML = '';

                    if (!reponse.ok || !corps.ok) {
                        panneauApercu.textContent = corps.message || "L'aperçu n'a pas pu être calculé.";
                        apercuAJour = false;
                    } else {
                        const personnes = corps.apercu.personnes || [];
                        const titre = document.createElement('p');
                        titre.className = 'font-semibold text-slate-800';
                        titre.textContent = personnes.length === 0
                            ? 'Aucune personne ne change de droits avec ce lot.'
                            : `${personnes.length} personne(s) touchée(s) :`;
                        panneauApercu.appendChild(titre);

                        personnes.forEach((p) => {
                            const bloc = document.createElement('div');
                            bloc.className = 'mt-2 border-t border-slate-100 pt-2';
                            const nom = document.createElement('p');
                            nom.className = 'font-semibold text-slate-700';
                            nom.textContent = `${p.name} — ${p.roles.map((r) => donnees.noms[r] || r).join(', ')}`;
                            bloc.appendChild(nom);
                            const droits = document.createElement('div');
                            droits.className = 'mt-1';
                            p.gagnes.forEach((d) => droits.appendChild(puce('+ ' + d, 'bg-emerald-50 text-emerald-700')));
                            p.perdus.forEach((d) => droits.appendChild(puce('− ' + d, 'bg-red-50 text-red-700')));
                            (p.portees || []).forEach((d) => droits.appendChild(puce(`${d.permission} : ${d.avant} → ${d.apres}`, 'bg-amber-50 text-amber-800')));
                            bloc.appendChild(droits);
                            panneauApercu.appendChild(bloc);
                        });
                        apercuAJour = true;
                    }
                } catch (e) {
                    panneauApercu.textContent = "L'établissement n'a pas répondu : l'aperçu n'a pas pu être calculé.";
                    apercuAJour = false;
                }

                recalculer();
            });

            formulaire.addEventListener('submit', (evenement) => {
                if (boutonAppliquer.disabled) {
                    evenement.preventDefault();
                    return;
                }
                const panier = document.getElementById('ecarts');
                panier.innerHTML = '';
                lot().forEach((e, n) => {
                    Object.entries(e).forEach(([champ, valeur]) => {
                        if (valeur === null || valeur === undefined) return;
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = `ecarts[${n}][${champ}]`;
                        input.value = valeur;
                        panier.appendChild(input);
                    });
                });
            });

            // ── Filtrage ──────────────────────────────────────────────
            // Les lignes masquées restent dans le document : masquer n'est pas annuler.
            const recherche = document.getElementById('recherche');
            const filtreRole = document.getElementById('filtre-role');
            const filtreEcart = document.getElementById('filtre-ecarts');
            const resultat = document.getElementById('resultat-filtre');

            function filtrer() {
                const terme = recherche.value.trim().toLowerCase();
                const role = filtreRole.value;
                const ecarts = filtreEcart.checked;
                const actif = terme !== '' || role !== '' || ecarts;
                let trouves = 0;

                document.querySelectorAll('.module').forEach((module) => {
                    let visibles = 0;
                    module.querySelectorAll('tbody tr').forEach((ligne) => {
                        const boites = [...ligne.querySelectorAll('input[type="checkbox"][data-droit]')];
                        const droit = boites[0]?.dataset.droit ?? '';
                        const parLeTexte = terme === '' || droit.toLowerCase().includes(terme);
                        const parLEcart = !ecarts || boites.some((c) =>
                            c.classList.contains('case-droit') && (role === '' || c.dataset.role === role) && ecartDe(c) !== null);
                        const visible = parLeTexte && parLEcart;
                        ligne.classList.toggle('hidden', !visible);
                        if (visible) { visibles++; trouves++; }
                    });
                    module.classList.toggle('hidden', visibles === 0);
                    if (actif && visibles > 0) module.open = true;
                    module.querySelectorAll('[data-colonne]').forEach((cellule) => {
                        cellule.classList.toggle('hidden', role !== '' && cellule.dataset.colonne !== role);
                    });
                });

                resultat.classList.toggle('hidden', !actif);
                resultat.textContent = trouves === 0 ? 'Aucun droit ne correspond.' : `${trouves} droit(s) affiché(s).`;
            }

            recherche.addEventListener('input', filtrer);
            filtreRole.addEventListener('change', filtrer);
            filtreEcart.addEventListener('change', filtrer);
            document.querySelectorAll('[data-plier]').forEach((b) => b.addEventListener('click', () => {
                const ouvrir = b.dataset.plier === 'ouvrir';
                document.querySelectorAll('.module').forEach((m) => (m.open = ouvrir));
            }));

            recalculer();
        })();
    </script>
</body>
</html>
