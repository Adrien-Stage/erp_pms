{{--
    Modale de modification d'un propriétaire, partagée par toutes les lignes.

    Une seule modale alimentée par les data-* de la ligne cliquée, plutôt
    qu'une modale par propriétaire : sur un registre de plusieurs dizaines de
    comptes, le second choix alourdirait la page pour rien.
--}}
<div id="owner-edit-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl">
        <form method="POST" id="owner-edit-form">
            @csrf

            <div class="border-b border-slate-200 px-6 py-4">
                <h3 class="text-base font-bold text-slate-900">Modifier le propriétaire</h3>
                <p class="mt-0.5 text-xs text-slate-500" id="owner-edit-subtitle"></p>
            </div>

            <div class="space-y-4 px-6 py-5">
                <div>
                    <label for="owner-edit-name" class="mb-1 block text-xs font-semibold text-slate-600">Nom complet</label>
                    <input type="text" id="owner-edit-name" name="name" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="owner-edit-email" class="mb-1 block text-xs font-semibold text-slate-600">Email</label>
                        <input type="email" id="owner-edit-email" name="email" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="owner-edit-phone" class="mb-1 block text-xs font-semibold text-slate-600">Téléphone</label>
                        <input type="text" id="owner-edit-phone" name="phone"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="owner-edit-company" class="mb-1 block text-xs font-semibold text-slate-600">Société</label>
                        <input type="text" id="owner-edit-company" name="company_name"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <label for="owner-edit-nationality" class="mb-1 block text-xs font-semibold text-slate-600">Nationalité</label>
                        <input type="text" id="owner-edit-nationality" name="nationality"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                </div>

                <div>
                    <label for="owner-edit-password" class="mb-1 block text-xs font-semibold text-slate-600">
                        Nouveau mot de passe <span class="font-normal text-slate-400">(laisser vide pour ne pas changer)</span>
                    </label>
                    <input type="password" id="owner-edit-password" name="password" autocomplete="new-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-6 py-4">
                <button type="button" onclick="window.closeOwnerEdit()"
                        class="px-4 py-2 text-sm font-medium text-slate-600 transition hover:text-slate-900">
                    Annuler
                </button>
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('owner-edit-modal');
        const form  = document.getElementById('owner-edit-form');

        window.openOwnerEdit = function (bouton) {
            const d = bouton.dataset;
            form.action = d.action;
            document.getElementById('owner-edit-subtitle').textContent = d.email || '';
            document.getElementById('owner-edit-name').value = d.name || '';
            document.getElementById('owner-edit-email').value = d.email || '';
            document.getElementById('owner-edit-phone').value = d.phone || '';
            document.getElementById('owner-edit-company').value = d.company || '';
            document.getElementById('owner-edit-nationality').value = d.nationality || '';
            // Jamais prérempli : on ne réaffiche pas un mot de passe, et un
            // champ vide signifie « ne pas y toucher ».
            document.getElementById('owner-edit-password').value = '';

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('owner-edit-name').focus();
        };

        window.closeOwnerEdit = function () {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        };

        modal.addEventListener('click', (e) => { if (e.target === modal) window.closeOwnerEdit(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) window.closeOwnerEdit();
        });
    })();
</script>
