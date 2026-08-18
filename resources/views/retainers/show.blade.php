<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('retainers.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Retainers</a>
        <span class="text-gray-500 mx-2">/</span>
        {{ $retainer->name }}
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <a href="{{ route('retainers.index') }}" class="text-blue-500 hover:underline">&larr; Back to Retainers</a>
                <h1 class="text-2xl font-bold mt-4">{{ $retainer->name }}</h1>
                <p class="text-gray-600 mt-2">To manage this retainer, please open the Retainers dashboard.</p>
            </div>
        </div>
    </div>
</x-app-layout>
