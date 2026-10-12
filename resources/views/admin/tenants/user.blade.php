@php
    /**
     * Fiche d'un employé d'un établissement, en lecture seule.
     *
     * Attend : $tenant, $employe, $estControleur
     *
     * Le compte se gère dans l'application, par l'administrateur de
     * l'établissement. La console n'y écrit plus ; elle donne seulement
     * l'accès au portail GRC d'un contrôleur de gestion, dont la base est à
     * part.
     */
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

    // Restrictions posées autrefois depuis la console, module par module :
    // seules l'exclusion et la lecture seule retirent encore quelque chose.
    $restrictions = collect($employe->module_permissions ?? [])
        ->filter(fn ($niveau) => in_array($niveau, ['none', 'read'], true));
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <x-favicon />
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
            <a href="{{ route(auth()->user()?->isTechAdmin() ? 'tech.dashboard' : 'business.dashboard') }}"
               class="shrink-0" title="Wetchah ERP">
                <x-brand variant="mark" class="h-7" />
            </a>
            <div class="h-5 w-px bg-slate-700"></div>
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
        @if(!empty($employe->department_name))
            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 border border-slate-200 px-2.5 py-1 text-[10px] font-bold text-slate-700">
                <i data-lucide="{{ $employe->department_icon ?? 'briefcase' }}" class="h-3.5 w-3.5 text-indigo-600"></i>
                <span>{{ $employe->department_name }}</span>
                <span class="text-[9px] font-mono px-1 py-0.2 bg-slate-200/70 text-slate-600 rounded">{{ $employe->department_code ?? '' }}</span>
            </span>
        @endif
    </div>

    <div class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-xs leading-relaxed text-indigo-900">
        Ce compte se gère dans l'application de {{ $tenant->name }}, par son administrateur. La console le consulte ;
        ses droits se règlent dans <a href="{{ route('tech.establishments.permissions', $tenant) }}" class="font-semibold underline">Droits &amp; rôles</a>.
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-6">
            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-extrabold text-slate-800">Rôles</h3>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        Les affectations font foi. Une affectation en lecture seule ne donne que les droits de consultation du rôle.
                    </p>
                </div>
                <div class="p-6">
                    @if(!empty($employe->roles))
                        <ul class="space-y-3">
                            @foreach($employe->roles as $r)
                                <li class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-bold text-slate-800">{{ $r['name'] }}</p>
                                        <p class="font-mono text-[10px] text-slate-400">{{ $r['slug'] }}</p>
                                        @if(!empty($r['description']))
                                            <p class="mt-1 text-[11px] leading-relaxed text-slate-500">{{ $r['description'] }}</p>
                                        @endif
                                    </div>
                                    <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold
                                        {{ ($r['level'] ?? null) === 'read' ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
                                        {{ ($r['level'] ?? null) === 'read' ? 'Lecture seule' : 'Plein exercice' }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @elseif($employe->role)
                        <p class="text-xs text-slate-600">
                            Aucune affectation : le compte n'existe que par sa colonne héritée,
                            <span class="font-mono font-semibold">{{ $employe->role }}</span>.
                        </p>
                    @else
                        <p class="text-xs text-amber-700">Aucun rôle : ce compte ne détient aucun droit.</p>
                    @endif
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-sm font-extrabold text-slate-800">Restrictions de service</h3>
                    <p class="mt-0.5 text-[11px] text-slate-500">
                        Posées autrefois depuis la console : elles l'emportent sur les rôles. Elles deviendront des exceptions
                        nominatives, réglées par l'administrateur de l'établissement.
                    </p>
                </div>
                <div class="p-6">
                    @forelse($restrictions as $service => $niveau)
                        <div class="flex items-center justify-between py-1.5 text-xs">
                            <span class="font-mono text-slate-700">{{ $service }}</span>
                            <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold
                                {{ $niveau === 'none' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                                {{ $niveau === 'none' ? 'Exclu' : 'Lecture seule' }}
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">Aucune : les rôles s'appliquent tels quels.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-3.5">
                    <h3 class="text-sm font-extrabold text-slate-800">État du compte</h3>
                </div>
                <dl class="divide-y divide-slate-100 text-xs">
                    @php
                        $creele    = $dateOuNull($employe->created_at ?? null);
                        $connexion = $dateOuNull($employe->last_login_at ?? null);
                        $verifie   = $dateOuNull($employe->email_verified_at ?? null);
                    @endphp
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-slate-500">Identifiant</dt>
                        <dd class="font-mono text-slate-700">#{{ $employe->id }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-5 py-3">
                        <dt class="text-slate-500">Téléphone</dt>
                        <dd class="text-slate-700">{{ $employe->phone ?: '—' }}</dd>
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
            </section>

            @if($estControleur && $tenant->hasGrc())
                {{-- Le GRC tient sa propre base : un mot de passe ne s'y
                     reporte qu'au moment où on le saisit, il y est haché. --}}
                <section class="bg-white rounded-xl border border-teal-200 shadow-sm overflow-hidden">
                    <div class="border-b border-teal-100 bg-teal-50/60 px-5 py-3.5">
                        <h3 class="text-sm font-extrabold text-teal-900">Accès au portail GRC</h3>
                        <p class="mt-0.5 text-[11px] text-teal-800">Donne ou renouvelle l'accès de ce contrôleur de gestion au portail.</p>
                    </div>
                    <form method="POST" action="{{ route('tech.establishments.users.grc', ['tenant' => $tenant, 'user' => $employe->id]) }}" class="space-y-3 p-5">
                        @csrf
                        <div>
                            <label for="grc-password" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Mot de passe du portail</label>
                            <input type="password" id="grc-password" name="password" required minlength="8" autocomplete="new-password"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                        </div>
                        <div>
                            <label for="grc-password-confirmation" class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-400">Confirmation</label>
                            <input type="password" id="grc-password-confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500">
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-teal-600 px-4 py-2.5 text-xs font-bold text-white transition hover:bg-teal-700">
                            Enregistrer l'accès au portail
                        </button>
                        <a href="{{ $tenant->grcUrl() }}/login" target="_blank" class="block text-center font-mono text-[11px] text-teal-700 hover:underline">{{ $tenant->grcUrl() }}/login</a>
                    </form>
                </section>
            @endif
        </div>
    </div>
</main>

</body>
</html>
