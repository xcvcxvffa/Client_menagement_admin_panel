<?php

use function Livewire\Volt\{state, with};
use App\Models\Lead;

state([
    'activeTab' => 'all',
    'search' => '',
    'selectedRows' => [],
    'showAddModal' => false,
    'newName' => '',
    'newEmail' => '',
    'newCompany' => '',
    'newStatus' => 'NEW',
    'sortBy' => 'latest',
    'viewMode' => 'list',
    'filterStatus' => '',
]);

$deleteSelected = function () {
    \Illuminate\Support\Facades\Gate::authorize('delete leads');
    Lead::whereIn('id', $this->selectedRows)->where('business_id', auth()->user()->current_business_id)->delete();
    $this->selectedRows = [];
    $this->dispatch('notify', message: 'Selected leads deleted successfully.', type: 'success');
};

$deleteLead = function ($id) {
    \Illuminate\Support\Facades\Gate::authorize('delete leads');
    Lead::where('id', $id)->where('business_id', auth()->user()->current_business_id)->delete();
    $this->dispatch('notify', message: 'Lead deleted successfully.', type: 'success');
};

$saveLead = function () {
    \Illuminate\Support\Facades\Gate::authorize('create leads');
    $this->validate([
        'newName' => 'required|string|max:255',
        'newEmail' => 'required|email|max:255',
        'newCompany' => 'nullable|string|max:255',
        'newStatus' => 'required|string',
    ]);

    Lead::create([
        'business_id' => auth()->user()->current_business_id,
        'name' => $this->newName,
        'email' => $this->newEmail,
        'company_name' => $this->newCompany,
        'status' => $this->newStatus,
        'value' => rand(1000, 10000),
    ]);
    $this->reset(['newName', 'newEmail', 'newCompany', 'newStatus', 'showAddModal']);
};

$exportLeads = function () {
    \Illuminate\Support\Facades\Gate::authorize('export leads');
    $leads = Lead::where('business_id', auth()->user()->current_business_id)->get();
    $csv = "ID,Name,Email,Company,Status,Value,Created At\n";
    foreach($leads as $lead) {
        $csv .= "{$lead->id},\"{$lead->name}\",{$lead->email},\"{$lead->company_name}\",{$lead->status},{$lead->value},{$lead->created_at}\n";
    }
    
    return response()->streamDownload(function () use ($csv) {
        echo $csv;
    }, 'leads-export.csv');
};

with(function () {
    $businessId = auth()->user()->current_business_id;
    $allLeads = Lead::where('business_id', $businessId)->get();
    
    $newLeadsCount = $allLeads->where('status', 'NEW')->count();
    $qualifiedLeadsCount = $allLeads->where('value', '>=', 5000)->count();
    $hotLeadsCount = $allLeads->where('status', 'HOT')->count();
    
    $respondedLeads = $allLeads->filter(fn($l) => $l->updated_at->gt($l->created_at));
    $totalHours = $respondedLeads->sum(fn($l) => $l->created_at->diffInHours($l->updated_at) ?: 1);
    $avgResponseTime = $respondedLeads->count() > 0 ? round($totalHours / $respondedLeads->count(), 1) . 'h' : '0h';

    return [
        'newLeadsCount' => $newLeadsCount,
        'qualifiedLeadsCount' => $qualifiedLeadsCount,
        'hotLeadsCount' => $hotLeadsCount,
        'avgResponseTime' => $avgResponseTime,
        'leads' => Lead::where('business_id', $businessId)
            ->when($this->search, function ($query) {
            $query->where(function($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                  ->orWhere('company_name', 'like', '%'.$this->search.'%')
                  ->orWhere('email', 'like', '%'.$this->search.'%');
            });
        })
        ->when($this->filterStatus, function($q) {
            $q->where('status', $this->filterStatus);
        })
        ->when($this->activeTab !== 'all', function ($query) {
            if ($this->activeTab === 'hot') $query->where('status', 'HOT');
            elseif ($this->activeTab === 'new') $query->where('status', 'NEW');
            elseif ($this->activeTab === 'favourite') $query->where('value', '>=', 5000);
            elseif ($this->activeTab === 'assigned to me') $query->where('value', '<', 5000);
            elseif ($this->activeTab === 'overdue') $query->where('status', 'PENDING');
        })
        ->when($this->sortBy === 'latest', fn($q) => $q->latest())
        ->when($this->sortBy === 'oldest', fn($q) => $q->oldest())
        ->when($this->sortBy === 'value_high', fn($q) => $q->orderBy('value', 'desc'))
        ->when($this->sortBy === 'value_low', fn($q) => $q->orderBy('value', 'asc'))
        ->when($this->sortBy === 'customer_asc', fn($q) => $q->orderBy('name', 'asc'))
        ->when($this->sortBy === 'customer_desc', fn($q) => $q->orderBy('name', 'desc'))
        ->when($this->sortBy === 'company_asc', fn($q) => $q->orderBy('company_name', 'asc'))
        ->when($this->sortBy === 'company_desc', fn($q) => $q->orderBy('company_name', 'desc'))
        ->when($this->sortBy === 'email_asc', fn($q) => $q->orderBy('email', 'asc'))
        ->when($this->sortBy === 'email_desc', fn($q) => $q->orderBy('email', 'desc'))
        ->when($this->sortBy === 'status_asc', fn($q) => $q->orderBy('status', 'asc'))
        ->when($this->sortBy === 'status_desc', fn($q) => $q->orderBy('status', 'desc'))
        ->when($this->sortBy === 'score_asc', fn($q) => $q->orderBy('value', 'asc'))
        ->when($this->sortBy === 'score_desc', fn($q) => $q->orderBy('value', 'desc'))
        ->get()
        ->map(function ($lead) {
            $managers = ['Jacob Müller', 'Olivia Davis', 'Liam Johnson', 'James Smith', 'Noah Garcia', 'Zoe Lewis', 'Oliver Hall'];
            $sources = ['Website', 'LinkedIn', 'X', 'Facebook', 'Instagram'];
            return [
                'id' => $lead->id,
                'customer' => $lead->name,
                'avatar' => 'https://ui-avatars.com/api/?name='.urlencode($lead->name).'&background=random',
                'company' => $lead->company_name ?? '-',
                'email' => $lead->email ?? '-',
                'status' => $lead->status ?? 'NEW',
                'manager' => $managers[$lead->id % count($managers)],
                'source' => $sources[$lead->id % count($sources)],
                'score' => (int) max(1, min(10, ceil($lead->value / 1000))),
                'created' => $lead->created_at->format('M d, Y'),
                'checked' => in_array($lead->id, $this->selectedRows ?? []),
            ];
        })->toArray(),
    ];
});

?>

<div class="space-y-6">
    <!-- Top Metrics Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-gray-200 bg-white border border-gray-200 rounded-xl">
        
        <!-- Metric 1 -->
        <div class="p-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-500 mb-1">New Leads</p>
                <div class="flex items-baseline gap-2">
                    <h3 class="text-3xl font-black text-gray-900">{{ $newLeadsCount }}</h3>
                    <span class="text-xs font-bold text-green-500 flex items-center">
                        <svg class="w-3 h-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                        12%
                    </span>
                </div>
            </div>
            <div class="w-24 h-10">
                <svg viewBox="0 0 100 30" class="w-full h-full stroke-orange-500" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M0,25 L15,10 L30,20 L45,5 L60,15 L75,5 L90,15 L100,5" />
                </svg>
            </div>
        </div>
        
        <!-- Metric 2 -->
        <div class="p-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-500 mb-1">Qualified Leads</p>
                <div class="flex items-baseline gap-2">
                    <h3 class="text-3xl font-black text-gray-900">{{ $qualifiedLeadsCount }}</h3>
                    <span class="text-xs font-bold text-green-500 flex items-center">
                        <svg class="w-3 h-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                        4.2%
                    </span>
                </div>
            </div>
            <div class="w-24 h-10">
                <svg viewBox="0 0 100 30" class="w-full h-full stroke-orange-500" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M0,20 L20,25 L40,10 L60,20 L80,5 L100,10" />
                </svg>
            </div>
        </div>
        
        <!-- Metric 3 -->
        <div class="p-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-500 mb-1">Avg Response Time</p>
                <div class="flex items-baseline gap-2">
                    <h3 class="text-3xl font-black text-gray-900">{{ $avgResponseTime }}</h3>
                    <span class="text-xs font-bold text-green-500 flex items-center">
                        <svg class="w-3 h-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>
                        15%
                    </span>
                </div>
            </div>
            <div class="w-24 h-10">
                <svg viewBox="0 0 100 30" class="w-full h-full stroke-orange-500" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M0,15 L25,5 L50,25 L75,10 L100,20" />
                </svg>
            </div>
        </div>

        <!-- Metric 4 -->
        <div class="p-5 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-500 mb-1">Hot Leads</p>
                <div class="flex items-baseline gap-2">
                    <h3 class="text-3xl font-black text-gray-900">{{ $hotLeadsCount }}</h3>
                    <span class="text-xs font-bold text-red-500 flex items-center">
                        <svg class="w-3 h-3 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>
                        2%
                    </span>
                </div>
            </div>
            <div class="w-24 h-10">
                <svg viewBox="0 0 100 30" class="w-full h-full stroke-orange-500" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M0,5 L20,15 L40,10 L60,25 L80,15 L100,25" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Main Table Container -->
    <div class="bg-white border border-gray-200 rounded-xl relative">
        
        <!-- Tabs -->
        <div class="border-b border-gray-200 flex gap-2 px-2 overflow-x-auto">
            @php $tabs = ['All', 'Favourite', 'New', 'Assigned to me', 'Overdue', 'Hot']; @endphp
            @foreach($tabs as $tab)
                <button wire:click="$set('activeTab', '{{ strtolower($tab) }}')" 
                        class="px-5 py-4 text-xs font-bold transition-colors whitespace-nowrap {{ $activeTab === strtolower($tab) ? 'text-orange-500 border-b-2 border-orange-500' : 'text-gray-500 hover:text-gray-900 border-b-2 border-transparent' }}">
                    {{ $tab }}
                </button>
            @endforeach
        </div>

        <!-- Toolbar -->
        <div class="p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100">
            <!-- Search & Filters -->
            <div class="flex items-center gap-3">
                <div class="relative w-64">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5">
                        <svg class="w-4.5 h-4.5 text-gray-400" style="width: 1.125rem; height: 1.125rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </span>
                    <input type="text" wire:model.live="search" placeholder="Search..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border-transparent rounded-lg text-sm focus:bg-white focus:ring-1 focus:ring-gray-200 focus:border-gray-200" />
                </div>
                
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" @click.away="open = false" class="p-2 rounded-lg transition-colors {{ $filterStatus ? 'text-orange-500 bg-orange-50' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-50' }}" title="Filter">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" /></svg>
                    </button>
                    
                    <div x-show="open" 
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="transform opacity-0 scale-95"
                         x-transition:enter-end="transform opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="transform opacity-100 scale-100"
                         x-transition:leave-end="transform opacity-0 scale-95"
                         class="absolute z-50 left-0 sm:left-auto sm:-right-2 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden" 
                         style="display: none;">
                        <div class="px-4 py-2 border-b border-gray-100 bg-gray-50">
                            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Filter by Status</h3>
                        </div>
                        <div class="py-1 px-1.5">
                            <button wire:click="$set('filterStatus', '')" @click="open = false" class="w-full text-left px-3 py-2 text-[13.5px] rounded-lg flex items-center justify-between transition-colors mb-0.5 {{ $filterStatus === '' ? 'font-bold text-[#9a3412] bg-[#fff7ed]' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span>All Statuses</span>
                                @if($filterStatus === '') <svg class="w-4 h-4 text-[#ea580c] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> @endif
                            </button>
                            <button wire:click="$set('filterStatus', 'NEW')" @click="open = false" class="w-full text-left px-3 py-2 text-[13.5px] rounded-lg flex items-center justify-between transition-colors mb-0.5 {{ $filterStatus === 'NEW' ? 'font-bold text-[#9a3412] bg-[#fff7ed]' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span class="flex items-center"><span class="w-2 h-2 inline-block rounded-full bg-blue-500 mr-2"></span> New</span>
                                @if($filterStatus === 'NEW') <svg class="w-4 h-4 text-[#ea580c] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> @endif
                            </button>
                            <button wire:click="$set('filterStatus', 'HOT')" @click="open = false" class="w-full text-left px-3 py-2 text-[13.5px] rounded-lg flex items-center justify-between transition-colors mb-0.5 {{ $filterStatus === 'HOT' ? 'font-bold text-[#9a3412] bg-[#fff7ed]' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span class="flex items-center"><span class="w-2 h-2 inline-block rounded-full bg-red-500 mr-2"></span> Hot</span>
                                @if($filterStatus === 'HOT') <svg class="w-4 h-4 text-[#ea580c] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> @endif
                            </button>
                            <button wire:click="$set('filterStatus', 'PENDING')" @click="open = false" class="w-full text-left px-3 py-2 text-[13.5px] rounded-lg flex items-center justify-between transition-colors mb-0.5 {{ $filterStatus === 'PENDING' ? 'font-bold text-[#9a3412] bg-[#fff7ed]' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span class="flex items-center"><span class="w-2 h-2 inline-block rounded-full bg-yellow-500 mr-2"></span> Pending</span>
                                @if($filterStatus === 'PENDING') <svg class="w-4 h-4 text-[#ea580c] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> @endif
                            </button>
                            <button wire:click="$set('filterStatus', 'WON')" @click="open = false" class="w-full text-left px-3 py-2 text-[13.5px] rounded-lg flex items-center justify-between transition-colors mb-0.5 {{ $filterStatus === 'WON' ? 'font-bold text-[#9a3412] bg-[#fff7ed]' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span class="flex items-center"><span class="w-2 h-2 inline-block rounded-full bg-green-500 mr-2"></span> Won</span>
                                @if($filterStatus === 'WON') <svg class="w-4 h-4 text-[#ea580c] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> @endif
                            </button>
                            <button wire:click="$set('filterStatus', 'LOST')" @click="open = false" class="w-full text-left px-3 py-2 text-[13.5px] rounded-lg flex items-center justify-between transition-colors mb-0.5 {{ $filterStatus === 'LOST' ? 'font-bold text-[#9a3412] bg-[#fff7ed]' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span class="flex items-center"><span class="w-2 h-2 inline-block rounded-full bg-gray-500 mr-2"></span> Lost</span>
                                @if($filterStatus === 'LOST') <svg class="w-4 h-4 text-[#ea580c] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg> @endif
                            </button>
                        </div>
                    </div>
                </div>
                <button wire:click="$set('sortBy', '{{ $sortBy === 'latest' ? 'oldest' : ($sortBy === 'oldest' ? 'value_high' : ($sortBy === 'value_high' ? 'value_low' : 'latest')) }}')" class="p-2 {{ $sortBy !== 'latest' ? 'text-orange-500 bg-orange-50' : 'text-gray-400 hover:text-gray-600 hover:bg-gray-50' }} rounded-lg transition-colors" title="Sort (Current: {{ $sortBy }})">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                </button>
                <button class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-50 transition-colors" title="Columns">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                </button>
            </div>
            
            <!-- Actions -->
            <div class="flex items-center gap-3">
                <div class="flex items-center bg-gray-50 rounded-lg p-1">
                    <button wire:click="$set('viewMode', 'list')" class="p-1 rounded-md transition-colors {{ $viewMode === 'list' ? 'text-gray-900 bg-white shadow-sm' : 'text-gray-400 hover:text-gray-600' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg></button>
                    <button wire:click="$set('viewMode', 'grid')" class="p-1 rounded-md transition-colors {{ $viewMode === 'grid' ? 'text-gray-900 bg-white shadow-sm' : 'text-gray-400 hover:text-gray-600' }}"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg></button>
                </div>
                
                <button class="px-4 py-2 text-sm font-bold text-gray-600 hover:text-gray-900 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                    Import
                </button>
                <div class="flex items-center">
                @can('export leads')
                <button wire:click="exportLeads" class="px-4 py-2 text-sm font-bold text-gray-600 hover:text-gray-900 flex items-center gap-2 border-r border-gray-200 pr-5 mr-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                    Export
                </button>
                @endcan

                @can('create leads')
                <button wire:click="$set('showAddModal', true)" class="bg-orange-50 text-orange-600 hover:bg-orange-100 px-3 md:px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2 transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    <span class="hidden sm:inline">Add Lead</span>
                </button>
                @endcan
                </div>
            </div>
        </div>

        <!-- Table / Grid -->
        @if($viewMode === 'list')
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse whitespace-nowrap min-w-[1000px]">
                <thead>
                    <tr class="bg-white">
                        <th class="py-4 pl-4 pr-3 text-[10px] font-bold uppercase tracking-wider text-gray-400 w-10">
                            <input type="checkbox" class="rounded border-gray-300 text-orange-500 focus:ring-orange-500 w-4 h-4" />
                        </th>
                        <th class="py-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400 cursor-pointer hover:text-gray-700 transition-colors group" wire:click="$set('sortBy', '{{ $sortBy === 'customer_asc' ? 'customer_desc' : 'customer_asc' }}')">
                            <div class="flex items-center gap-1">
                                Customer
                                @if(str_starts_with($sortBy, 'customer'))
                                    <svg class="w-3 h-3 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortBy === 'customer_asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" /></svg>
                                @else
                                    <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                                @endif
                            </div>
                        </th>
                        <th class="py-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400 cursor-pointer hover:text-gray-700 transition-colors group" wire:click="$set('sortBy', '{{ $sortBy === 'company_asc' ? 'company_desc' : 'company_asc' }}')">
                            <div class="flex items-center gap-1">
                                Company
                                @if(str_starts_with($sortBy, 'company'))
                                    <svg class="w-3 h-3 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortBy === 'company_asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" /></svg>
                                @else
                                    <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                                @endif
                            </div>
                        </th>
                        <th class="py-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400 cursor-pointer hover:text-gray-700 transition-colors group" wire:click="$set('sortBy', '{{ $sortBy === 'email_asc' ? 'email_desc' : 'email_asc' }}')">
                            <div class="flex items-center gap-1">
                                Email
                                @if(str_starts_with($sortBy, 'email'))
                                    <svg class="w-3 h-3 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortBy === 'email_asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" /></svg>
                                @else
                                    <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                                @endif
                            </div>
                        </th>
                        <th class="py-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400 cursor-pointer hover:text-gray-700 transition-colors group" wire:click="$set('sortBy', '{{ $sortBy === 'status_asc' ? 'status_desc' : 'status_asc' }}')">
                            <div class="flex items-center gap-1">
                                Status
                                @if(str_starts_with($sortBy, 'status'))
                                    <svg class="w-3 h-3 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortBy === 'status_asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" /></svg>
                                @else
                                    <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                                @endif
                            </div>
                        </th>
                        <th class="py-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Manager</th>
                        <th class="py-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Source</th>
                        <th class="py-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400 cursor-pointer hover:text-gray-700 transition-colors group" wire:click="$set('sortBy', '{{ $sortBy === 'score_asc' ? 'score_desc' : 'score_asc' }}')">
                            <div class="flex items-center gap-1">
                                Score
                                @if(str_starts_with($sortBy, 'score'))
                                    <svg class="w-3 h-3 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortBy === 'score_asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" /></svg>
                                @else
                                    <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                                @endif
                            </div>
                        </th>
                        <th class="py-4 px-3 text-[10px] font-bold uppercase tracking-wider text-gray-400 cursor-pointer hover:text-gray-700 transition-colors group" wire:click="$set('sortBy', '{{ $sortBy === 'latest' ? 'oldest' : 'latest' }}')">
                            <div class="flex items-center gap-1">
                                Created
                                @if(in_array($sortBy, ['latest', 'oldest']))
                                    <svg class="w-3 h-3 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $sortBy === 'oldest' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7' }}" /></svg>
                                @else
                                    <svg class="w-3 h-3 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                                @endif
                            </div>
                        </th>
                        <th class="py-4 px-4 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($leads as $lead)
                    <tr class="hover:bg-gray-50 transition-colors {{ $lead['checked'] ? 'bg-orange-50/30' : '' }}">
                        <td class="py-4 pl-4 pr-3">
                            <input type="checkbox" wire:model.live="selectedRows" value="{{ $lead['id'] }}" class="rounded border-gray-300 text-orange-500 focus:ring-orange-500 w-4 h-4" />
                        </td>
                        <td class="py-4 px-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $lead['avatar'] }}" class="w-8 h-8 rounded-full" />
                                <span class="font-bold text-sm text-gray-900">{{ $lead['customer'] }}</span>
                            </div>
                        </td>
                        <td class="py-4 px-3 text-sm font-medium text-gray-500">{{ $lead['company'] }}</td>
                        <td class="py-4 px-3 text-sm font-medium text-gray-500">{{ $lead['email'] }}</td>
                        <td class="py-4 px-3">
                            <span class="px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider rounded border border-gray-200 text-gray-600 bg-white shadow-sm">
                                {{ $lead['status'] }}
                            </span>
                        </td>
                        <td class="py-4 px-3 text-sm font-bold text-gray-900">{{ $lead['manager'] }}</td>
                        <td class="py-4 px-3 text-sm font-medium text-gray-500">{{ $lead['source'] }}</td>
                        <td class="py-4 px-3">
                            <div class="flex items-center gap-1">
                                <!-- Score Bars -->
                                @for($i = 1; $i <= 10; $i++)
                                    <div class="w-1 h-3 rounded-full {{ $i <= $lead['score'] ? ($lead['score'] >= 7 ? 'bg-green-500' : ($lead['score'] >= 5 ? 'bg-yellow-400' : 'bg-orange-400')) : 'bg-gray-200' }}"></div>
                                @endfor
                                <span class="ml-2 text-xs font-bold text-gray-400">{{ $lead['score'] }}/10</span>
                            </div>
                        </td>
                        <td class="py-4 px-3 text-sm font-medium text-gray-500">{{ $lead['created'] }}</td>
                        <td class="py-4 px-4 text-right">
                            <button class="text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z" /></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <!-- Grid View -->
        <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 bg-gray-50/50">
            @foreach($leads as $lead)
            <div class="bg-white border border-gray-200 rounded-xl p-5 hover:shadow-lg hover:border-gray-300 transition-all relative {{ $lead['checked'] ? 'ring-2 ring-orange-500 border-transparent' : '' }}">
                <div class="absolute top-4 right-4 z-10">
                    <input type="checkbox" wire:model.live="selectedRows" value="{{ $lead['id'] }}" class="rounded border-gray-300 text-orange-500 focus:ring-orange-500 w-5 h-5 shadow-sm" />
                </div>
                <div class="flex items-center gap-4 mb-4">
                    <img src="{{ $lead['avatar'] }}" class="w-12 h-12 rounded-full border border-gray-100 shadow-sm" />
                    <div>
                        <h4 class="font-bold text-gray-900 leading-tight">{{ $lead['customer'] }}</h4>
                        <p class="text-xs font-medium text-gray-500 mt-0.5">{{ $lead['company'] }}</p>
                    </div>
                </div>
                <div class="space-y-3 text-sm mb-5">
                    <div class="flex items-center gap-2 text-gray-600">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        <span class="truncate">{{ $lead['email'] }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-gray-600">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        <span>{{ $lead['manager'] }}</span>
                    </div>
                    <div class="flex justify-between items-center bg-gray-50 p-2 rounded-lg border border-gray-100">
                        <span class="px-2 py-0.5 text-[10px] font-bold uppercase rounded text-gray-600 bg-white shadow-sm border border-gray-200">{{ $lead['status'] }}</span>
                        <div class="flex items-center gap-1">
                            <span class="font-bold text-gray-900 text-xs">{{ $lead['score'] }}</span>
                            <span class="text-[10px] font-bold text-gray-400">/10</span>
                        </div>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-100 flex justify-between items-start">
                    <span class="text-xs font-medium text-gray-400 pt-1">{{ $lead['created'] }}</span>
                    <div class="flex flex-col items-end gap-1.5">
                        <button class="text-xs text-orange-500 font-bold hover:text-orange-600 flex items-center gap-1 group transition-colors">
                            View Details
                            <svg class="w-3.5 h-3.5 transform group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                        @can('delete leads')
                        <x-confirm-action action="deleteLead({{ $lead['id'] }})" title="Delete Lead" message="Are you sure you want to delete this lead?" buttonText="Delete">
                            <x-slot:trigger>
                                <button type="button" class="text-[10px] text-red-500 font-bold hover:text-red-600 flex items-center gap-1 group transition-colors mt-1.5 ml-auto">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    Delete
                                </button>
                            </x-slot:trigger>
                        </x-confirm-action>
                        @endcan
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <!-- Floating Action Bar (Simulated) -->
        @if(count($selectedRows) > 0)
        <div class="absolute bottom-6 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white rounded-xl shadow-xl flex items-center px-2 py-2 gap-1 z-10">
            <div class="px-3 py-1.5 text-xs font-medium text-gray-300">Selected: {{ count($selectedRows) }}</div>
            <div class="w-px h-4 bg-gray-700 mx-1"></div>
            <button class="px-4 py-1.5 text-xs font-bold hover:bg-gray-800 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                Edit
            </button>
            <button class="px-4 py-1.5 text-xs font-bold hover:bg-gray-800 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                Assign to
            </button>
            @can('delete leads')
            <button wire:click="deleteSelected" class="px-4 py-1.5 text-xs font-bold text-red-400 hover:bg-gray-800 rounded-lg flex items-center gap-2 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                Delete
            </button>
            @endcan
            <button wire:click="$set('selectedRows', [])" class="px-4 py-1.5 text-xs font-bold text-gray-900 bg-white rounded-lg ml-1 hover:bg-gray-100 transition-colors">
                Discard
            </button>
        </div>
        @endif
    </div>

    <!-- Add Lead Modal -->
    @if($showAddModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 sm:p-0">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-bold text-gray-900">Add New Lead</h3>
                <button wire:click="$set('showAddModal', false)" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Name <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="newName" class="w-full px-3 py-2 bg-gray-50 border-transparent rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                    @error('newName') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Email <span class="text-red-500">*</span></label>
                    <input type="email" wire:model="newEmail" class="w-full px-3 py-2 bg-gray-50 border-transparent rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                    @error('newEmail') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Company</label>
                    <input type="text" wire:model="newCompany" class="w-full px-3 py-2 bg-gray-50 border-transparent rounded-lg text-sm focus:bg-white focus:ring-2 focus:ring-orange-500 focus:border-transparent" />
                    @error('newCompany') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">Status <span class="text-red-500">*</span></label>
                    <x-custom-select wire:model="newStatus" placeholder="Select Status"
                        :options="[
                            ['id' => 'NEW', 'name' => 'New'],
                            ['id' => 'HOT', 'name' => 'Hot'],
                            ['id' => 'OPEN', 'name' => 'Open'],
                            ['id' => 'QUALIFIED', 'name' => 'Qualified']
                        ]" />
                    @error('newStatus') <span class="text-xs text-red-500 font-bold mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 flex justify-end gap-2 border-t border-gray-100">
                <button wire:click="$set('showAddModal', false)" class="px-4 py-2 text-sm font-bold text-gray-600 hover:text-gray-900">Cancel</button>
                <button wire:click="saveLead" class="px-4 py-2 text-sm font-bold text-white bg-orange-500 hover:bg-orange-600 rounded-lg transition-colors">Save Lead</button>
            </div>
        </div>
    </div>
    @endif
</div>
