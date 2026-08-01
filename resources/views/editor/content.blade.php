{{--
    Éditeur de contenu, vue de l'éditeur rattaché à un établissement.

    Réutilise le formulaire de l'ERP (partials/site-content-form) : les champs
    sont donc strictement les mêmes des deux côtés. Seule l'enveloppe change —
    pas de barre d'onglets d'administration, pas de navigation vers d'autres
    établissements, rien qui laisse deviner une console technique.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Contenu du site — {{ $tenant->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-slate-800 antialiased font-body">

    <header class="sticky top-0 z-30 w-full border-b border-stone-200 bg-white shadow-sm">
        <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-5 lg:px-8">
            <div class="flex items-center gap-3">
                @if(!empty($tenant->settings['logo']))
                    <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="" class="h-8 w-8 rounded object-contain">
                @else
                    <span class="flex h-8 w-8 items-center justify-center rounded bg-stone-800">
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                        </svg>
                    </span>
                @endif
                <div>
                    <p class="text-sm font-bold leading-none text-stone-900">{{ $tenant->name }}</p>
                    <p class="text-[11px] text-stone-500">Contenu du site</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden text-xs text-stone-500 sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('site-editor.logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-stone-300 px-3 py-1.5 text-xs font-semibold text-stone-600 transition hover:bg-stone-100">
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="mx-auto w-full max-w-6xl px-5 py-8 lg:px-8">

        @if(session('success'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif
        @if($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc space-y-0.5">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @include('admin.tenants.partials.site-content-form', [
            'tenant'    => $tenant,
            'actionUrl' => route('site-editor.content.update'),
        ])
    </main>

</body>
</html>
