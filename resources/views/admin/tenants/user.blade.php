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
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold border
                {{ $employe->is_active ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-red-500/10 text-red-400 border-red-500/30' }}">
                <span class="h-1.5 w-1.5 rounded-full {{ $employe->is_active ? 'bg-green-400' : 'bg-red-400' }}"></span>
                {{ $employe->is_active ? 'Compte actif' : 'Compte désactivé' }}
            </span>
            <button type="button"
                    onclick="document.getElementById('modal-delete-user').classList.remove('hidden')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-red-600/90 hover:bg-red-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
                Supprimer
            </button>
        </div>
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

            {{-- Historique d'actions & Traçabilité (Audit Logs) --}}
            <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-extrabold text-slate-800">Historique d'activité &amp; Journal d'audit</h3>
                            <span class="rounded-full bg-slate-100 border border-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-700">
                                {{ count($auditLogs ?? []) }} opération(s)
                            </span>
                        </div>
                        <p class="mt-0.5 text-[11px] text-slate-500">
                            Traçabilité chronologique complète depuis la création du compte jusqu'à ce jour.
                        </p>
                    </div>
                </div>

                <div class="p-6">
                    @if(!empty($auditLogs) && count($auditLogs) > 0)
                        <div class="flow-root">
                            <ul role="list" class="-mb-8">
                                @foreach($auditLogs as $idx => $log)
                                    @php
                                        $logDate = $dateOuNull($log->created_at ?? null);
                                        $isLast = $loop->last;

                                        $badgeClass = match($log->module ?? '') {
                                            'auth' => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'security', 'user_management', 'users' => 'bg-purple-50 text-purple-700 border-purple-200',
                                            'reception', 'bookings' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'cash_register', 'caisse' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'restaurant' => 'bg-orange-50 text-orange-700 border-orange-200',
                                            'housekeeping' => 'bg-teal-50 text-teal-700 border-teal-200',
                                            default => 'bg-slate-50 text-slate-700 border-slate-200',
                                        };

                                        $isTarget = ($log->user_id != $employe->id);
                                    @endphp
                                    <li>
                                        <div class="relative pb-8">
                                            @if(! $isLast)
                                                <span class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true"></span>
                                            @endif
                                            <div class="relative flex items-start space-x-3">
                                                <div>
                                                    <span class="h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-white border
                                                        {{ $isTarget ? 'bg-amber-100 border-amber-300 text-amber-700' : 'bg-slate-100 border-slate-300 text-slate-600' }}">
                                                        @if(($log->module ?? '') === 'auth')
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                                                        @elseif(($log->module ?? '') === 'security' || ($log->module ?? '') === 'users')
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                                        @else
                                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1 pt-0.5">
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <div class="flex items-center gap-2">
                                                            <span class="rounded border px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $badgeClass }}">
                                                                {{ $log->module ?: 'général' }}
                                                            </span>
                                                            @if($isTarget)
                                                                <span class="rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[9px] font-bold text-amber-800">
                                                                    Action subie
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <time class="text-[11px] text-slate-400 font-mono" datetime="{{ $log->created_at }}">
                                                            {{ $logDate ? $logDate->format('d/m/Y à H:i:s') : $log->created_at }}
                                                        </time>
                                                    </div>
                                                    <p class="mt-1 text-xs font-semibold text-slate-800 leading-snug">
                                                        {{ $log->action }}
                                                    </p>
                                                    @if(!empty($log->ip_address) || !empty($log->payload_decoded))
                                                        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-[10px] text-slate-500 font-mono">
                                                            @if(!empty($log->ip_address))
                                                                <span class="inline-flex items-center gap-1 bg-slate-50 border border-slate-200 rounded px-1.5 py-0.5">
                                                                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                                                                    IP: {{ $log->ip_address }}
                                                                </span>
                                                            @endif
                                                            @if(!empty($log->payload_decoded))
                                                                <details class="cursor-pointer">
                                                                    <summary class="text-indigo-600 hover:text-indigo-800 font-sans text-[11px]">Détails techniques</summary>
                                                                    <pre class="mt-1 p-2 bg-slate-900 text-slate-100 rounded-lg text-[10px] overflow-x-auto max-w-full">{{ json_encode($log->payload_decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                </details>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 mb-3">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <p class="text-xs font-semibold text-slate-700">Aucun historique d'activité disponible</p>
                            <p class="text-[11px] text-slate-400 mt-1 max-w-sm mx-auto">
                                Aucune opération enregistrée dans le journal d'audit pour ce compte collaborateur.
                            </p>
                        </div>
                    @endif
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

            {{-- Zone de danger : suppression du compte --}}
            <section class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden">
                <div class="border-b border-red-100 bg-red-50/60 px-5 py-3.5 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-red-900">Zone de danger</h3>
                        <p class="mt-0.5 text-[11px] text-red-700">Suppression du collaborateur depuis l'ERP</p>
                    </div>
                    <span class="rounded-full bg-red-100 p-1.5 text-red-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                </div>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Cette action supprime définitivement ce collaborateur de l'établissement <strong class="text-slate-800">{{ $tenant->name }}</strong>. Ses accès et sessions seront révoqués instantanément.
                    </p>
                    <button type="button"
                            onclick="document.getElementById('modal-delete-user').classList.remove('hidden')"
                            class="w-full rounded-lg bg-red-600 hover:bg-red-700 px-4 py-2.5 text-xs font-bold text-white transition shadow-sm flex items-center justify-center gap-2">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Supprimer ce compte définitivement
                    </button>
                </div>
            </section>
        </div>
    </div>
</main>

{{-- Modale de confirmation de suppression --}}
<div id="modal-delete-user" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden transform transition-all">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-100 text-red-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-base font-bold text-slate-900">Supprimer cet utilisateur ?</h3>
                    <p class="mt-2 text-xs text-slate-600 leading-relaxed">
                        Êtes-vous sûr de vouloir supprimer définitivement le compte de <strong class="text-slate-900 font-semibold">{{ $employe->name }}</strong> (<span class="font-mono text-slate-700">{{ $employe->email }}</span>) ?
                    </p>
                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-[11px] leading-relaxed text-red-800">
                        ⚠️ <strong>Action irréversible :</strong> L'utilisateur n'aura plus aucun accès à l'application de <strong>{{ $tenant->name }}</strong>. Ses sessions ouvertes seront purgées immédiatement et cette suppression sera consignée dans le journal d'audit central.
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
            <button type="button"
                    onclick="document.getElementById('modal-delete-user').classList.add('hidden')"
                    class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition shadow-xs">
                Annuler
            </button>
            <form method="POST" action="{{ route(auth()->user()?->isTechAdmin() ? 'tech.establishments.users.destroy' : 'business.establishments.users.destroy', ['tenant' => $tenant, 'user' => $employe->id]) }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="rounded-lg bg-red-600 hover:bg-red-700 px-4 py-2 text-xs font-bold text-white transition shadow-sm">
                    Confirmer la suppression
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
