<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'FreelanceHub') }}</title>

        <!-- Dynamic Favicon -->
        @php
            $currentBusiness = \App\Models\Business::find(auth()->user()->current_business_id);
        @endphp
        @if($currentBusiness && $currentBusiness->favicon_path)
            <link rel="icon" type="image/png" href="{{ asset('storage/' . $currentBusiness->favicon_path) }}">
        @endif

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

        <!-- Flatpickr -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <style>
            [x-cloak] { display: none !important; }
            body {
                font-family: 'Plus Jakarta Sans', sans-serif;
            }
            #notif-panel {
                transition: transform 0.28s cubic-bezier(.4,0,.2,1), opacity 0.28s ease;
                transform: translateX(100%);
                opacity: 0;
            }
            #notif-panel.is-open {
                transform: translateX(0);
                opacity: 1;
            }
            #notif-backdrop {
                transition: opacity 0.28s ease;
                opacity: 0;
            }
            #notif-backdrop.is-open {
                opacity: 1;
            }
            .scrollbar-hide::-webkit-scrollbar {
                display: none;
            }
            .scrollbar-hide {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
            /* Global Admin Panel Slim Scrollbar */
            ::-webkit-scrollbar {
                width: 7px;
                height: 7px;
            }
            ::-webkit-scrollbar-track {
                background: transparent;
                border-radius: 9999px;
            }
            ::-webkit-scrollbar-thumb {
                background-color: #cbd5e1;
                border-radius: 9999px;
                transition: background-color 0.2s ease;
            }
            ::-webkit-scrollbar-thumb:hover {
                background-color: #94a3b8;
            }
            * {
                scrollbar-width: thin;
                scrollbar-color: #cbd5e1 transparent;
            }
        </style>
    </head>
    <body class="h-full bg-white text-gray-900 antialiased overflow-hidden" x-data="{ sidebarOpen: false, notifOpen: false }">
        <div class="flex h-screen overflow-hidden bg-white">
            
            <!-- Mobile Sidebar Backdrop -->
            <div x-show="sidebarOpen" 
                 x-transition:enter="transition-opacity ease-linear duration-300"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition-opacity ease-linear duration-300"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-40 bg-gray-900/50 backdrop-blur-sm lg:hidden" 
                 @click="sidebarOpen = false"></div>

            <!-- Sidebar -->
            <livewire:layout.navigation />

            <!-- Main Content Area -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Topbar -->
                <header class="flex items-center justify-between h-16 px-4 sm:px-6 bg-white border-b border-gray-100 flex-shrink-0 sticky top-0 z-30">
                    <!-- Left Section: Mobile menu toggle & Title -->
                    <div class="flex items-center space-x-4">
                        <button @click="sidebarOpen = true" class="p-2 -ml-2 rounded-md hover:bg-gray-50 lg:hidden text-gray-500 focus:outline-none">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        
                        @if(isset($header))
                            <div class="text-xl font-medium text-gray-900 flex items-center gap-3">
                                {{ $header }}
                            </div>
                        @endif
                    </div>

                    <!-- Search Bar in Top Bar -->
                    <div class="hidden sm:flex flex-1 justify-center px-6">
                        <livewire:global-search />
                    </div>

                    <!-- Right Section: Icons & Profile -->
                    <div class="flex items-center space-x-2 sm:space-x-3">
                        <a href="{{ route('settings.index') }}" class="inline-block p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors focus:outline-none focus:ring-2 focus:ring-gray-200" title="Settings">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </a>
                        
                        <!-- Profile Dropdown (Volt Component) -->
                        <div class="border-l border-gray-200 pl-3 sm:pl-4 ml-1 sm:ml-2">
                            <livewire:layout.topbar-profile />
                        </div>
                    </div>
                </header>

                <!-- Scrollable Viewport -->
                @php
                    $isFullWidth = $attributes->get('fullWidth', false);
                @endphp
                <main class="flex-grow overflow-y-auto bg-[#FAFAF8] {{ $isFullWidth ? '' : 'p-4 sm:p-6 lg:p-8' }}">
                    <div class="{{ $isFullWidth ? 'h-full w-full flex flex-col' : 'max-w-7xl mx-auto' }}">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>

        <!-- Global Toast Component -->
        <x-global-toast />

        <!-- ── Notifications Drawer ──────────────────────────── -->
        <!-- Backdrop -->
        <div id="notif-backdrop"
             onclick="closeNotif()"
             class="fixed inset-0 z-[60] bg-gray-900/40 backdrop-blur-sm"
             style="display:none;"></div>

        <!-- Panel -->
        <div id="notif-panel"
             class="fixed top-0 right-0 h-full w-80 bg-white shadow-2xl z-[70] flex flex-col"
             style="display:none;">

            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <h2 class="text-base font-bold text-gray-900">Notifications</h2>
                </div>
                <button onclick="closeNotif()" class="p-1 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Notifications List -->
            <div class="flex-1 overflow-y-auto">
                @php
                    $notifications = auth()->user()->notifications()->latest()->take(20)->get();
                @endphp

                @if($notifications->isEmpty())
                <div class="flex flex-col items-center justify-center h-full text-center px-6">
                    <svg class="w-12 h-12 text-gray-200 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <p class="text-sm font-semibold text-gray-400">All caught up!</p>
                    <p class="text-xs text-gray-400 mt-1">No new notifications right now.</p>
                </div>
                @else
                <ul class="divide-y divide-gray-100">
                    @foreach($notifications as $notif)
                    @php
                        $data  = $notif->data;
                        $title = $data['title'] ?? $data['message'] ?? 'Notification';
                        $body  = $data['body']  ?? $data['description'] ?? '';
                    @endphp
                    <li class="flex gap-3 px-5 py-4 hover:bg-gray-50 transition-colors {{ is_null($notif->read_at) ? 'bg-orange-50/40' : '' }}">
                        <div class="flex-shrink-0 mt-0.5">
                            <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900 leading-snug">{{ $title }}</p>
                            @if($body)
                            <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ $body }}</p>
                            @endif
                            <p class="text-[11px] text-gray-400 mt-1">{{ $notif->created_at->diffForHumans() }}</p>
                        </div>
                        @if(is_null($notif->read_at))
                        <div class="flex-shrink-0 mt-2">
                            <div class="w-2 h-2 bg-orange-500 rounded-full"></div>
                        </div>
                        @endif
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>

            <!-- Footer -->
            @if(!$notifications->isEmpty())
            <div class="px-5 py-3 border-t border-gray-100">
                <button class="text-xs font-semibold text-orange-600 hover:text-orange-700 transition-colors">
                    Mark all as read
                </button>
            </div>
            @endif
        </div>
        <script>
            function toggleNotif() {
                var panel    = document.getElementById('notif-panel');
                var backdrop = document.getElementById('notif-backdrop');
                if (panel.classList.contains('is-open')) {
                    panel.classList.remove('is-open');
                    backdrop.classList.remove('is-open');
                    setTimeout(function(){ 
                        panel.style.display = 'none'; 
                        backdrop.style.display = 'none';
                    }, 300);
                } else {
                    panel.style.display = 'flex';
                    backdrop.style.display = 'block';
                    setTimeout(function(){ 
                        panel.classList.add('is-open');
                        backdrop.classList.add('is-open');
                    }, 10);
                }
            }
            function closeNotif() {
                var panel    = document.getElementById('notif-panel');
                var backdrop = document.getElementById('notif-backdrop');
                panel.classList.remove('is-open');
                backdrop.classList.remove('is-open');
                setTimeout(function(){ 
                    panel.style.display = 'none'; 
                    backdrop.style.display = 'none';
                }, 300);
            }
        </script>
    </body>
</html>
