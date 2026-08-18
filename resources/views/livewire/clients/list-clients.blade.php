<?php

use App\Models\Client;
use App\Models\Project;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, with};

state([
    'search' => '',
]);

with(function () {
    $businessId = Auth::user()->current_business_id;

    $clientsQuery = Client::where('business_id', $businessId);

    if ($this->search) {
        $clientsQuery->where(function ($q) {
            $q->where('name', 'like', "%{$this->search}%")
              ->orWhere('company_name', 'like', "%{$this->search}%")
              ->orWhere('email', 'like', "%{$this->search}%");
        });
    }
    $clients = $clientsQuery->with('projects')->withCount('projects')->latest()->paginate(15);
    $totalClients = Client::where('business_id', $businessId)->count();
    $totalProjects = Project::where('business_id', $businessId)->count();
    $ongoingProjects = Project::where('business_id', $businessId)->whereNotIn('status', ['completed', 'cancelled'])->count();
    $completedProjects = Project::where('business_id', $businessId)->where('status', 'completed')->count();

    return [
        'clients' => $clients,
        'totalClients' => $totalClients,
        'totalProjects' => $totalProjects,
        'ongoingProjects' => $ongoingProjects,
        'completedProjects' => $completedProjects,
    ];
});

?>

<div>
    <!-- Stats Row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Total clients</h4>
            <div class="text-2xl font-bold text-gray-900">{{ $totalClients }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Total projects</h4>
            <div class="text-2xl font-bold text-gray-900">{{ $totalProjects }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Ongoing</h4>
            <div class="text-2xl font-bold text-green-600">{{ $ongoingProjects }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Completed</h4>
            <div class="text-2xl font-bold text-blue-600">{{ $completedProjects }}</div>
        </div>
    </div>

    <!-- Search and Actions Row -->
    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <div class="relative w-full max-w-sm">
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="text" wire:model.live="search" placeholder="Search by name or phone..."
                   class="w-full pl-9 pr-4 py-2 border border-gray-250 bg-white rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500">
        </div>
        <div class="flex items-center gap-2">
            <button class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-250 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                CSV
            </button>
            <button class="px-4 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-250 rounded-lg hover:bg-gray-50 transition-colors flex items-center gap-2 shadow-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                JSON
            </button>
        </div>
    </div>

    <!-- Clients Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @foreach($clients as $client)
            <div class="bg-white border border-gray-100 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                <div class="p-5">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            @php
                                $colors = [
                                    'bg-red-50 text-red-600',
                                    'bg-orange-50 text-orange-600',
                                    'bg-amber-50 text-amber-600',
                                    'bg-green-50 text-green-600',
                                    'bg-emerald-50 text-emerald-600',
                                    'bg-teal-50 text-teal-600',
                                    'bg-cyan-50 text-cyan-600',
                                    'bg-blue-50 text-blue-600',
                                    'bg-indigo-50 text-indigo-600',
                                    'bg-violet-50 text-violet-600',
                                    'bg-purple-50 text-purple-600',
                                    'bg-fuchsia-50 text-fuchsia-600',
                                    'bg-pink-50 text-pink-600',
                                    'bg-rose-50 text-rose-600',
                                ];
                                $colorClass = $colors[abs(crc32($client->name)) % count($colors)];
                                $initials = preg_match_all('/(?<=\s|^)[a-z]/i', $client->name, $matches)
                                    ? strtoupper(implode('', array_slice($matches[0], 0, 2)))
                                    : strtoupper(substr($client->name, 0, 2));
                            @endphp
                            <div class="w-12 h-12 rounded-full {{ $colorClass }} flex items-center justify-center font-bold text-lg flex-shrink-0">
                                {{ $initials }}
                            </div>
                            <div>
                                <a href="{{ route('clients.show', $client) }}" class="text-base font-bold text-gray-900 hover:text-orange-600 transition-colors line-clamp-1">
                                    {{ $client->name }}
                                </a>
                                @if($client->phone)
                                <div class="text-sm text-gray-500 flex items-center mt-0.5">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                    {{ $client->phone }}
                                </div>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('clients.show', $client) }}" class="flex items-center justify-center w-8 h-8 rounded-full bg-[#FAFAF8] text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors">
                            <svg class="w-4 h-4 ml-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </a>
                    </div>

                    <div class="mt-5 p-3 bg-gray-50 rounded-lg flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                            <span class="text-sm font-semibold text-gray-700">{{ $client->projects_count }} project{{ $client->projects_count !== 1 ? 's' : '' }}</span>
                        </div>
                        @if($client->projects->isNotEmpty())
                            @php $latestProject = $client->projects->sortByDesc('created_at')->first(); @endphp
                            <div class="flex items-center gap-2 text-sm">
                                <span class="font-medium text-gray-900 truncate max-w-[100px]">{{ $latestProject->name }}</span>
                                <span class="text-xs font-semibold text-purple-600 bg-purple-100 px-2 py-0.5 rounded-full">{{ $latestProject->status }}</span>
                            </div>
                        @else
                            <span class="text-xs text-gray-500 italic">No projects yet</span>
                        @endif
                    </div>
                </div>
                
                <div class="px-5 py-3 border-t border-gray-100 bg-white rounded-b-xl flex items-center justify-between">
                    <span class="text-xs text-gray-400 font-medium">Added {{ $client->created_at->diffForHumans() }}</span>
                    @can('create projects')
                    <a href="{{ route('projects.index') }}?client_id={{ $client->id }}" class="text-xs font-bold text-orange-600 hover:text-orange-700 flex items-center">
                        <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                        New Project
                    </a>
                    @endcan
                </div>
            </div>
        @endforeach
    </div>

    @if($clients->isEmpty())
        <div class="text-center py-12 bg-white rounded-xl border border-gray-100 shadow-sm mt-4">
            <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <h3 class="mt-2 text-sm font-semibold text-gray-900">No clients found</h3>
            <p class="mt-1 text-sm text-gray-500">Get started by creating a new client.</p>
        </div>
    @endif

    <div class="mt-6">
        {{ $clients->links(data: ['scrollTo' => false]) }}
    </div>
</div>
