@php
    $allModules = \App\Support\ModuleCatalog::all();
    $accentClasses = [
        'indigo'  => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'badge' => 'bg-indigo-100 text-indigo-800'],
        'sky'     => ['bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'border' => 'border-sky-200', 'badge' => 'bg-sky-100 text-sky-800'],
        'teal'    => ['bg' => 'bg-teal-50', 'text' => 'text-teal-700', 'border' => 'border-teal-200', 'badge' => 'bg-teal-100 text-teal-800'],
        'amber'   => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'badge' => 'bg-amber-100 text-amber-800'],
        'purple'  => ['bg' => 'bg-purple-50', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'badge' => 'bg-purple-100 text-purple-800'],
        'emerald' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'badge' => 'bg-emerald-100 text-emerald-800'],
        'blue'    => ['bg' => 'bg-blue-50', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'badge' => 'bg-blue-100 text-blue-800'],
        'rose'    => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'badge' => 'bg-rose-100 text-rose-800'],
        'orange'  => ['bg' => 'bg-orange-50', 'text' => 'text-orange-700', 'border' => 'border-orange-200', 'badge' => 'bg-orange-100 text-orange-800'],
    ];

    $totalStaff = $tenantUsers->count();
    $staffInDepts = $tenantUsers->filter(fn($u) => !empty($u->department_id))->count();
    $staffWithoutDept = $totalStaff - $staffInDepts;

    $modulesByService = [];
    foreach ($allModules as $key => $mod) {
        $srv = $mod['service'] ?? 'app';
        $modulesByService[$srv][$key] = $mod;
    }
@endphp

<div x-data="{
    showCreateModal: false,
    showEditModal: false,
    editDept: { id: null, name: '', code: '', description: '', icon: 'briefcase', accent: 'indigo', sort_order: 10, modules: {} },
    openEdit(dept) {
        let mods = {};
        if (dept.modules && Array.isArray(dept.modules)) {
            dept.modules.forEach(m => { 
                let k = m.key || m.module_key;
                let lvl = m.level || m.default_level || 'write';
                mods[k] = lvl;
            });
        }
        this.editDept = {
            id: dept.id,
            name: dept.name,
            code: dept.code || '',
            description: dept.description || '',
            icon: dept.icon || 'briefcase',
            accent: dept.accent || 'indigo',
            sort_order: dept.sort_order || 1,
            modules: mods
        };
        this.showEditModal = true;
    }
}" class="space-y-6">

    <!-- En-tête & Statistiques -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-extrabold text-slate-800 tracking-tight flex items-center gap-2.5">
                <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
                </svg>
                Départements & Structure Métier
            </h2>
            <p class="text-xs text-slate-500 mt-1">
                Définissez les départements de {{ $tenant->name }} et les modules logiciels rattachés par défaut à chacun d'eux.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" @click="showCreateModal = true"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Nouveau département
            </button>
        </div>
    </div>

    <!-- Bandeau KPI / Répartition -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Départements actifs</p>
            <p class="mt-1 text-2xl font-extrabold text-slate-800">{{ $tenantDepartments->count() }}</p>
            <p class="mt-0.5 text-[11px] text-slate-500">Pôles opérationnels configurés</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Personnel rattaché</p>
            <p class="mt-1 text-2xl font-extrabold text-emerald-600">{{ $staffInDepts }} / {{ $totalStaff }}</p>
            <p class="mt-0.5 text-[11px] text-slate-500">{{ $staffWithoutDept > 0 ? $staffWithoutDept . ' employé(s) sans département' : '100% des employés affectés' }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Services & Modules</p>
            <p class="mt-1 text-2xl font-extrabold text-indigo-600">3 services &bull; {{ count($allModules) }} modules</p>
            <p class="mt-0.5 text-[11px] text-slate-500">PMS, Portail Site Web & GRC</p>
        </div>
    </div>

    <!-- Liste des Départements -->
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
        @forelse($tenantDepartments as $dept)
            @php
                $style = $accentClasses[$dept->accent ?? 'indigo'] ?? $accentClasses['indigo'];
                $deptModules = $dept->modules ?? [];
            @endphp
            <div class="flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-slate-300 transition">
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $style['bg'] }} {{ $style['text'] }} {{ $style['border'] }} border">
                                <i data-lucide="{{ $dept->icon ?? 'briefcase' }}" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 text-sm leading-snug">{{ $dept->name }}</h3>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-mono font-bold uppercase {{ $style['badge'] }}">
                                        {{ $dept->code ?: 'N/A' }}
                                    </span>
                                    <span class="text-[10px] text-slate-400">Ordre : {{ $dept->sort_order }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Actions Dropdown/Menu -->
                        <div class="flex items-center gap-1">
                            <button type="button" @click="openEdit({{ json_encode($dept) }})"
                                    title="Modifier le département"
                                    class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-indigo-600 transition cursor-pointer">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                </svg>
                            </button>
                            <form action="{{ route('tech.establishments.departments.destroy', ['tenant' => $tenant, 'department' => $dept->id]) }}"
                                  method="POST"
                                  onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce département ? Les utilisateurs affectés ne seront plus rattachés à aucun pôle.')"
                                  class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        title="Supprimer le département"
                                        class="rounded-lg p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 transition cursor-pointer">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>

                    @if(!empty($dept->description))
                        <p class="mt-3 text-xs text-slate-600 leading-relaxed line-clamp-2">
                            {{ $dept->description }}
                        </p>
                    @endif

                    <!-- Modules associés -->
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">
                            Modules associés ({{ count($deptModules) }}) :
                        </p>
                        @if(empty($deptModules))
                            <span class="text-[11px] text-slate-400 italic">Aucun module rattaché</span>
                        @else
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($deptModules as $m)
                                    @php
                                        $modKey = is_array($m) ? ($m['key'] ?? '') : (is_object($m) ? $m->module_key : '');
                                        $modLvl = is_array($m) ? ($m['level'] ?? 'write') : (is_object($m) ? $m->default_level : 'write');
                                        $modDef = $allModules[$modKey] ?? null;
                                        $modLabel = $modDef['label'] ?? $modKey;
                                    @endphp
                                    <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[10px] font-medium border
                                        {{ $modLvl === 'write' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-600 border-slate-200' }}"
                                          title="{{ $modLabel }} — {{ $modLvl === 'write' ? 'Lecture/Écriture par défaut' : 'Lecture seule par défaut' }}">
                                        <span>{{ $modLabel }}</span>
                                        <span class="text-[9px] font-bold opacity-75">[{{ $modLvl === 'write' ? 'L/É' : 'L' }}]</span>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Pied de carte : Effectif -->
                <div class="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                    <a href="{{ route('tech.establishments.show', ['tenant' => $tenant, 'section' => 'users']) }}"
                       class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1.5">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                        <span>{{ $dept->users_count ?? 0 }} employé(s)</span>
                    </a>
                    <button type="button" @click="openEdit({{ json_encode($dept) }})"
                            class="text-[11px] font-bold text-slate-500 hover:text-indigo-600 transition">
                        Configurer &rarr;
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 mb-3">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0M12 12.75h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-800">Aucun département configuré</h3>
                <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                    Les départements permettent de regrouper vos modules et de pré-configurer automatiquement les accès des employés.
                </p>
                <div class="mt-4">
                    <button type="button" @click="showCreateModal = true"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-700 transition">
                        + Créer le premier département
                    </button>
                </div>
            </div>
        @endforelse
    </div>

    <!-- MODAL CRÉATION DE DÉPARTEMENT -->
    <div x-show="showCreateModal"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden"
             @click.away="showCreateModal = false">
            <form action="{{ route('tech.establishments.departments.store', $tenant) }}" method="POST" class="flex flex-col min-h-0">
                @csrf
                <div class="bg-slate-950 px-6 py-5 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-indigo-500/20 p-2 text-indigo-400">
                            <i data-lucide="briefcase" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white tracking-wide">Créer un nouveau département</h3>
                            <p class="text-[11px] text-slate-400">Établissement {{ $tenant->name }}</p>
                        </div>
                    </div>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-white transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 space-y-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Nom du département <span class="text-red-500">*</span></label>
                            <input type="text" name="name" required placeholder="Ex: Réception / Front Office"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Code court (Trigramme)</label>
                            <input type="text" name="code" placeholder="Ex: REC, HSK, FNB, DIR" maxlength="10"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Icône (Lucide)</label>
                            <select name="icon" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white outline-none focus:border-indigo-500">
                                <option value="briefcase">Mallette (briefcase)</option>
                                <option value="calendar-check">Calendrier (calendar-check)</option>
                                <option value="sparkles">Étoiles (sparkles)</option>
                                <option value="utensils">Couverts (utensils)</option>
                                <option value="users">Équipe (users)</option>
                                <option value="calculator">Calculatrice (calculator)</option>
                                <option value="laptop">Ordinateur (laptop)</option>
                                <option value="award">Médaille (award)</option>
                                <option value="store">Boutique (store)</option>
                                <option value="shield-check">Bouclier (shield-check)</option>
                                <option value="bed">Lit (bed)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Couleur d'accent</label>
                            <select name="accent" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white outline-none focus:border-indigo-500">
                                <option value="indigo">Indigo</option>
                                <option value="sky">Ciel (Sky)</option>
                                <option value="teal">Teal (Émeraude doux)</option>
                                <option value="amber">Ambre / Doré</option>
                                <option value="purple">Pourpre / Violet</option>
                                <option value="emerald">Émeraude / Vert</option>
                                <option value="blue">Bleu</option>
                                <option value="rose">Rose</option>
                                <option value="orange">Orange</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Ordre d'affichage</label>
                            <input type="number" name="sort_order" value="10" min="1" max="99"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Description</label>
                        <textarea name="description" rows="2" placeholder="Mission et rôle opérationnel de ce département au sein de l'établissement..."
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500"></textarea>
                    </div>

                    <!-- Sélection des modules par service -->
                    <div class="border-t border-slate-200 pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="text-[11px] font-bold uppercase tracking-wide text-slate-700">Modules associés à ce département</h4>
                                <p class="text-[11px] text-slate-500">
                                    Cochez les modules accessibles par défaut pour les employés de ce département.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            @foreach($modulesByService as $serviceKey => $srvModules)
                                <div class="rounded-xl border border-slate-200 p-3.5 bg-slate-50/50">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2.5">
                                        @if($serviceKey === 'app') Service PMS (Wetchah_APP)
                                        @elseif($serviceKey === 'site') Portail Web Public (Wetchah_SITE)
                                        @elseif($serviceKey === 'grc') Contrôle de Gestion & Audit (Wetchah_GRC)
                                        @else Service {{ ucfirst($serviceKey) }}
                                        @endif
                                    </p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                        @foreach($srvModules as $modKey => $mDef)
                                            <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200 hover:border-indigo-200 transition">
                                                <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-800">
                                                    <input type="checkbox" name="modules[]" value="{{ $modKey }}" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                    <span>{{ $mDef['label'] }}</span>
                                                </label>
                                                <select name="levels[{{ $modKey }}]" class="text-[10px] font-medium border border-slate-200 rounded px-1.5 py-0.5 bg-slate-50 text-slate-700">
                                                    <option value="write">Lecture / Écriture</option>
                                                    <option value="read">Lecture seule</option>
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex items-center justify-end gap-3 shrink-0">
                    <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 transition">
                        Annuler
                    </button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                        Créer le département
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL MODIFICATION DE DÉPARTEMENT -->
    <div x-show="showEditModal"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         x-cloak>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-2xl w-full max-h-[90vh] flex flex-col overflow-hidden"
             @click.away="showEditModal = false">
            <form :action="`{{ url('admin/establishments/' . $tenant->id . '/departments') }}/${editDept.id}`" method="POST" class="flex flex-col min-h-0">
                @csrf
                @method('PUT')
                <div class="bg-slate-950 px-6 py-5 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-indigo-500/20 p-2 text-indigo-400">
                            <i data-lucide="briefcase" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white tracking-wide">Modifier le département : <span x-text="editDept.name"></span></h3>
                            <p class="text-[11px] text-slate-400">Mise à jour en temps réel dans la base de {{ $tenant->name }}</p>
                        </div>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-white transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-6 space-y-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Nom du département <span class="text-red-500">*</span></label>
                            <input type="text" name="name" x-model="editDept.name" required
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Code court (Trigramme)</label>
                            <input type="text" name="code" x-model="editDept.code" maxlength="10"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Icône (Lucide)</label>
                            <select name="icon" x-model="editDept.icon" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white outline-none focus:border-indigo-500">
                                <option value="briefcase">Mallette (briefcase)</option>
                                <option value="calendar-check">Calendrier (calendar-check)</option>
                                <option value="sparkles">Étoiles (sparkles)</option>
                                <option value="utensils">Couverts (utensils)</option>
                                <option value="users">Équipe (users)</option>
                                <option value="calculator">Calculatrice (calculator)</option>
                                <option value="laptop">Ordinateur (laptop)</option>
                                <option value="award">Médaille (award)</option>
                                <option value="store">Boutique (store)</option>
                                <option value="shield-check">Bouclier (shield-check)</option>
                                <option value="bed">Lit (bed)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Couleur d'accent</label>
                            <select name="accent" x-model="editDept.accent" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white outline-none focus:border-indigo-500">
                                <option value="indigo">Indigo</option>
                                <option value="sky">Ciel (Sky)</option>
                                <option value="teal">Teal (Émeraude doux)</option>
                                <option value="amber">Ambre / Doré</option>
                                <option value="purple">Pourpre / Violet</option>
                                <option value="emerald">Émeraude / Vert</option>
                                <option value="blue">Bleu</option>
                                <option value="rose">Rose</option>
                                <option value="orange">Orange</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Ordre d'affichage</label>
                            <input type="number" name="sort_order" x-model="editDept.sort_order" min="1" max="99"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wide text-slate-500 mb-1">Description</label>
                        <textarea name="description" x-model="editDept.description" rows="2"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500"></textarea>
                    </div>

                    <!-- Modules associés -->
                    <div class="border-t border-slate-200 pt-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h4 class="text-[11px] font-bold uppercase tracking-wide text-slate-700">Modules associés à ce département</h4>
                                <p class="text-[11px] text-slate-500">
                                    Cochez ou décochez les modules et ajustez leur niveau d'accès.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            @foreach($modulesByService as $serviceKey => $srvModules)
                                <div class="rounded-xl border border-slate-200 p-3.5 bg-slate-50/50">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2.5">
                                        @if($serviceKey === 'app') Service PMS (Wetchah_APP)
                                        @elseif($serviceKey === 'site') Portail Web Public (Wetchah_SITE)
                                        @elseif($serviceKey === 'grc') Contrôle de Gestion & Audit (Wetchah_GRC)
                                        @else Service {{ ucfirst($serviceKey) }}
                                        @endif
                                    </p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                        @foreach($srvModules as $modKey => $mDef)
                                            <div class="flex items-center justify-between p-2 rounded-lg bg-white border border-slate-200 hover:border-indigo-200 transition">
                                                <label class="flex items-center gap-2 cursor-pointer select-none text-xs font-semibold text-slate-800">
                                                    <input type="checkbox" name="modules[]" value="{{ $modKey }}"
                                                           :checked="editDept.modules && (editDept.modules['{{ $modKey }}'] !== undefined)"
                                                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                    <span>{{ $mDef['label'] }}</span>
                                                </label>
                                                <select name="levels[{{ $modKey }}]"
                                                        :value="(editDept.modules && editDept.modules['{{ $modKey }}']) ? editDept.modules['{{ $modKey }}'] : 'write'"
                                                        class="text-[10px] font-medium border border-slate-200 rounded px-1.5 py-0.5 bg-slate-50 text-slate-700">
                                                    <option value="write">Lecture / Écriture</option>
                                                    <option value="read">Lecture seule</option>
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex items-center justify-end gap-3 shrink-0">
                    <button type="button" @click="showEditModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 transition">
                        Annuler
                    </button>
                    <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                        Enregistrer les modifications
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
