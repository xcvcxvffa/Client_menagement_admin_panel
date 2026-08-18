<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-50 dark:bg-gray-900">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'FreelanceHub') }} - Client Portal</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased h-full text-gray-900 dark:text-gray-100 selection:bg-indigo-500/30">
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col">
        
        <!-- Portal Navigation -->
        <nav class="bg-white dark:bg-gray-800 border-b border-gray-150 dark:border-gray-750 z-30 sticky top-0 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <!-- Brand -->
                        <div class="flex-shrink-0 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg shadow-md">
                                FH
                            </div>
                            <span class="font-black text-xl tracking-tight hidden sm:block">Client Portal</span>
                        </div>
                        
                        <!-- Links -->
                        <div class="hidden sm:-my-px sm:ml-10 sm:flex sm:space-x-8">
                            <a href="{{ route('portal.dashboard') }}" wire:navigate class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('portal.dashboard') ? 'border-indigo-500 text-gray-900 dark:text-white font-bold' : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-700 dark:hover:text-gray-300 font-medium' }} transition-colors">
                                Dashboard
                            </a>
                            <a href="{{ route('portal.quotes') }}" wire:navigate class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('portal.quotes') ? 'border-indigo-500 text-gray-900 dark:text-white font-bold' : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-700 dark:hover:text-gray-300 font-medium' }} transition-colors">
                                Quotes
                            </a>
                            <a href="{{ route('portal.invoices') }}" wire:navigate class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('portal.invoices') ? 'border-indigo-500 text-gray-900 dark:text-white font-bold' : 'border-transparent text-gray-500 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600 hover:text-gray-700 dark:hover:text-gray-300 font-medium' }} transition-colors">
                                Invoices
                            </a>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 hidden md:block">
                            Welcome, {{ Auth::guard('client')->user()->name }}
                        </div>
                        <form method="POST" action="{{ route('portal.logout') }}">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-sm font-medium text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-lg transition-colors">
                                Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Header -->
        @if (isset($header))
            <header class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-150 dark:border-gray-750">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">{{ $header }}</h1>
                </div>
            </header>
        @endif

        <!-- Page Content -->
        <main class="flex-1">
            <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>
        
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-150 dark:border-gray-750 py-6 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">Powered by FreelanceHub Inc.</p>
        </footer>
    </div>
</body>
</html>
