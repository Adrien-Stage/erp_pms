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
            <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2.5 py-1 text-[10px] font-bold text-slate-600">
                {{ count($employe->roles) }} accès module
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

                    <div>
                        <label for="f-role" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Rôle principal</label>
                        <input type="text" id="f-role" name="role" list="f-role-list" value="{{ old('role', $employe->role) }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <datalist id="f-role-list">
                            @foreach($rolesAssignables as $r)<option value="{{ $r->slug }}">{{ $r->name }}</option>@endforeach
                        </datalist>
                        <p class="mt-1 text-[11px] text-slate-400">
                            Rôle historique, encore lu par wetchah_app pour les employés sans accès détaillé ci-dessous.
                        </p>
                    </div>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-extrabold text-slate-800">Accès par module</h3>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        Cochez les modules auxquels cette personne accède, et pour chacun son niveau.
                        <span class="text-slate-400">La lecture seule laisse consulter les écrans sans pouvoir y agir.</span>
                    </p>
                </div>

                <div class="p-6">
                    @if($rolesParModule->isEmpty())
                        <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-xs text-slate-500">
                            Aucun rôle assignable n'a pu être lu dans cet établissement.<br>
                            <span class="text-[11px] text-slate-400">
                                Sa base n'est peut-être pas joignable, ou sa version est antérieure aux rôles par module.
                            </span>
                        </p>
                    @else
                        <div class="space-y-5">
                            @foreach($rolesParModule as $module => $roles)
                                <div>
                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $module }}</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        @foreach($roles as $r)
                                            @php $actif = in_array((int) $r->id, $rolesActuels, true); @endphp
                                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border p-2.5 transition hover:border-indigo-300 hover:bg-indigo-50/40
                                                {{ $actif ? 'border-indigo-300 bg-indigo-50/40' : 'border-slate-200' }}">
                                                <input type="checkbox" name="roles[]" value="{{ $r->id }}" @checked($actif)
                                                       class="h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-xs font-semibold text-slate-800">{{ $r->name }}</span>
                                                    @if($r->description)
                                                        <span class="block truncate text-[10px] text-slate-400">{{ $r->description }}</span>
                                                    @endif
                                                </span>
                                                <select name="levels[{{ $r->id }}]"
                                                        class="shrink-0 rounded-md border border-slate-300 px-1.5 py-1 text-[10px] outline-none focus:border-indigo-500">
                                                    <option value="write" @selected(($niveauActuel[(int) $r->id] ?? 'write') === 'write')>Lecture/écriture</option>
                                                    <option value="read" @selected(($niveauActuel[(int) $r->id] ?? null) === 'read')>Lecture seule</option>
                                                </select>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-between gap-4 border-t border-slate-100 bg-slate-50 px-6 py-4">
                    <p class="text-[10px] text-slate-400">
                        Un module décoché retire l'accès : l'écran présente toujours l'ensemble des rôles.
                    </p>
                    <button type="submit"
                            class="shrink-0 rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-indigo-700">
                        Enregistrer
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
