{{--
    Actions sur un employé de l'établissement.

    Attend : $tenant, $user (objet issu de la base du tenant)

    Ces actions écrivent dans la base de l'établissement : l'effet est
    immédiat côté wetchah_app, il n'y a pas de copie à resynchroniser.
--}}
@php
    // Rôles cochés et niveaux actuels, transmis à la modale partagée.
    $rolesActuels = collect($user->roles ?? [])->pluck('slug')->all();
    $niveauxActuels = collect($user->roles ?? [])->mapWithKeys(fn ($r) => [$r['slug'] => $r['level']])->all();
@endphp
<div x-data="{ open: false }" @keydown.escape.window="open = false" class="relative inline-block text-left">
    <button type="button" @click="open = !open" @click.outside="open = false"
            class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
            :aria-expanded="open" aria-haspopup="true"
            aria-label="Actions pour {{ $user->name }}">
        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 6a1.75 1.75 0 110-3.5A1.75 1.75 0 0110 6zM10 11.75a1.75 1.75 0 110-3.5 1.75 1.75 0 010 3.5zM10 17.5a1.75 1.75 0 110-3.5 1.75 1.75 0 010 3.5z" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute right-0 z-20 mt-1 w-60 origin-top-right overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
         role="menu">

        <a href="{{ route('tech.establishments.users.show', ['tenant' => $tenant, 'user' => $user->id]) }}"
           class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-xs font-semibold text-slate-800 transition hover:bg-slate-50" role="menuitem">
            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Voir la fiche
        </a>

        <div class="border-t border-slate-100"></div>

        <button type="button" @click="open = false; window.openTenantUserEdit(this)"
                data-id="{{ $user->id }}"
                data-name="{{ $user->name }}"
                data-email="{{ $user->email }}"
                data-phone="{{ $user->phone }}"
                data-role="{{ $user->role }}"
                data-roles="{{ json_encode($rolesActuels) }}"
                data-levels="{{ json_encode($niveauxActuels) }}"
                data-action="{{ route('tech.establishments.users.update', ['tenant' => $tenant, 'user' => $user->id]) }}"
                class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-xs text-slate-700 transition hover:bg-slate-50" role="menuitem">
            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
            </svg>
            Modifier et gérer les accès
        </button>

        <form method="POST" action="{{ route('tech.establishments.users.toggle-active', ['tenant' => $tenant, 'user' => $user->id]) }}"
              @if($user->is_active) onsubmit="return confirm('Désactiver {{ addslashes($user->name) }} ? Cette personne ne pourra plus se connecter à {{ addslashes($tenant->name) }}.');" @endif>
            @csrf
            <button type="submit"
                    class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-xs transition hover:bg-slate-50 {{ $user->is_active ? 'text-amber-700' : 'text-emerald-700' }}"
                    role="menuitem">
                @if($user->is_active)
                    <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    Désactiver
                @else
                    <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Activer
                @endif
            </button>
        </form>

        <div class="border-t border-slate-100"></div>

        <form method="POST" action="{{ route('tech.establishments.users.destroy', ['tenant' => $tenant, 'user' => $user->id]) }}"
              onsubmit="return confirm('Supprimer définitivement le compte de {{ addslashes($user->name) }} dans {{ addslashes($tenant->name) }} ? Cette action est irréversible.');">
            @csrf
            @method('DELETE')
            <button type="submit"
                    class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-xs text-red-700 transition hover:bg-red-50"
                    role="menuitem">
                <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
                Supprimer
            </button>
        </form>
    </div>
</div>
