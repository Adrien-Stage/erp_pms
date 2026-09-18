@php
    $rolesParModule = collect($tenantRoles)->groupBy(fn ($r) => $r->module ?: 'autre');

    $deptCatalogMap = [];
    foreach ($tenantDepartments ?? [] as $dept) {
        $deptModules = [];
        if (!empty($dept->modules)) {
            foreach ($dept->modules as $m) {
                $k = is_array($m) ? ($m['key'] ?? '') : ($m->module_key ?? '');
                $lvl = is_array($m) ? ($m['level'] ?? 'write') : ($m->default_level ?? 'write');
                if ($k) { $deptModules[$k] = $lvl; }
            }
        }
        $deptCatalogMap[$dept->id] = [
            'name'    => $dept->name,
            'code'    => $dept->code,
            'modules' => $deptModules,
        ];
    }
@endphp

<div id="tenant-user-create-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col">
        <form method="POST" action="{{ route('tech.establishments.users.store', $tenant) }}" id="tenant-user-create-form" class="flex min-h-0 flex-col">
            @csrf

            <div class="border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold text-slate-800">Ajouter un employé</h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Création directe dans la base de {{ $tenant->name }} avec affectation de département et pré-sélection des modules.
                    </p>
                </div>
                <button type="button" onclick="window.closeTenantUserCreate()" class="text-slate-400 hover:text-slate-700 transition">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="create-tu-name" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Nom complet <span class="text-red-500">*</span></label>
                        <input type="text" id="create-tu-name" name="name" required placeholder="Ex: Marie Mbarga"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="create-tu-email" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Email <span class="text-red-500">*</span></label>
                        <input type="email" id="create-tu-email" name="email" required placeholder="m.mbarga@hotel.com"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="create-tu-phone" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Téléphone</label>
                        <input type="text" id="create-tu-phone" name="phone" placeholder="+237 600 000 000"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="create-tu-password" class="block text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                Mot de passe <span class="text-red-500">*</span>
                            </label>
                            <button type="button" onclick="generateCreatePassword()" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800">
                                Générer auto
                            </button>
                        </div>
                        <input type="text" id="create-tu-password" name="password" required placeholder="Mot de passe"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <!-- Affectation Département & Rôle -->
                <div class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="create-tu-department" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-indigo-900">
                                Département d'affectation
                            </label>
                            <select id="create-tu-department" name="department_id" onchange="onDepartmentChanged(this.value)"
                                    class="w-full rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm font-semibold text-slate-800 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                <option value="">-- Aucun / Sélection manuelle --</option>
                                @foreach($tenantDepartments ?? [] as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code ?: 'N/A' }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-indigo-600/80">
                                ⭐ Sélectionner un département pré-coche automatiquement ses modules et ses rôles métiers ci-dessous.
                            </p>
                        </div>
                        <div>
                            <label for="create-tu-role" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Rôle principal (Libellé)</label>
                            <input type="text" id="create-tu-role" name="role" list="create-tu-role-list" placeholder="Ex: Réceptionniste"
                                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            <datalist id="create-tu-role-list">
                                @foreach($tenantRoles as $r)<option value="{{ $r->slug }}">{{ $r->name }}</option>@endforeach
                            </datalist>
                        </div>
                    </div>
                </div>

                <!-- Accès par Module & Rôles -->
                <div class="border-t border-slate-200 pt-5">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h4 class="text-[11px] font-bold uppercase tracking-wide text-slate-700">Accès aux modules & Rôles assignés</h4>
                            <p class="text-[11px] text-slate-500">
                                Les rôles cochés donnent accès aux modules correspondants. Vous pouvez ajuster les niveaux (lecture / écriture).
                            </p>
                        </div>
                        <span id="create-tu-dept-badge" class="hidden rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                            Pré-rempli selon le département
                        </span>
                    </div>

                    @if($rolesParModule->isEmpty())
                        <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-xs text-slate-500">
                            Aucun rôle assignable n'a pu être lu dans cet établissement.
                        </p>
                    @else
                        <div class="space-y-4">
                            @foreach($rolesParModule as $module => $roles)
                                <div class="rounded-xl border border-slate-200 p-3 bg-slate-50/50" data-module-container="{{ $module }}">
                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-500 flex items-center justify-between">
                                        <span>Module : {{ ucfirst($module) }}</span>
                                        <span class="module-match-pill hidden text-[9px] text-indigo-600 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.2">Inclus dans le département</span>
                                    </p>
                                    <div class="space-y-2">
                                        @foreach($roles as $role)
                                            <div class="flex items-center justify-between gap-3 rounded-lg bg-white p-2 border border-slate-200">
                                                <label class="flex items-center gap-2.5 text-xs text-slate-800 cursor-pointer select-none">
                                                    <input type="checkbox" name="roles[]" value="{{ $role->slug }}"
                                                           data-slug="{{ $role->slug }}"
                                                           data-module="{{ $role->module }}"
                                                           class="create-tu-role-check rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                    <span class="font-semibold">{{ $role->name }}</span>
                                                    @if($role->description)
                                                        <span class="text-[11px] text-slate-400">— {{ $role->description }}</span>
                                                    @endif
                                                </label>
                                                <select name="levels[{{ $role->slug }}]"
                                                        data-slug="{{ $role->slug }}"
                                                        class="create-tu-role-level rounded border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700 outline-none focus:border-indigo-500">
                                                    <option value="write">Lecture / Écriture</option>
                                                    <option value="read">Lecture seule</option>
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" onclick="window.closeTenantUserCreate()"
                        class="px-4 py-2 text-xs font-semibold text-slate-600 transition hover:text-slate-900">
                    Annuler
                </button>
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-5 py-2 text-xs font-bold text-white transition hover:bg-indigo-700 shadow-sm">
                    Créer l'employé
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('tenant-user-create-modal');
        const deptCatalog = @json($deptCatalogMap);

        window.openTenantUserCreate = function () {
            document.getElementById('tenant-user-create-form').reset();
            document.querySelectorAll('.create-tu-role-check').forEach(c => c.checked = false);
            document.querySelectorAll('.create-tu-role-level').forEach(s => s.value = 'write');
            document.querySelectorAll('.module-match-pill').forEach(p => p.classList.add('hidden'));
            document.getElementById('create-tu-dept-badge').classList.add('hidden');
            generateCreatePassword();
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('create-tu-name').focus();
        };

        window.closeTenantUserCreate = function () {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        window.generateCreatePassword = function () {
            const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$';
            let pwd = '';
            for (let i = 0; i < 10; i++) {
                pwd += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('create-tu-password').value = pwd;
        };

        window.onDepartmentChanged = function (deptId) {
            const dept = deptCatalog[deptId];
            const badge = document.getElementById('create-tu-dept-badge');
            
            if (!dept) {
                if (badge) badge.classList.add('hidden');
                document.querySelectorAll('.module-match-pill').forEach(p => p.classList.add('hidden'));
                document.querySelectorAll('.create-tu-role-check').forEach(chk => chk.checked = false);
                return;
            }

            if (badge) {
                badge.textContent = `Pré-rempli selon : ${dept.name}`;
                badge.classList.remove('hidden');
            }

            const deptCode = (dept.code || '').toUpperCase();
            const isDirection = deptCode === 'DIR' || (dept.name || '').toLowerCase().includes('direction');
            const deptModuleKeys = Object.keys(dept.modules || {});

            const moduleAliases = {
                'boutique': ['boutique', 'shop'],
                'shop': ['shop', 'boutique'],
                'comptabilite': ['comptabilite', 'accounting', 'ledger'],
                'accounting': ['accounting', 'comptabilite', 'ledger'],
                'ledger': ['ledger', 'comptabilite', 'accounting'],
                'hebergement': ['hebergement', 'reservations', 'clients'],
                'reservations': ['reservations', 'hebergement', 'clients'],
                'clients': ['clients', 'hebergement', 'reservations'],
                'rh': ['rh', 'utilisateurs'],
                'utilisateurs': ['utilisateurs', 'rh'],
                'it': ['it', 'parametres', 'api', 'pwa'],
                'parametres': ['parametres', 'it'],
                'qualite': ['qualite', 'grc'],
                'grc': ['grc', 'qualite']
            };

            const canonicalDeptModules = {
                'REC': ['hebergement'],
                'HSK': ['housekeeping'],
                'FNB': ['restaurant'],
                'BTQ': ['boutique', 'shop'],
                'FIN': ['comptabilite'],
                'RH':  ['rh', 'utilisateurs'],
                'IT':  ['it', 'parametres'],
                'QLT': ['qualite', 'grc']
            };

            // Met à jour les conteneurs de modules
            document.querySelectorAll('[data-module-container]').forEach(container => {
                const mod = container.dataset.moduleContainer;
                const pill = container.querySelector('.module-match-pill');
                const aliases = moduleAliases[mod] || [mod];
                const isMatch = isDirection || deptModuleKeys.includes(mod) || aliases.some(a => deptModuleKeys.includes(a)) || (canonicalDeptModules[deptCode] && canonicalDeptModules[deptCode].includes(mod));
                if (pill) {
                    if (isMatch) {
                        pill.classList.remove('hidden');
                    } else {
                        pill.classList.add('hidden');
                    }
                }
            });

            // Met à jour les checkboxes de rôles et leurs niveaux
            document.querySelectorAll('.create-tu-role-check').forEach(chk => {
                const roleModule = chk.dataset.module;
                const aliases = moduleAliases[roleModule] || [roleModule];
                const isMatch = isDirection || (canonicalDeptModules[deptCode] && canonicalDeptModules[deptCode].includes(roleModule)) || aliases.some(a => deptModuleKeys.includes(a));
                chk.checked = isMatch;
                if (isMatch) {
                    let defaultLevel = 'write';
                    for (const a of aliases) {
                        if (dept.modules && dept.modules[a]) {
                            defaultLevel = dept.modules[a];
                            break;
                        }
                    }
                    const levelSel = document.querySelector(`.create-tu-role-level[data-slug="${chk.dataset.slug}"]`);
                    if (levelSel) {
                        levelSel.value = defaultLevel;
                    }
                }
            });

            // Suggère le rôle principal s'il est vide
            const roleInput = document.getElementById('create-tu-role');
            if (roleInput && (!roleInput.value || roleInput.value.trim() === '')) {
                roleInput.value = dept.name;
            }
        };

        modal.addEventListener('click', (e) => { if (e.target === modal) window.closeTenantUserCreate(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) window.closeTenantUserCreate();
        });
    })();
</script>
