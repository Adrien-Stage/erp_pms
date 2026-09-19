<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <x-favicon />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Wetchah ERP</title>

        <!-- ✅ AJOUTER CECI : Chargement de Tailwind et de tes configs -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Tes polices Google Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    </head>
    <body class="antialiased">
        <main class="flex min-h-screen items-center justify-center bg-slate-50 px-6">
            <div class="text-center">
                <x-brand class="mx-auto h-24" />
                <p class="mt-6 text-sm text-slate-500">Console d'orchestration des établissements.</p>
                <a href="{{ route('login') }}"
                   class="mt-6 inline-flex rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700">
                    Accéder à l'administration
                </a>
            </div>
        </main>
    </body>
</html>