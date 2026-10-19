<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Creative Media Hub') }}</title>

        <!-- Brand Favicon & Icons -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="alternate icon" href="/favicon.ico">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <meta name="theme-color" content="#07090E">


        <!-- Zero-Flash Cinema Accent Initialization -->
        <script>
            (function() {
                try {
                    var accent = localStorage.getItem('cmh_accent_color') || 'cyan';
                    document.documentElement.setAttribute('data-accent', accent);
                } catch (e) {}
            })();
        </script>

        <!-- Scripts & Stylesheets -->
        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-[#07090E] text-slate-100 selection:bg-cyan-500 selection:text-white min-h-screen overflow-x-hidden">
        @inertia
    </body>
</html>
