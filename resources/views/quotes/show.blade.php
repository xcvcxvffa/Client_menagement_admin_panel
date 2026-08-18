<x-app-layout>
    <x-slot name="header">
        {{ __('Quote Details') }}
    </x-slot>

    <livewire:quotes.show-quote :quote="$quote" />
</x-app-layout>
