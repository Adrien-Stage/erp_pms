@php
    $tabMenus = [
        'finance' => ['label' => 'Vue financière', 'icon' => 'chart'],
        'info'    => ['label' => 'Informations', 'icon' => 'building'],
        'users'   => ['label' => 'Utilisateurs', 'icon' => 'users'],
    ];
    $section = $section ?? request('section', 'finance');
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $tenant->name }} — Espace business</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased font-body">

    <!-- Top bar -->
    <header class="sticky top-0 z-30 w-full bg-[#0f172a] border-b border-slate-800 text-white shadow-md">
        <div class="mx-auto px-5 lg:px-8 flex items-center justify-between h-14">
            <div class="flex items-center gap-4">
                <a href="{{ route('business.dashboard', ['tab' => 'establishments']) }}" class="flex items-center gap-2 text-slate-400 hover:text-white transition text-xs font-semibold">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" /></svg>
                    Mes établissements
                </a>
                <div class="h-5 w-px bg-slate-700"></div>
                <div class="flex items-center gap-3">
                    @if(!empty($tenant->settings['logo']))
                        <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="{{ $tenant->name }}" class="h-7 object-contain">
                    @else
                        <div class="h-7 w-7 rounded bg-indigo-600/20 flex items-center justify-center text-indigo-300 font-bold text-xs">
                            {{ strtoupper(mb_substr($tenant->name, 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <h1 class="text-sm font-bold text-white leading-none">{{ $tenant->name }}</h1>
                        <p class="text-[10px] text-slate-400 font-mono">{{ $tenant->slug }}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3">
                @php
                    $dockerBadge = match($tenant->docker_status) {
                        'running'  => ['label' => 'En ligne', 'classes' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30', 'dot' => 'bg-emerald-400'],
                        'creating' => ['label' => 'Démarrage…', 'classes' => 'bg-amber-500/10 text-amber-400 border-amber-500/30', 'dot' => 'bg-amber-400 animate-pulse'],
                        'error'    => ['label' => 'Erreur', 'classes' => 'bg-red-500/10 text-red-400 border-red-500/30', 'dot' => 'bg-red-400'],
                        default    => ['label' => 'Hors ligne', 'classes' => 'bg-slate-500/10 text-slate-400 border-slate-500/30', 'dot' => 'bg-slate-400'],
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $dockerBadge['classes'] }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $dockerBadge['dot'] }}"></span>
                    {{ $dockerBadge['label'] }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-bold text-slate-300 hover:bg-slate-700 hover:text-white transition">Déconnexion</button>
                </form>
            </div>
        </div>
    </header>

    {{-- Onglets horizontaux --}}
    <div class="sticky top-14 z-20 bg-white border-b border-slate-200 shadow-sm">
        <nav class="mx-auto px-5 lg:px-8 flex items-center gap-1">
            @foreach($tabMenus as $key => $menu)
                <a href="{{ route('business.establishments.show', ['tenant' => $tenant, 'section' => $key]) }}"
                   class="inline-flex items-center gap-2 px-4 py-3.5 text-xs font-bold border-b-2 -mb-px transition {{ $section === $key ? 'text-indigo-700 border-indigo-600' : 'text-slate-500 border-transparent hover:text-slate-800 hover:border-slate-300' }}">
                    @if($menu['icon'] === 'building')
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" /></svg>
                    @elseif($menu['icon'] === 'users')
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                    @else
                        <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                    @endif
                    {{ $menu['label'] }}
                </a>
            @endforeach
        </nav>
    </div>

    <div class="min-h-[calc(100vh-6.5rem)]">
        <!-- Contenu -->
        <main class="mx-auto px-5 lg:px-8 py-8">
            @if(session('success'))
                <div class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4 text-xs font-bold text-green-800 shadow-sm flex items-center gap-2">
                    <svg class="h-4 w-4 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4 text-xs font-bold text-red-800 shadow-sm">{{ session('error') }}</div>
            @endif

            {{-- ==================== INFORMATIONS ==================== --}}
            @if($section === 'info')
                <div class="mb-6">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Informations</h2>
                    <p class="text-xs text-slate-500 mt-1">Fiche d'identité de votre établissement {{ $tenant->name }}.</p>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100"><h3 class="text-sm font-bold text-slate-800">Informations générales</h3></div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                            <div><dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nom</dt><dd class="mt-1 font-semibold text-slate-800">{{ $tenant->name }}</dd></div>
                            <div><dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Pays / Ville</dt><dd class="mt-1 font-semibold text-slate-800">{{ $tenant->settings['country'] ?? 'N/A' }}{{ !empty($tenant->settings['city']) ? ', ' . $tenant->settings['city'] : '' }}</dd></div>
                            <div><dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Adresse</dt><dd class="mt-1 font-semibold text-slate-800">{{ $tenant->address ?? 'N/A' }}</dd></div>
                            <div><dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Devise</dt><dd class="mt-1 font-semibold text-slate-800">{{ $tenant->currency }}</dd></div>
                            <div><dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Téléphone</dt><dd class="mt-1 font-semibold text-slate-800">{{ $tenant->phone ?? 'N/A' }}</dd></div>
                            <div><dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Email</dt><dd class="mt-1 font-semibold text-slate-800">{{ $tenant->email ?? 'N/A' }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mt-6">
                    <div class="px-6 py-4 border-b border-slate-100"><h3 class="text-sm font-bold text-slate-800">Modules & accès</h3></div>
                    <div class="p-6 space-y-4">
                        <div>
                            <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Modules actifs</dt>
                            <div class="flex flex-wrap gap-1.5">
                                @forelse($tenant->modules ?? [] as $m)
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">{{ ucfirst($m) }}</span>
                                @empty
                                    <span class="text-xs text-slate-400 italic">Aucun module optionnel activé.</span>
                                @endforelse
                            </div>
                        </div>
                        @if($tenant->docker_status === 'running' && $tenant->app_port)
                        <div>
                            <dt class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Lien d'accès à l'application</dt>
                            <a href="http://localhost:{{ $tenant->app_port }}" target="_blank" class="text-sm font-mono text-indigo-600 hover:text-indigo-800 hover:underline">http://localhost:{{ $tenant->app_port }}</a>
                        </div>
                        @endif
                    </div>
                </div>

            {{-- ==================== UTILISATEURS ==================== --}}
            @elseif($section === 'users')
                <div class="mb-6 flex items-baseline justify-between" x-data="{
                    show: false, submitting: false, errorMsg: '', generatedPassword: null,
                    name: '', email: '', phone: '',
                    reset() { this.name=''; this.email=''; this.phone=''; this.errorMsg=''; this.generatedPassword=null; },
                    submit() {
                        this.submitting = true; this.errorMsg = '';
                        fetch('{{ route('business.establishments.create-manager', $tenant) }}', {
                            method: 'POST',
                            headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}', 'Accept':'application/json' },
                            body: JSON.stringify({ name: this.name, email: this.email, phone: this.phone })
                        })
                        .then(r => r.json().then(d => ({ status: r.status, body: d })))
                        .then(res => {
                            this.submitting = false;
                            if (res.status === 201) { this.generatedPassword = res.body.generated_password; }
                            else { this.errorMsg = res.body.message || 'Une erreur est survenue.'; }
                        })
                        .catch(() => { this.submitting = false; this.errorMsg = 'Impossible de contacter le serveur.'; });
                    }
                }">
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Utilisateurs</h2>
                        <p class="text-xs text-slate-500 mt-1">{{ $tenantUsers->count() }} utilisateur(s) dans {{ $tenant->name }}.</p>
                    </div>
                    <button type="button" @click="show = true; reset()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Créer un manager
                    </button>

                    {{-- Modale création manager --}}
                    <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-transition.opacity>
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden max-w-md w-full" @click.away="if(!submitting){ show = generatedPassword ? show : false }">
                            <div class="bg-slate-950 px-6 py-5 flex items-center gap-3">
                                <div class="rounded-lg bg-indigo-500/20 p-2"><svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 19.235V18a3.75 3.75 0 013.75-3.75h1.5A3.75 3.75 0 0112 18v1.235" /></svg></div>
                                <h3 class="text-sm font-bold text-white">Créer un manager — <span class="font-mono">{{ $tenant->slug }}</span></h3>
                            </div>
                            <template x-if="!generatedPassword">
                                <form @submit.prevent="submit()" class="p-6 space-y-4">
                                    <p class="text-xs text-slate-600 leading-relaxed">Crée un compte manager (directeur) dans cet établissement. Un mot de passe sera généré automatiquement.</p>
                                    <div x-show="errorMsg" x-cloak class="rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-xs text-red-700" x-text="errorMsg"></div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Nom complet</label>
                                        <input type="text" x-model="name" required class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Email</label>
                                        <input type="email" x-model="email" required class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1">Téléphone <span class="normal-case text-slate-400">(optionnel)</span></label>
                                        <input type="text" x-model="phone" class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                    </div>
                                    <div class="flex items-center justify-end gap-3 pt-1">
                                        <button type="button" @click="show = false" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Annuler</button>
                                        <button type="submit" :disabled="submitting" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition disabled:opacity-60 cursor-pointer" x-text="submitting ? 'Création…' : 'Créer le manager'"></button>
                                    </div>
                                </form>
                            </template>
                            <template x-if="generatedPassword">
                                <div class="p-6 space-y-4 text-center">
                                    <div class="w-12 h-12 rounded-full bg-emerald-100 flex items-center justify-center mx-auto"><svg class="h-6 w-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                                    <h3 class="text-sm font-bold text-slate-800">Manager créé</h3>
                                    <p class="text-xs text-slate-500">Transmettez ces identifiants au directeur :</p>
                                    <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-left space-y-1">
                                        <p class="text-xs"><span class="text-slate-400">Email :</span> <span class="font-mono font-semibold" x-text="email"></span></p>
                                        <p class="text-xs"><span class="text-slate-400">Mot de passe :</span> <span class="font-mono font-semibold text-indigo-600" x-text="generatedPassword"></span></p>
                                    </div>
                                    <button type="button" @click="show = false; window.location.reload()" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition cursor-pointer">Terminé</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    @if($tenantUsers->isEmpty())
                        <div class="p-10 text-center text-sm text-slate-400">Aucun utilisateur — ou établissement momentanément injoignable.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                                        <th class="px-6 py-3">Nom</th><th class="px-3 py-3">Email</th><th class="px-3 py-3">Téléphone</th><th class="px-3 py-3">Rôle</th><th class="px-6 py-3 text-center">Statut</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    @foreach($tenantUsers as $u)
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="px-6 py-3 font-bold text-slate-800">{{ $u->name }}</td>
                                            <td class="px-3 py-3 text-slate-600">{{ $u->email }}</td>
                                            <td class="px-3 py-3 text-slate-500">{{ $u->phone ?? '—' }}</td>
                                            <td class="px-3 py-3"><span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">{{ $u->role }}</span></td>
                                            <td class="px-6 py-3 text-center">
                                                <span class="inline-flex items-center gap-1.5 text-[10px] font-bold {{ $u->is_active ? 'text-emerald-600' : 'text-red-500' }}">
                                                    <span class="h-1.5 w-1.5 rounded-full {{ $u->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                                    {{ $u->is_active ? 'Actif' : 'Inactif' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

            {{-- ==================== VUE FINANCIÈRE ==================== --}}
            @elseif($section === 'finance')
                <div x-data="{
                        period: 'month', loading: true, data: null,
                        periods: { today:'Aujourd\'hui', week:'Semaine', month:'Mois', year:'Année' },
                        fmt(c) { return new Intl.NumberFormat('fr-FR').format(Math.round((c||0)/100)); },
                        async load() {
                            this.loading = true;
                            try {
                                const url = new URL('{{ route('business.establishments.finance-data', $tenant) }}', window.location.origin);
                                url.searchParams.set('period', this.period);
                                const r = await fetch(url, { headers: { 'Accept':'application/json' } });
                                this.data = await r.json();
                            } catch(e) { this.data = null; }
                            this.loading = false;
                        },
                        line(vals, w, h) {
                            if (!vals || !vals.length) return '';
                            const max = Math.max(...vals, 1); const sx = vals.length>1 ? w/(vals.length-1) : 0;
                            return vals.map((v,i)=>`${i===0?'M':'L'} ${(i*sx).toFixed(1)} ${(h-(v/max)*h).toFixed(1)}`).join(' ');
                        },
                        area(vals, w, h) {
                            if (!vals || !vals.length) return '';
                            return this.line(vals,w,h) + ` L ${w} ${h} L 0 ${h} Z`;
                        },
                        get totals() {
                            if (!this.data || !this.data.series) return [];
                            const s = this.data.series, n = (s.labels||[]).length, out = [];
                            for (let i=0;i<n;i++) out.push((s.hotel[i]||0)+(s.restaurant[i]||0)+(s.shop[i]||0));
                            return out;
                        }
                     }" x-init="load()">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                        <div>
                            <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Vue financière</h2>
                            <p class="text-xs text-slate-500 mt-1">Revenus et performance de {{ $tenant->name }}.</p>
                        </div>
                        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5">
                            <template x-for="(label,key) in periods" :key="key">
                                <button type="button" @click="period=key; load()" class="px-3 py-1.5 text-xs font-semibold rounded-md transition" :class="period===key ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-800'" x-text="label"></button>
                            </template>
                        </div>
                    </div>

                    <div x-show="loading && !data" class="grid grid-cols-1 sm:grid-cols-4 gap-4"><template x-for="i in 4" :key="i"><div class="h-24 rounded-xl bg-slate-100 animate-pulse"></div></template></div>

                    <template x-if="data && !data.reachable">
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-8 text-center">
                            <p class="text-sm font-bold text-amber-800">Données financières indisponibles</p>
                            <p class="text-xs text-amber-700 mt-1">L'établissement est momentanément hors ligne ou n'expose pas encore ses données. Réessayez plus tard.</p>
                        </div>
                    </template>

                    <template x-if="data && data.reachable">
                        <div>
                            {{-- KPI --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                                <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-indigo-600 to-indigo-700 text-white p-5 shadow-sm">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-indigo-100">Revenu</p>
                                    <p class="text-2xl font-extrabold mt-2 leading-none"><span x-text="fmt(data.summary.revenue.total)"></span> <span class="text-xs text-indigo-200" x-text="data.summary.currency"></span></p>
                                    <div class="mt-2 text-[11px] text-indigo-100" x-show="data.summary.revenue_previous">
                                        <template x-if="data.summary.revenue_previous.total > 0">
                                            <span x-text="(((data.summary.revenue.total - data.summary.revenue_previous.total) / data.summary.revenue_previous.total * 100).toFixed(1)) + '% vs période préc.'"></span>
                                        </template>
                                    </div>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Occupation</p>
                                    <p class="text-2xl font-extrabold text-slate-800 mt-2 leading-none"><span x-text="data.summary.occupancy.rate"></span>%</p>
                                    <p class="text-[11px] text-slate-400 mt-2"><span x-text="data.summary.occupancy.rooms_occupied"></span>/<span x-text="data.summary.occupancy.rooms_total"></span> chambres</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Réservations</p>
                                    <p class="text-2xl font-extrabold text-slate-800 mt-2 leading-none" x-text="data.summary.bookings.total"></p>
                                    <p class="text-[11px] text-slate-400 mt-2">sur la période</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Écart de caisse</p>
                                    <p class="text-2xl font-extrabold mt-2 leading-none" :class="data.summary.cash.total_discrepancy < 0 ? 'text-red-600' : (data.summary.cash.total_discrepancy > 0 ? 'text-amber-600' : 'text-emerald-600')"><span x-text="fmt(data.summary.cash.total_discrepancy)"></span></p>
                                    <p class="text-[11px] text-slate-400 mt-2"><span x-text="data.summary.cash.sessions_with_gap"></span> caisse(s) avec écart</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                                {{-- Graphe --}}
                                <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                                        <h3 class="text-sm font-bold text-slate-800">Évolution du chiffre d'affaires</h3>
                                        <div class="flex items-center gap-3 text-[10px] text-slate-500">
                                            <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-indigo-500"></span>Hôtel</span>
                                            <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-amber-500"></span>Resto</span>
                                            <span class="flex items-center gap-1"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>Boutique</span>
                                        </div>
                                    </div>
                                    <div class="p-5">
                                        <template x-if="data.series && totals.some(v=>v>0)">
                                            <div>
                                                <svg viewBox="0 0 600 170" class="w-full h-40" preserveAspectRatio="none">
                                                    <defs><linearGradient id="finGrad" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#6366f1" stop-opacity="0.22"/><stop offset="100%" stop-color="#6366f1" stop-opacity="0"/></linearGradient></defs>
                                                    <path :d="area(totals,600,170)" fill="url(#finGrad)"></path>
                                                    <path :d="line(totals,600,170)" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-linejoin="round"></path>
                                                </svg>
                                                <div class="flex justify-between text-[9px] text-slate-400 mt-1.5">
                                                    <span x-text="data.series.labels[0] ?? ''"></span>
                                                    <span x-text="data.series.labels[data.series.labels.length-1] ?? ''"></span>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="!data.series || !totals.some(v=>v>0)"><div class="h-40 flex items-center justify-center text-xs text-slate-400">Aucun revenu sur cette période.</div></template>
                                    </div>
                                </div>

                                {{-- Alertes de l'établissement --}}
                                <div class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                                    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
                                        <h3 class="text-sm font-bold text-slate-800">Alertes</h3>
                                        <span class="ml-auto text-[10px] font-bold px-2 py-0.5 rounded-full" :class="data.alerts.length>0 ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'" x-text="data.alerts.length"></span>
                                    </div>
                                    <div class="max-h-[320px] overflow-y-auto divide-y divide-slate-50">
                                        <template x-for="(a,i) in data.alerts" :key="i">
                                            <div class="px-5 py-3 flex items-start gap-2">
                                                <span class="mt-0.5 h-2 w-2 rounded-full shrink-0" :class="a.severity==='high' ? 'bg-red-500' : 'bg-amber-500'"></span>
                                                <div>
                                                    <p class="text-xs font-semibold text-slate-800" x-text="a.title"></p>
                                                    <p class="text-[11px] text-slate-500 leading-snug mt-0.5" x-text="a.message"></p>
                                                    <p class="text-[10px] text-slate-400 mt-1" x-text="a.at"></p>
                                                </div>
                                            </div>
                                        </template>
                                        <template x-if="data.alerts.length===0">
                                            <div class="px-5 py-10 text-center text-xs text-slate-500">Aucune anomalie détectée. 👍</div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            @endif
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => { if (window.refreshLucideIcons) window.refreshLucideIcons(); });
    </script>
</body>
</html>
