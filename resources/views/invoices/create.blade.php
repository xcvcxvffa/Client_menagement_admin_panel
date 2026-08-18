<x-app-layout>
    <x-slot name="header">
        {{ __('Create Invoice') }}
    </x-slot>

    <livewire:invoices.invoice-builder />
</x-app-layout>
