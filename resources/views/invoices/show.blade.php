<x-app-layout>
    <x-slot name="header">
        {{ __('Invoice Details') }}
    </x-slot>

    <livewire:invoices.show-invoice :invoice="$invoice" />
</x-app-layout>
