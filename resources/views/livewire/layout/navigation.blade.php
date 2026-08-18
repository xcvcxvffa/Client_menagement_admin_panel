<?php

use App\Livewire\Actions\Logout;

$logout = function (Logout $logout) {
    $logout();

    $this->redirect('/', navigate: true);
};

?>

<aside class="fixed inset-y-0 left-0 z-50 flex flex-col w-[260px] bg-white border-r border-gray-100 lg:static lg:inset-auto lg:translate-x-0 transform transition-transform duration-300 ease-in-out"
       :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
    
    <!-- Sidebar Header -->
    <div class="flex items-center justify-between h-16 px-6 mt-2 flex-shrink-0">
        <div class="flex items-center space-x-2.5">
            @php
                $currentBusiness = \App\Models\Business::find(auth()->user()->current_business_id);
            @endphp
            @if($currentBusiness && $currentBusiness->logo_path)
                <img src="{{ asset('storage/' . $currentBusiness->logo_path) }}" alt="{{ $currentBusiness->name }}" class="h-9 w-auto object-contain">
            @else
                <div class="bg-[#ea580c] text-white w-9 h-9 rounded-[10px] flex items-center justify-center font-bold text-xl leading-none">
                    {{ strtoupper(substr($currentBusiness->name ?? 'C', 0, 1)) }}
                </div>
                <span class="text-[20px] font-bold text-gray-900 tracking-tight">{{ $currentBusiness->name ?? 'Clienter' }}</span>
            @endif
        </div>
        
        <button onclick="toggleNotif()" class="relative text-gray-400 hover:text-orange-500 focus:outline-none transition-colors">
            <svg class="w-[22px] h-[22px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            {{-- Unread dot --}}
            @php $unreadCount = auth()->user()->unreadNotifications()->count(); @endphp
            @if($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-orange-500 rounded-full border-2 border-white"></span>
            @endif
        </button>
    </div>

    <!-- Sidebar Nav Links -->
    <nav class="flex-1 px-4 py-5 space-y-0.5 overflow-y-auto overflow-x-hidden">
        @can('view dashboard')
        <x-sidebar-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">Dashboard</x-sidebar-link>
        @endcan
        
        @can('view clients')
        <x-sidebar-link :href="route('clients.index')" :active="request()->routeIs('clients.*')" icon="users">Clients</x-sidebar-link>
        @endcan
        
        @can('view projects')
        <x-sidebar-link :href="route('projects.index')" :active="request()->routeIs('projects.*')" icon="folder">Projects</x-sidebar-link>
        @endcan
        
        
        <x-sidebar-link :href="route('files.index')" :active="request()->routeIs('files.*')" icon="document-text">File Management</x-sidebar-link>
        
        @can('view retainers')
        <x-sidebar-link :href="route('retainers.index')" :active="request()->routeIs('retainers.*')" icon="refresh">Retainers</x-sidebar-link>
        @endcan
        
        @can('view payments')
        <x-sidebar-link :href="route('payments.index')" :active="request()->routeIs('payments.*')" icon="trending-up">Payments</x-sidebar-link>
        @endcan
        

        @can('view messages')
        <x-sidebar-link :href="route('messages.index')" :active="request()->routeIs('messages.*')" icon="chat">Messages</x-sidebar-link>
        @endcan


        

        @can('view tasks')
        <x-sidebar-link :href="route('tasks.index')" :active="request()->routeIs('tasks.*')" icon="check-square">Tasks</x-sidebar-link>
        @endcan
        

        @can('view team')
        <x-sidebar-link :href="route('team.index')" :active="request()->routeIs('team.*')" icon="user-group">Team</x-sidebar-link>
        @endcan
        
        @can('view invoices')
        <x-sidebar-link :href="route('billing.index')" :active="request()->routeIs('billing.*')" icon="credit-card">Billing</x-sidebar-link>
        @endcan
        
        @can('view expenses')
        <x-sidebar-link :href="route('expenses.index')" :active="request()->routeIs('expenses.*')" icon="currency-dollar">Expenses</x-sidebar-link>
        @endcan
        
        @can('view roles')
        <x-sidebar-link :href="route('settings.roles')" :active="request()->routeIs('settings.roles')" icon="shield-check">Roles</x-sidebar-link>
        @endcan
        
        @can('view permissions')
        <x-sidebar-link :href="route('settings.permissions')" :active="request()->routeIs('settings.permissions')" icon="key">Permissions</x-sidebar-link>
        @endcan
    </nav>

    <!-- Sidebar Footer -->
    <div class="px-4 py-5 mt-auto flex-shrink-0 border-t border-gray-100 space-y-4">
        <!-- Business Card -->
        <div class="bg-[#f4f4f5] rounded-[14px] p-3 flex items-center justify-between overflow-hidden">
            <div class="flex items-center space-x-3 min-w-0 flex-1">
                @php
                    $currentBusiness = \App\Models\Business::find(auth()->user()->current_business_id);
                    $businessName = $currentBusiness->name ?? 'Twixel Media pvt ltd';
                    $firstLetter = strtoupper(substr($businessName, 0, 1));
                    $userRole = auth()->user()->roles->first()?->name ?? 'Free';
                @endphp
                @if(auth()->user()->avatar_path)
                    <img src="{{ asset('storage/' . auth()->user()->avatar_path) }}" alt="{{ auth()->user()->name }}" class="w-10 h-10 rounded-full object-cover shadow-sm flex-shrink-0">
                @else
                    <div class="w-10 h-10 rounded-full bg-[#18181b] flex items-center justify-center text-white font-bold text-lg leading-none uppercase flex-shrink-0">
                        {{ $firstLetter }}
                    </div>
                @endif
                <div class="flex-1 leading-tight flex flex-col justify-center min-w-0">
                    <h3 class="text-[13px] font-bold text-[#1a1a1a] truncate">{{ $businessName }}</h3>
                    <div class="mt-1">
                        <span class="inline-block px-2 py-0.5 bg-gray-200/70 text-gray-600 text-[10px] font-semibold rounded-full leading-none truncate">
                            {{ $userRole }}
                        </span>
                    </div>
                </div>
            </div>
            @can('view settings')
            <a href="{{ route('settings.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors p-1 flex-shrink-0" title="Settings">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </a>
            @endcan
        </div>

        <button wire:click="logout" class="hidden">Logout</button>
    </div>
</aside>
