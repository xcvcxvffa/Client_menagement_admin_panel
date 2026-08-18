<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-3 text-sm">
            <span class="text-indigo-600 dark:text-indigo-400 font-semibold">Settings</span>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
        <livewire:settings.business-settings />
    </div>
</x-app-layout>
