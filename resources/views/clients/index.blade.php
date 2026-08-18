<x-app-layout>
    <x-slot name="header">
        Clients
    </x-slot>

    {{-- Page Header with Add Client button --}}
    <div class="flex items-start justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Clients</h1>
            <p class="text-sm text-gray-500 mt-0.5">View all clients and jump into their projects</p>
        </div>
        @can('create clients')
        <a href="{{ route('clients.create') }}" wire:navigate
           class="inline-flex items-center justify-center px-4 py-2 bg-[#d9480f] hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors flex-shrink-0">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Add Client
        </a>
        @endcan
    </div>

    <livewire:clients.list-clients />
</x-app-layout>
