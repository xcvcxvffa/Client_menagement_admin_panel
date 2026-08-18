<x-app-layout>
    <x-slot name="header">
        {{ __('Invoices') }}
    </x-slot>

    <livewire:invoices.list-invoices />
</x-app-layout>
