<x-app-layout>
    <x-slot name="header">
        {{ __('Billing Center') }}
    </x-slot>

    <div class="max-w-7xl mx-auto">
        <livewire:billing.dashboard />
    </div>
</x-app-layout>
