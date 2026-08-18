<x-app-layout>
    <x-slot name="header">
        {{ __('Team Management') }}
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-8">
        <livewire:team.team-manager />
    </div>
</x-app-layout>
