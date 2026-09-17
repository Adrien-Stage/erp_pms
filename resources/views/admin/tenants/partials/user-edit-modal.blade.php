{{--
    Modification d'un employé et de ses accès.

    Attend : $tenant, $tenantRoles (rôles assignables lus dans la base du tenant)

    Les rôles proposés viennent de l'établissement lui-même, pas d'une liste
    codée en dur ici : chaque établissement a son propre référentiel, et un
    module désactivé n'y figure pas.
--}}
@php
    // Groupés par module, comme dans wetchah_app, pour que le choix se lise
    // par domaine plutôt qu'en une liste plate.
    $rolesParModule = collect($tenantRoles)->groupBy(fn ($r) => $r->module ?: 'autre');
@endphp

<div id="tenant-user-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
    <div class="max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl flex flex-col">
        <form method="POST" id="tenant-user-form" class="flex min-h-0 flex-col">
            @csrf

            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-base font-extrabold text-slate-800">Modifier l'employé</h3>
                <p class="mt-0.5 text-xs text-slate-500">
                    Enregistré directement dans la base de {{ $tenant->name }} : l'employé voit le changement
                    à sa prochaine connexion.
                </p>
            </div>

            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5">

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="tu-name" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Nom</label>
                        <input type="text" id="tu-name" name="name" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="tu-email" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Email</label>
                        <input type="email" id="tu-email" name="email" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="tu-phone" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Téléphone</label>
                        <input type="text" id="tu-phone" name="phone"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="tu-password" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">
                            Mot de passe <span class="font-medium normal-case text-slate-400">(vide = inchangé)</span>
                        </label>
                        <input type="password" id="tu-password" name="password" autocomplete="new-password"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="tu-department" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Département</label>
                        <select id="tu-department" name="department_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 bg-white">
                            <option value="">-- Aucun département --</option>
                            @foreach($tenantDepartments ?? [] as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }} ({{ $dept->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="tu-role" class="mb-1 block text-[11px] font-bold uppercase tracking-wide text-slate-500">Rôle principal</label>
                        <input type="text" id="tu-role" name="role" list="tu-role-list"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <datalist id="tu-role-list">
                            @foreach($tenantRoles as $r)<option value="{{ $r->slug }}">{{ $r->name }}</option>@endforeach
                        </datalist>
                    </div>
                </div>
                <p class="mt-1 text-[11px] text-slate-400">
                    L'affectation au département détermine les accès métier standards. Le rôle historique reste lu en fallback.
                </p>

                <div class="border-t border-slate-200 pt-5">
                    <h4 class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Accès par module</h4>
                    <p class="mt-0.5 mb-3 text-[11px] text-slate-400">
                        Cochez les rôles, puis choisissez pour chacun s'il donne la lecture seule ou l'écriture.
                    </p>

                    @if($rolesParModule->isEmpty())
                        <p class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center text-xs text-slate-500">
                            Aucun rôle assignable n'a pu être lu dans cet établissement.<br>
                            <span class="text-[11px] text-slate-400">
                                Sa base n'est peut-être pas joignable, ou sa version est antérieure aux rôles par module.
                            </span>
                        </p>
                    @else
                        <div class="space-y-4">
                            @foreach($rolesParModule as $module => $roles)
                                <div>
                                    <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $module }}</p>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach($roles as $r)
                                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 p-2.5 transition hover:border-indigo-300 hover:bg-indigo-50/40">
                                                <input type="checkbox" name="roles[]" value="{{ $r->id }}"
                                                       data-slug="{{ $r->slug }}"
                                                       class="tu-role-check h-4 w-4 shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-xs font-semibold text-slate-800">{{ $r->name }}</span>
                                                    @if($r->description)
                                                        <span class="block truncate text-[10px] text-slate-400">{{ $r->description }}</span>
                                                    @endif
                                                </span>
                                                <select name="levels[{{ $r->id }}]" data-slug="{{ $r->slug }}"
                                                        class="tu-role-level shrink-0 rounded-md border border-slate-300 px-1.5 py-1 text-[10px] outline-none focus:border-indigo-500">
                                                    <option value="write">Lecture/écriture</option>
                                                    <option value="read">Lecture seule</option>
                                                </select>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" onclick="window.closeTenantUserEdit()"
                        class="px-4 py-2 text-xs font-semibold text-slate-600 transition hover:text-slate-900">
                    Annuler
                </button>
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-indigo-700">
                    Enregistrer les accès
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('tenant-user-modal');
        const form  = document.getElementById('tenant-user-form');

        window.openTenantUserEdit = function (bouton) {
            const d = bouton.dataset;
            form.action = d.action;

            document.getElementById('tu-name').value  = d.name  || '';
            document.getElementById('tu-email').value = d.email || '';
            document.getElementById('tu-phone').value = d.phone || '';
            document.getElementById('tu-role').value  = d.role  || '';
            const deptEl = document.getElementById('tu-department');
            if (deptEl) { deptEl.value = d.departmentId || ''; }
            document.getElementById('tu-password').value = '';

            // Rôles cochés et niveaux repositionnés à chaque ouverture : sans
            // cette remise à zéro, l'employé précédent laisserait ses cases.
            let actifs = [], niveaux = {};
            try { actifs  = JSON.parse(d.roles  || '[]'); } catch (e) {}
            try { niveaux = JSON.parse(d.levels || '{}'); } catch (e) {}

            document.querySelectorAll('.tu-role-check').forEach((c) => {
                c.checked = actifs.includes(c.dataset.slug);
            });
            document.querySelectorAll('.tu-role-level').forEach((s) => {
                s.value = niveaux[s.dataset.slug] || 'write';
            });

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('tu-name').focus();
        };

        window.closeTenantUserEdit = function () {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        modal.addEventListener('click', (e) => { if (e.target === modal) window.closeTenantUserEdit(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) window.closeTenantUserEdit();
        });
    })();
</script>
