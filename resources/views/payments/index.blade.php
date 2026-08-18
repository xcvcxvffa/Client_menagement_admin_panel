<x-app-layout>
    <x-slot name="header">
        {{ __('Payments') }}
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <livewire:payments.list-payments />
    </div>
</x-app-layout>
