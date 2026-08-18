<x-app-layout>
    <style>
        /* Override layout padding and max-width to make chat full bleed */
        main { padding: 0 !important; overflow: hidden !important; }
        main > div.max-w-7xl { max-width: 100% !important; margin: 0 !important; height: 100% !important; }
    </style>
    <div class="h-full w-full">
        <livewire:messages.list-messages />
    </div>
</x-app-layout>
