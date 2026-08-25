{{--
    Connexion de l'éditeur de contenu.

    Volontairement neutre : ni le mot « administration », ni « ERP », ni le nom
    du produit. L'éditeur n'a pas à savoir qu'il partage l'application avec une
    console technique.

    À noter : cette discrétion réduit la découverte fortuite, elle ne protège
    rien par elle-même. Ce qui protège, c'est le refus de connexion de tout
    compte qui n'est pas éditeur, et le cloisonnement à son seul établissement.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Espace éditeur</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-stone-100 via-white to-stone-100 text-slate-800 antialiased font-body">

    <div class="flex min-h-screen items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm">

            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-stone-800 shadow-lg">
                    <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                    </svg>
                </div>
                <h1 class="font-display text-2xl font-semibold text-stone-900">Espace éditeur</h1>
                <p class="mt-1.5 text-sm text-stone-500">Gestion du contenu de votre site</p>
            </div>

            <div class="rounded-2xl border border-stone-200 bg-white p-7 shadow-sm">

                @if($errors->any())
                    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('site-editor.login.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="mb-1.5 block text-xs font-semibold text-stone-600">Identifiant</label>
                        <input type="text" id="email" name="email" value="{{ old('email') }}" required autofocus
                               autocomplete="username"
                               class="w-full rounded-lg border border-stone-300 bg-stone-50/60 px-3.5 py-2.5 text-sm outline-none transition focus:border-stone-500 focus:bg-white focus:ring-1 focus:ring-stone-400">
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-xs font-semibold text-stone-600">Mot de passe</label>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                               class="w-full rounded-lg border border-stone-300 bg-stone-50/60 px-3.5 py-2.5 text-sm outline-none transition focus:border-stone-500 focus:bg-white focus:ring-1 focus:ring-stone-400">
                    </div>

                    <label class="flex items-center gap-2 pt-1 text-xs text-stone-500">
                        <input type="checkbox" name="remember" value="1"
                               class="h-3.5 w-3.5 rounded border-stone-300 text-stone-700 focus:ring-stone-400">
                        Rester connecté
                    </label>

                    <button type="submit"
                            class="mt-2 w-full rounded-lg bg-stone-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-stone-900">
                        Se connecter
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-[11px] text-stone-400">
                Besoin d'aide ? Contactez votre gestionnaire.
            </p>
        </div>
    </div>

</body>
</html>
