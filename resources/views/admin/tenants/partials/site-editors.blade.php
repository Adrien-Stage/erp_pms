{{--
    Comptes autorisés à éditer le contenu de ce site.

    Attend : $tenant

    Ces comptes vivent dans la base de l'ERP, contrairement aux employés de
    l'établissement. Ils n'accèdent qu'à ce formulaire, sur une adresse
    distincte, et ne voient rien d'autre de la plateforme.
--}}
@php
    $editeurs = \App\Models\User::where('role', \App\Models\User::ROLE_SITE_EDITOR)
        ->where('tenant_id', $tenant->id)
        ->orderBy('name')
        ->get();
@endphp

<div class="mb-8 rounded-xl border border-slate-200 bg-white shadow-sm" x-data="{ creation: false }">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
        <div>
            <h3 class="text-sm font-extrabold tracking-tight text-slate-800">Qui peut éditer ce contenu</h3>
            <p class="mt-0.5 text-xs text-slate-500">
                Comptes limités à la rédaction du site de {{ $tenant->name }}. Ils se connectent sur
                <a href="{{ route('site-editor.login') }}" target="_blank" class="font-mono text-indigo-600 hover:underline">{{ route('site-editor.login') }}</a>,
                une adresse séparée de cette console.
            </p>
        </div>
        <button type="button" @click="creation = !creation"
                class="shrink-0 rounded-lg bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white transition hover:bg-indigo-700">
            Ajouter un éditeur
        </button>
    </div>

    <div x-show="creation" x-cloak class="border-b border-slate-200 bg-slate-50 px-5 py-4">
        <form method="POST" action="{{ route('tech.establishments.editors.store', $tenant) }}"
              class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            @csrf
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Nom</label>
                <input type="text" name="name" required
                       class="w-full rounded-lg border border-slate-300 px-2.5 py-2 text-xs outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Email</label>
                <input type="email" name="email" required
                       class="w-full rounded-lg border border-slate-300 px-2.5 py-2 text-xs outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="mb-1 block text-[10px] font-bold uppercase tracking-wide text-slate-500">Mot de passe</label>
                <input type="text" name="password" required minlength="6"
                       class="w-full rounded-lg border border-slate-300 px-2.5 py-2 text-xs outline-none focus:border-indigo-500">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white transition hover:bg-slate-700">
                    Créer le compte
                </button>
            </div>
        </form>
    </div>

    @if($editeurs->isEmpty())
        <p class="px-5 py-6 text-center text-xs text-slate-400">
            Aucun éditeur pour ce site. Le contenu n'est modifiable que depuis cette console.
        </p>
    @else
        <ul class="divide-y divide-slate-100">
            @foreach($editeurs as $editeur)
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                    <div class="min-w-0">
                        <p class="truncate text-xs font-semibold text-slate-800">{{ $editeur->name }}</p>
                        <p class="truncate font-mono text-[11px] text-slate-500">{{ $editeur->email }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold
                            {{ $editeur->is_active ? 'border-green-200 bg-green-50 text-green-700' : 'border-red-200 bg-red-50 text-red-700' }}">
                            {{ $editeur->is_active ? 'Actif' : 'Désactivé' }}
                        </span>
                        <form method="POST" action="{{ route('tech.establishments.editors.toggle', ['tenant' => $tenant, 'editor' => $editeur]) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-100">
                                {{ $editeur->is_active ? 'Désactiver' : 'Activer' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('tech.establishments.editors.destroy', ['tenant' => $tenant, 'editor' => $editeur]) }}"
                              onsubmit="return confirm('Supprimer le compte éditeur de {{ addslashes($editeur->name) }} ?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-md border border-red-200 px-2.5 py-1 text-[11px] font-semibold text-red-600 transition hover:bg-red-50">
                                Supprimer
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
