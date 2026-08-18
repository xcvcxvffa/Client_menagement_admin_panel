<x-app-layout>
    <x-slot name="header">Add New Client</x-slot>

    {{-- Page Header row --}}
    <div class="flex items-start justify-between mb-8">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Add New Client</h1>
            <p class="text-sm mt-0.5">
                <span class="text-orange-500 font-medium">Create</span>
                <span class="text-gray-500"> a new client profile</span>
            </p>
        </div>

        <a href="{{ route('clients.index') }}" wire:navigate
           class="inline-flex items-center px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 bg-white hover:bg-gray-50 transition-colors shadow-sm flex-shrink-0">
            <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Clients
        </a>
    </div>

    <livewire:clients.create-client />
</x-app-layout>

