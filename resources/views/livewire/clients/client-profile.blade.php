<?php

use App\Models\Client;
use App\Models\ActivityLog;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use function Livewire\Volt\{state, uses, with};

uses([WithFileUploads::class]);

state([
    'client',
    'activeTab' => 'general',
    'uploadedFile' => null,
    
    // Edit Client
    'showEditModal' => false,
    'edit_name' => '',
    'edit_email' => '',
    'edit_phone' => '',
    'edit_company_name' => '',
    'edit_address' => '',
    'edit_tax_number' => '',
    'edit_currency' => 'INR',
    // Delete Client
    'showDeleteModal' => false,


]);

$uploadAttachment = function () {
    $this->validate([
        'uploadedFile' => 'required|file|max:10240',
    ]);

    $media = $this->client->addMedia($this->uploadedFile)
        ->toMediaCollection('attachments');

    ActivityLog::create([
        'description' => "Uploaded attachment '{$media->file_name}' to client '{$this->client->name}'",
        'subject_id' => $this->client->id,
        'subject_type' => Client::class,
    ]);

    $this->uploadedFile = null;
    $this->dispatch('notify', message: 'File uploaded successfully.', type: 'success');
};

$deleteAttachment = function ($mediaId) {
    $media = Media::find($mediaId);
    if ($media && $media->model_id === $this->client->id && $media->model_type === Client::class) {
        $fileName = $media->file_name;
        $media->delete();

        ActivityLog::create([
            'description' => "Deleted attachment '{$fileName}' from client '{$this->client->name}'",
            'subject_id' => $this->client->id,
            'subject_type' => Client::class,
        ]);

        $this->dispatch('notify', message: 'File deleted successfully.', type: 'success');
    }
};

$openEditModal = function () {
    $this->edit_name = $this->client->name;
    $this->edit_email = $this->client->email;
    $this->edit_phone = $this->client->phone;
    $this->edit_company_name = $this->client->company_name;
    $this->edit_address = $this->client->address;
    $this->edit_tax_number = $this->client->tax_number;
    $this->edit_currency = $this->client->currency ?? 'INR';
    $this->showEditModal = true;
};

$updateClient = function () {
    $this->validate([
        'edit_name' => 'required|string|max:255',
        'edit_email' => 'required|email|max:255|unique:clients,email,' . $this->client->id,
        'edit_phone' => 'nullable|string|max:50',
        'edit_company_name' => 'nullable|string|max:255',
        'edit_address' => 'nullable|string',
        'edit_tax_number' => 'nullable|string|max:50',
        'edit_currency' => 'required|string|max:3',
    ]);

    $this->client->update([
        'name' => $this->edit_name,
        'email' => $this->edit_email,
        'phone' => $this->edit_phone,
        'company_name' => $this->edit_company_name,
        'address' => $this->edit_address,
        'tax_number' => $this->edit_tax_number,
        'currency' => $this->edit_currency,
    ]);

    $this->showEditModal = false;
    $this->dispatch('notify', message: 'Client updated successfully.', type: 'success');
};

$deleteClient = function () {
    $this->client->delete();
    return redirect()->route('clients.index')->with('message', 'Client deleted successfully.');
};

$deleteProject = function ($projectId) {
    $project = \App\Models\Project::findOrFail($projectId);
    if ($project->client_id === $this->client->id) {
        $project->delete();
        $this->dispatch('notify', message: 'Project deleted successfully.', type: 'success');
    }
};

with(function () {
    $projects  = $this->client->projects()->latest()->get();
    $retainers = $this->client->retainers()->latest()->get();

    $contents  = $this->client->contents()->with(['platform', 'contentType'])->latest()->get();
    $invoices  = $this->client->invoices()->with('payments')->latest()->get();

    $attachments = $this->client->getMedia('attachments');
    $activities = ActivityLog::with('user')->where('subject_type', Client::class)->where('subject_id', $this->client->id)->latest()->get();

    // Budget from all projects
    $totalBudget = $projects->sum('budget');
    $totalInvoiced = $invoices->sum('total');
    $totalPaid = $invoices->sum('amount_paid');
    $balance = max(0, $totalInvoiced - $totalPaid);

    $currencySymbol = $this->client->currency === 'USD' ? '$' : '₹';
    $currencyName   = $this->client->currency === 'USD' ? 'US Dollar' : 'Indian Rupee';

    return [
        'projects'         => $projects,
        'retainers'        => $retainers,

        'contents'         => $contents,
        'invoices'         => $invoices,

        'attachments'      => $attachments,
        'totalInvoiced'    => $totalInvoiced,
        'totalPaid'        => $totalPaid,
        'totalOutstanding' => $balance,
        'currencySymbol'   => $currencySymbol,
        'currencyName'     => $currencyName,
        'totalBudget'      => $totalBudget,
        'activities'       => $activities,
    ];
});

?>

<div>
    {{-- ── TOP NAV ─────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('clients.index') }}" wire:navigate
           class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 transition-colors">
            <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Clients
        </a>
    </div>

    {{-- ── MAIN PROFILE CARD ───────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
        <div class="flex items-start justify-between">
            {{-- Client info left --}}
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ $client->name }}</h1>
                <div class="flex items-center gap-4 mt-1.5 text-sm text-gray-500">
                    @if($client->phone)
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        {{ $client->phone }}
                    </span>
                    @endif
                    <span class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Added {{ $client->created_at->diffForHumans() }}
                    </span>
                </div>
                <div class="mt-4 mb-5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-50 text-gray-700 border border-gray-100">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg>
                        Billed in {{ $client->currency }} · {{ $currencyName }}
                    </span>
                </div>

                <hr class="border-gray-50 my-5">

                {{-- Financial stats --}}
                <div class="flex items-center gap-6">
                    <div>
                        <span class="text-[11px] text-gray-500 block">Budget</span>
                        <span class="text-[15px] font-bold text-gray-900 block leading-tight">{{ $currencySymbol }}{{ number_format($totalBudget) }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-gray-500 block">Paid</span>
                        <span class="text-[15px] font-bold text-emerald-500 block leading-tight">{{ $currencySymbol }}{{ number_format($totalPaid) }}</span>
                    </div>
                    <div>
                        <span class="text-[11px] text-gray-500 block">Balance</span>
                        <span class="text-[15px] font-bold text-orange-600 block leading-tight">
                            {{ $currencySymbol }}{{ number_format($totalOutstanding) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Action buttons right --}}
            <div class="flex items-center gap-2 flex-shrink-0">
                @can('create projects')
                <a href="{{ route('projects.index') }}?client_id={{ $client->id }}"
                   class="inline-flex items-center px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Project
                </a>
                @endcan
                @can('edit clients')
                <button wire:click="openEditModal" class="p-2 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                @endcan
                @can('delete clients')
                <button wire:click="$set('showDeleteModal', true)" class="p-2 rounded-lg border border-gray-200 text-gray-500 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-500 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                @endcan
            </div>
        </div>
    </div>

    {{-- ── TABS ────────────────────────────────────────────────── --}}
    <div class="border-b border-gray-200 mb-6">
        <nav class="flex gap-1 -mb-px">
            @php
            $tabs = [
                ['key' => 'general',     'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>',                                                                       'label' => 'Client Portal'],
                ['key' => 'projects',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>',                                                               'label' => 'Projects', 'count' => $projects->count()],
                ['key' => 'retainers',   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>', 'label' => 'Retainers', 'count' => $retainers->count()],

                ['key' => 'contents',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />', 'label' => 'Content', 'count' => $contents->count()],
                ['key' => 'invoices',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>',                                         'label' => 'Invoices'],

                ['key' => 'documents',   'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',                    'label' => 'Documents', 'count' => $attachments->count()],
                ['key' => 'activity',    'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',                                                                                         'label' => 'Activity Log'],
            ];
            @endphp

            @foreach($tabs as $tab)
            <button wire:click="$set('activeTab', '{{ $tab['key'] }}')"
                    class="pb-3.5 px-4 text-sm font-semibold border-b-2 flex items-center gap-1.5 transition-all duration-150
                           {{ $activeTab === $tab['key']
                               ? 'border-[#ea580c] text-[#ea580c]'
                               : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">{!! $tab['icon'] !!}</svg>
                {{ $tab['label'] }}
                @if(isset($tab['count']))
                    <span class="text-xs font-bold {{ $activeTab === $tab['key'] ? 'text-[#ea580c]' : 'text-gray-400' }}">{{ $tab['count'] }}</span>
                @endif
            </button>
            @endforeach
        </nav>
    </div>

    {{-- ── TAB CONTENT ─────────────────────────────────────────── --}}

    {{-- CLIENT PORTAL TAB --}}
    @if($activeTab === 'general')
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Client Profile</h4>
            <div class="space-y-3">
                <div><span class="text-xs text-gray-400 block">Full Name</span><span class="text-sm font-semibold text-gray-900">{{ $client->name }}</span></div>
                <div><span class="text-xs text-gray-400 block">Email Address</span><span class="text-sm font-semibold text-gray-900">{{ $client->email }}</span></div>
                <div><span class="text-xs text-gray-400 block">Phone Number</span><span class="text-sm font-semibold text-gray-900">{{ $client->phone ?? '—' }}</span></div>
                <div><span class="text-xs text-gray-400 block">Company Name</span><span class="text-sm font-semibold text-gray-900">{{ $client->company_name ?? '—' }}</span></div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Billing Details</h4>
            <div class="space-y-3">
                <div><span class="text-xs text-gray-400 block">Tax Number (GSTIN / VAT)</span><span class="text-sm font-semibold text-gray-900">{{ $client->tax_number ?? '—' }}</span></div>
                <div><span class="text-xs text-gray-400 block">Default Currency</span><span class="text-sm font-semibold text-gray-900">{{ $client->currency }}</span></div>
                <div><span class="text-xs text-gray-400 block">Billing Address</span><span class="text-sm font-semibold text-gray-900 whitespace-pre-line">{{ $client->address ?? '—' }}</span></div>
            </div>
        </div>
    </div>
    @endif

    {{-- PROJECTS TAB --}}
    @if($activeTab === 'projects')
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-[17px] font-bold text-gray-900">Projects</h3>
            @can('create projects')
            <a href="{{ route('projects.index') }}?client_id={{ $client->id }}" class="inline-flex items-center px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white text-[13px] font-semibold rounded-lg shadow-sm transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New Project
            </a>
            @endcan
        </div>
        
        <div class="space-y-4">
            @forelse($projects as $project)
            <div class="border border-gray-100 rounded-xl p-5 flex items-center justify-between hover:border-gray-200 transition-colors">
                <div>
                    <div class="flex items-center gap-3">
                        <h4 class="text-base font-bold text-gray-900">{{ $project->name }}</h4>
                        <span class="px-2.5 py-0.5 bg-purple-50 text-purple-600 text-[11px] font-bold rounded-full">New</span>
                    </div>
                    <div class="flex items-center gap-4 mt-2.5 text-[13px]">
                        <span class="text-gray-500">Budget: <span class="font-bold text-gray-900">{{ $currencySymbol }}{{ number_format($project->budget) }}</span></span>
                        <span class="text-gray-500">Paid: <span class="font-bold text-emerald-500">{{ $currencySymbol }}0</span></span>
                        <span class="text-gray-500">Balance: <span class="font-bold text-orange-600">-{{ $currencySymbol }}{{ number_format($project->budget) }}</span></span>
                        <span class="text-gray-400">Added {{ $project->created_at->diffForHumans() }}</span>
                    </div>
                </div>
                @can('delete projects')
                <button wire:click="deleteProject({{ $project->id }})" wire:confirm="Are you sure you want to delete this project?" class="text-red-400 hover:text-red-600 p-2 hover:bg-red-50 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                @endcan
            </div>
            @empty
            <div class="py-10 text-center text-gray-400 border border-gray-100 rounded-xl">No projects found for this client.</div>
            @endforelse
        </div>
    </div>
    @endif


    {{-- CONTENTS TAB --}}
    @if($activeTab === 'contents')
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
            <h3 class="text-lg font-medium leading-6 text-gray-900">Content</h3>
            @can('create content')
            <a href="{{ route('contents.index') }}?client={{ $client->id }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-900">Manage Content</a>
            @endcan
        </div>
        
        <div class="divide-y divide-gray-100">
            @php
                $statusColors = [
                    'idea' => 'bg-gray-100 text-gray-800',
                    'brief' => 'bg-purple-100 text-purple-800',
                    'assigned' => 'bg-indigo-100 text-indigo-800',
                    'in_progress' => 'bg-blue-100 text-blue-800',
                    'internal_review' => 'bg-yellow-100 text-yellow-800',
                    'changes_requested' => 'bg-red-100 text-red-800',
                    'client_approval' => 'bg-orange-100 text-orange-800',
                    'approved' => 'bg-green-100 text-green-800',
                    'scheduled' => 'bg-teal-100 text-teal-800',
                    'published' => 'bg-emerald-100 text-emerald-800',
                    'cancelled' => 'bg-gray-200 text-gray-600',
                ];
            @endphp
            
            @forelse($contents as $content)
                <div class="p-6 hover:bg-gray-50 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="bg-indigo-50 w-10 h-10 rounded-lg flex items-center justify-center text-indigo-600 font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        </div>
                        <div>
                            <a href="{{ route('contents.show', $content->id) }}" class="text-sm font-bold text-gray-900 hover:text-indigo-600">{{ $content->title }}</a>
                            <div class="text-xs text-gray-500 mt-0.5 flex gap-2">
                                <span>{{ $content->platform->name ?? 'Any Platform' }}</span> &bull;
                                <span>{{ $content->contentType->name ?? 'Any Type' }}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-6 text-sm">
                        <div class="text-gray-500 text-right">
                            <div class="font-medium text-gray-900">{{ $content->publish_date ? $content->publish_date->format('d M y') : '--' }}</div>
                            <div class="text-xs">Publish Date</div>
                        </div>
                        <span class="px-2.5 py-1 text-[11px] font-bold rounded-full {{ $statusColors[$content->status] ?? 'bg-gray-100 text-gray-800' }}">
                            {{ ucwords(str_replace('_', ' ', $content->status)) }}
                        </span>
                        <a href="{{ route('contents.show', $content->id) }}" class="text-indigo-600 hover:text-indigo-900 font-medium">View</a>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No content assigned</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by creating content for this client.</p>
                </div>
            @endforelse
        </div>
    </div>
    @endif

    {{-- INVOICES TAB --}}
    @if($activeTab === 'invoices')
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-50 border-b border-gray-100 text-xs font-bold uppercase tracking-wider text-gray-500">
                    <th class="px-6 py-4">Invoice #</th>
                    <th class="px-6 py-4">Due Date</th>
                    <th class="px-6 py-4">Total</th>
                    <th class="px-6 py-4">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($invoices as $invoice)
                <tr>
                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $invoice->invoice_number }}</td>
                    <td class="px-6 py-4 text-xs text-gray-500">{{ $invoice->due_date?->format('M d, Y') ?? 'N/A' }}</td>
                    <td class="px-6 py-4 font-bold text-gray-800">{{ $currencySymbol }}{{ number_format($invoice->total, 2) }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold
                            {{ $invoice->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($invoice->status === 'sent' ? 'bg-indigo-50 text-indigo-700' : 'bg-rose-50 text-rose-700') }}">
                            {{ ucfirst($invoice->status) }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-6 py-12 text-center text-gray-400">No invoices found for this client.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif



    {{-- DOCUMENTS TAB --}}
    @if($activeTab === 'documents')
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden" x-data="{ showUpload: false }">
        <div class="px-6 py-4 border-b border-gray-150 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-900">Documents & Files</h3>
            <button @click="showUpload = !showUpload; $wire.set('uploadedFile', null)" class="inline-flex items-center px-3 py-1.5 bg-[#ea580c] hover:bg-orange-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                Upload File
            </button>
        </div>

        <div x-show="showUpload" x-collapse class="px-6 py-4 bg-orange-50/30 border-b border-gray-150">
            <form wire:submit.prevent="uploadAttachment" class="space-y-3">
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <input type="file" wire:model="uploadedFile" required class="w-full px-3.5 py-2.5 border border-gray-250 bg-white rounded-xl text-sm focus:ring-orange-500 focus:border-orange-500" />
                        @error('uploadedFile') <span class="text-xs text-rose-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                    <button type="submit" class="px-4 py-2.5 bg-[#ea580c] hover:bg-orange-700 text-white rounded-xl text-sm font-semibold transition-colors shadow-sm" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="uploadAttachment">Upload</span>
                        <span wire:loading wire:target="uploadAttachment">Uploading...</span>
                    </button>
                    <button type="button" @click="showUpload = false" class="px-4 py-2.5 border border-gray-250 text-gray-600 hover:bg-gray-100 rounded-xl text-sm transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>

        <div class="divide-y divide-gray-100">
            @forelse($attachments as $media)
                <div class="px-6 py-4 flex items-center justify-between group hover:bg-gray-50/50 transition-colors duration-150">
                    <div class="flex items-center space-x-3 min-w-0">
                        <div class="w-10 h-10 flex-shrink-0 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900 truncate" title="{{ $media->name }}">{{ $media->name }}</p>
                            <div class="flex items-center space-x-2 text-xs text-gray-500 mt-0.5">
                                <span>{{ strtoupper($media->extension) }}</span>
                                <span>&bull;</span>
                                <span>{{ $media->human_readable_size }}</span>
                                <span>&bull;</span>
                                <span>{{ $media->created_at->format('M d, Y') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <a href="{{ $media->getUrl() }}" target="_blank" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Download">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                        @can('delete documents')
                        <button wire:click="deleteAttachment({{ $media->id }})" wire:confirm="Are you sure you want to delete this document?" class="p-2 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Delete">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="px-6 py-12 text-center">
                    <svg class="w-12 h-12 mx-auto stroke-1 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <p class="text-sm mt-3 text-gray-400 font-medium">No documents yet</p>
                    <p class="text-xs mt-1 text-gray-400">Upload files related to this client.</p>
                </div>
            @endforelse
        </div>
    </div>
    @endif

    {{-- ACTIVITY LOG TAB --}}
    @if($activeTab === 'activity')
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <h3 class="text-[17px] font-bold text-gray-900 mb-6">Activity History</h3>
        <div class="flow-root">
            <ul class="-mb-8">
                @forelse($activities as $activity)
                    <li>
                        <div class="relative pb-8">
                            @if(!$loop->last)
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                            @endif
                            <div class="relative flex space-x-3.5">
                                <div>
                                    <span class="h-8 w-8 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs border border-white">
                                        {{ substr($activity->user?->name ?? 'S', 0, 1) }}
                                    </span>
                                </div>
                                <div class="flex-grow min-w-0 pt-1.5 flex justify-between space-x-4">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            <span class="font-semibold text-gray-900">{{ $activity->user?->name ?? 'System' }}</span>
                                            {{ $activity->description }}
                                        </p>
                                    </div>
                                    <div class="text-right text-xs whitespace-nowrap text-gray-400">
                                        {{ $activity->created_at->diffForHumans() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                @empty
                    <div class="text-center py-8 text-gray-400">
                        <p class="text-sm">No activity recorded for this client yet.</p>
                    </div>
                @endforelse
            </ul>
        </div>
    </div>
    @endif

    <!-- Edit Client Modal -->
    <div x-show="$wire.showEditModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="$wire.showEditModal" class="fixed inset-0 transition-opacity" aria-hidden="true" @click="$wire.set('showEditModal', false)">
                <div class="absolute inset-0 bg-gray-900/20 backdrop-blur-sm"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="$wire.showEditModal" class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form wire:submit.prevent="updateClient">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4">Edit Client</h3>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="edit_name" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                                @error('edit_name') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                                <input type="text" wire:model="edit_company_name" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                                @error('edit_company_name') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                <input type="email" wire:model="edit_email" required class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                                @error('edit_email') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="text" wire:model="edit_phone" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                                @error('edit_phone') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                                <textarea wire:model="edit_address" rows="2" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm"></textarea>
                                @error('edit_address') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Tax Number (GST/VAT)</label>
                                    <input type="text" wire:model="edit_tax_number" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 text-sm">
                                    @error('edit_tax_number') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Currency</label>
                                    <x-custom-select wire:model="edit_currency" placeholder="Select Currency" class="w-full mt-0 z-40"
                                        :options="[
                                            ['id' => 'INR', 'name' => 'INR (₹)'],
                                            ['id' => 'USD', 'name' => 'USD ($)'],
                                            ['id' => 'EUR', 'name' => 'EUR (€)'],
                                            ['id' => 'GBP', 'name' => 'GBP (£)']
                                        ]" />
                                    @error('edit_currency') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-[#ea580c] text-base font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                            Save Changes
                        </button>
                        <button type="button" @click="$wire.set('showEditModal', false)" class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div x-show="$wire.showDeleteModal" class="fixed inset-0 z-[60] overflow-y-auto" style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="$wire.showDeleteModal" class="fixed inset-0 transition-opacity" aria-hidden="true" @click="$wire.set('showDeleteModal', false)">
                <div class="absolute inset-0 bg-gray-900/20 backdrop-blur-sm transition-opacity"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="$wire.showDeleteModal" class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-[480px] sm:w-full p-6">
                <div class="flex items-start gap-4 mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 flex flex-shrink-0 items-center justify-center text-rose-500">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div class="pt-2">
                        <h3 class="text-[19px] font-bold text-gray-900">Delete Client?</h3>
                    </div>
                </div>
                <div class="mt-2 mb-8 text-gray-500 text-[14px] leading-relaxed ml-[64px]">
                    Are you sure you want to delete <strong>{{ $client->name }}</strong>? This will permanently remove the client and all associated projects and invoices. This action cannot be undone.
                </div>
                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3 ml-[64px]">
                    <button type="button" wire:click="deleteClient" class="w-full inline-flex justify-center rounded-xl border border-transparent shadow-sm px-5 py-2.5 bg-[#D92D20] text-[14px] font-bold text-white hover:bg-red-700 focus:outline-none transition-colors sm:w-auto items-center">
                        Delete Client
                    </button>
                    <button type="button" @click="$wire.set('showDeleteModal', false)" class="mt-3 w-full inline-flex justify-center rounded-xl border border-gray-200 shadow-sm px-5 py-2.5 bg-white text-[14px] font-bold text-gray-700 hover:bg-gray-50 focus:outline-none transition-colors sm:mt-0 sm:w-auto">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>


</div>
