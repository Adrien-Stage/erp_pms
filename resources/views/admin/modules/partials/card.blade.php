{{--
    Carte d'un module du répertoire.

    Attend : $module (entrée de ModuleCatalog enrichie de « slug »),
             $count (établissements équipés), $total (établissements).

    Toute la carte est cliquable via le lien étiré (span absolute inset-0) :
    le menu contextuel passe au-dessus grâce à son z-index, sans quoi ses
    clics seraient captés par le lien.
--}}
@php
    // Classes écrites en toutes lettres : Tailwind ne scanne que resources/,
    // et ne détecte pas une classe recomposée à l'exécution.
    $accents = [
        'indigo'  => 'bg-indigo-50 text-indigo-600',
        'sky'     => 'bg-sky-50 text-sky-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'amber'   => 'bg-amber-50 text-amber-600',
        'violet'  => 'bg-violet-50 text-violet-600',
        'blue'    => 'bg-blue-50 text-blue-600',
        'rose'    => 'bg-rose-50 text-rose-600',
        'cyan'    => 'bg-cyan-50 text-cyan-600',
        'fuchsia' => 'bg-fuchsia-50 text-fuchsia-600',
        'slate'   => 'bg-slate-100 text-slate-600',
    ];
    $typeBadges = [
        'core'      => ['label' => 'Cœur',   'classes' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
        'optionnel' => ['label' => 'Optionnel', 'classes' => 'bg-amber-50 text-amber-700 border-amber-200'],
        'derive'    => ['label' => 'Dérivé', 'classes' => 'bg-slate-50 text-slate-600 border-slate-200'],
        'config'    => ['label' => 'Sur configuration', 'classes' => 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200'],
    ];
    $accent = $accents[$module['accent']] ?? $accents['slate'];
    $badge  = $typeBadges[$module['type']] ?? $typeBadges['core'];
    $url    = route('tech.modules.show', $module['slug']);
@endphp

<div class="relative flex flex-col rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300 hover:shadow-md">

    <div class="flex items-start justify-between gap-3">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg {{ $accent }}">
            <i data-lucide="{{ $module['icon'] }}" class="h-5 w-5"></i>
        </div>

        {{-- Menu contextuel (trois points) --}}
        <div x-data="{ open: false, copied: false }" @keydown.escape.window="open = false"
             class="relative z-20 -mr-1.5 -mt-1.5 inline-block text-left">
            <button type="button" @click="open = !open" @click.outside="open = false"
                    class="rounded-md p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                    :aria-expanded="open" aria-haspopup="true"
                    aria-label="Actions pour le module {{ $module['label'] }}">
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10 6a1.75 1.75 0 110-3.5A1.75 1.75 0 0110 6zM10 11.75a1.75 1.75 0 110-3.5 1.75 1.75 0 010 3.5zM10 17.5a1.75 1.75 0 110-3.5 1.75 1.75 0 010 3.5z" />
                </svg>
            </button>

            <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                 class="absolute right-0 z-30 mt-1 w-60 origin-top-right overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                 role="menu">

                <a href="{{ $url }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50" role="menuitem">
                    <i data-lucide="eye" class="h-4 w-4 text-slate-400"></i>
                    Ouvrir la fiche
                </a>

                <a href="{{ $url }}#guide" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50" role="menuitem">
                    <i data-lucide="book-open" class="h-4 w-4 text-slate-400"></i>
                    Guide d'utilisation
                </a>

                <a href="{{ $url }}#ecrans" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50" role="menuitem">
                    <i data-lucide="layout-list" class="h-4 w-4 text-slate-400"></i>
                    Écrans du module
                </a>

                <div class="border-t border-slate-100"></div>

                @if($module['type'] !== 'config')
                    <a href="{{ $url }}#etablissements" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50" role="menuitem">
                        <i data-lucide="building-2" class="h-4 w-4 text-slate-400"></i>
                        Établissements équipés ({{ $count }})
                    </a>
                @endif

                <a href="{{ route('tech.dashboard', ['tab' => 'roles']) }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 transition hover:bg-slate-50" role="menuitem">
                    <i data-lucide="shield-check" class="h-4 w-4 text-slate-400"></i>
                    Rôles et permissions
                </a>

                @if($module['key'])
                    <div class="border-t border-slate-100"></div>

                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $module['key'] }}'); copied = true; setTimeout(() => { copied = false; open = false }, 900)"
                            class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm text-slate-700 transition hover:bg-slate-50"
                            role="menuitem">
                        <i data-lucide="clipboard-copy" class="h-4 w-4 text-slate-400"></i>
                        <span x-show="!copied">Copier la clé « {{ $module['key'] }} »</span>
                        <span x-show="copied" x-cloak class="font-semibold text-emerald-700">Clé copiée</span>
                    </button>
                @endif
            </div>
        </div>
    </div>

    <a href="{{ $url }}" class="mt-4 block focus:outline-none">
        {{-- Lien étiré : rend toute la carte cliquable --}}
        <span class="absolute inset-0" aria-hidden="true"></span>
        <p class="text-sm font-bold text-slate-800">{{ $module['label'] }}</p>
        <p class="mt-0.5 font-mono text-[10px] text-slate-400">{{ $module['key'] ?? $module['slug'] }}</p>
        <p class="mt-2 text-[11px] leading-relaxed text-slate-500">{{ $module['tagline'] }}</p>
    </a>

    <div class="mt-4 flex items-center justify-between gap-2 border-t border-slate-100 pt-3">
        <span class="rounded border px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider {{ $badge['classes'] }}">
            {{ $badge['label'] }}
        </span>
        @if($module['type'] === 'core')
            <span class="text-[10px] font-semibold text-slate-400">Toujours actif</span>
        @elseif($module['key'])
            <span class="text-[10px] font-semibold text-slate-500">{{ $count }}/{{ $total }} établissement{{ $count > 1 ? 's' : '' }}</span>
        @else
            <span class="text-[10px] font-semibold text-slate-400">Selon configuration</span>
        @endif
    </div>
</div>
