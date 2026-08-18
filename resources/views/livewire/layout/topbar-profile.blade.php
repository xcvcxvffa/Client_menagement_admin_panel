<?php

use Livewire\Volt\Component;
use App\Livewire\Actions\Logout;

new class extends Component {
    public function logout(Logout $logout)
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<x-dropdown align="right" width="48">
    <x-slot name="trigger">
        <button class="flex items-center space-x-2 p-1.5 sm:space-x-2.5 rounded-lg text-sm font-semibold text-gray-700 hover:text-gray-900 hover:bg-gray-50 focus:outline-none transition duration-150">
            <!-- User Icon -->
            @if(auth()->user()->avatar_path)
                <img src="{{ asset('storage/' . auth()->user()->avatar_path) }}" alt="{{ auth()->user()->name }}" class="w-8 h-8 rounded-full object-cover shadow-sm border border-gray-200">
            @else
                <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-xs shadow-sm">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
            @endif
            
            <!-- Username (Desktop Only) -->
            <span class="tracking-tight hidden sm:block">{{ auth()->user()->name }}</span>
            
            <svg class="w-4 h-4 text-gray-400 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
    </x-slot>

    <x-slot name="content">
        <!-- Display Username in Dropdown for Mobile -->
        <div class="block sm:hidden px-4 py-3 border-b border-gray-100">
            <p class="text-sm font-semibold text-gray-900 truncate">{{ auth()->user()->name }}</p>
            <p class="text-xs font-medium text-gray-500 truncate">{{ auth()->user()->email }}</p>
        </div>
        
        <x-dropdown-link :href="route('profile')" wire:navigate>
            {{ __('Profile Settings') }}
        </x-dropdown-link>
        
        <div class="border-t border-gray-100"></div>

        <button wire:click="logout" class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
            {{ __('Log Out') }}
        </button>
    </x-slot>
</x-dropdown>
