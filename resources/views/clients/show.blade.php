<x-app-layout>
    <x-slot name="header">{{ $client->name }}</x-slot>

    <livewire:clients.client-profile :client="$client" />
</x-app-layout>
