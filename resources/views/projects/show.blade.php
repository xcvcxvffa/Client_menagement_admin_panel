<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('projects.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Projects</a>
        <span class="text-gray-500 mx-2">/</span>
        {{ $project->name }}
    </x-slot>

    <!-- Since we handle detail viewing inside the index drawer now, this page might just redirect back or show a full-page version if needed. -->
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <a href="{{ route('projects.index') }}" class="text-blue-500 hover:underline">&larr; Back to Projects</a>
                <h1 class="text-2xl font-bold mt-4">{{ $project->name }}</h1>
                <p class="text-gray-600 mt-2">To manage this project, please open the Projects dashboard.</p>
            </div>
        </div>
    </div>
</x-app-layout>
