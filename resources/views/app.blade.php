<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Creative Media Streaming Library') }}</title>

        <!-- Inline script to set theme before paint to prevent theme flash -->
        <script>
            (function() {
                const saved = localStorage.getItem('appearance');
                if (saved === 'light') {
                    document.documentElement.classList.remove('dark');
                } else if (saved === 'dark') {
                    document.documentElement.classList.add('dark');
                } else {
                    if (window.matchMedia('(prefers-color-scheme: light)').matches) {
                        document.documentElement.classList.remove('dark');
                    } else {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        <!-- Google Fonts: Outfit & Plus Jakarta Sans for EN, Cairo & Tajawal for AR -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts & Stylesheets -->
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased selection:bg-cyan-500 selection:text-white min-h-screen overflow-x-hidden">
        @inertia
    </body>
</html>
