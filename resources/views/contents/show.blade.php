<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Content Detail') }}
            </h2>
            <a href="{{ route('contents.index') }}" class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">&larr; Back to Content</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:contents.content-detail :content-id="$id" />
        </div>
    </div>
</x-app-layout>
