<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased relative min-h-screen bg-[#FFF8F0]">
        <!-- Soft gradient orbs for modern mesh background -->
        <div class="absolute top-0 -left-1/4 w-[150%] h-[150%] bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-orange-100/80 via-amber-100/40 to-yellow-50/30 blur-3xl rounded-full z-0 pointer-events-none transform -translate-y-1/4"></div>
        <div class="absolute bottom-0 -right-1/4 w-[150%] h-[150%] bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-orange-200/50 via-orange-50/40 to-transparent blur-3xl rounded-full z-0 pointer-events-none transform translate-y-1/4"></div>
        
        <!-- Perspective lines overlay -->
        <div class="absolute inset-0 z-0 opacity-20 pointer-events-none" style="background-image: linear-gradient(to right, rgba(0,0,0,0.05) 1px, transparent 1px), linear-gradient(to bottom, rgba(0,0,0,0.05) 1px, transparent 1px); background-size: 80px 80px; transform: perspective(1000px) rotateX(60deg) scale(2.5) translateY(-20%); transform-origin: top center;"></div>

        <div class="min-h-screen flex flex-col p-6 relative z-10 overflow-y-auto">
            <div class="m-auto w-full sm:max-w-md bg-white/50 backdrop-blur-2xl border border-white/60 shadow-2xl shadow-purple-500/10 rounded-3xl p-8 sm:p-10">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
