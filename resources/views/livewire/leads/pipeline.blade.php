<?php

use App\Models\Lead;
use App\Models\Client;
use App\Models\ActivityLog;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, with};

state([
    'search' => '',
    'selectedLeadId' => null,
    
    // Form fields
    'name' => '',
    'email' => '',
    'phone' => '',
    'company_name' => '',
    'value' => 0.00,
    'status' => 'NEW',
    'notes' => '',
]);

$updateLeadStatus = function ($leadId, $newStatus) {
    \Illuminate\Support\Facades\Gate::authorize('status leads');
    $lead = Lead::find($leadId);
    if ($lead) {
        $oldStatus = $lead->status;
        $lead->status = $newStatus;
        $lead->save();

        ActivityLog::create([
            'description' => "Moved lead '{$lead->name}' from {$oldStatus} to {$newStatus}",
            'subject_id' => $lead->id,
            'subject_type' => Lead::class,
        ]);

        $this->dispatch('notify', message: 'Lead status updated successfully.', type: 'success');
    }
};

$resetForm = function () {
    $this->selectedLeadId = null;
    $this->name = '';
    $this->email = '';
    $this->phone = '';
    $this->company_name = '';
    $this->value = 0.00;
    $this->status = 'NEW';
    $this->notes = '';
};

$saveLead = function () {
    if ($this->selectedLeadId) {
        \Illuminate\Support\Facades\Gate::authorize('edit leads');
    } else {
        \Illuminate\Support\Facades\Gate::authorize('create leads');
    }

    $this->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'nullable|string|max:50',
        'company_name' => 'nullable|string|max:255',
        'value' => 'required|numeric|min:0',
        'status' => 'required|string|in:NEW,CONTACTED,PROPOSAL_SENT,NEGOTIATION,WON,LOST',
        'notes' => 'nullable|string',
    ]);

    if ($this->selectedLeadId) {
        $lead = Lead::find($this->selectedLeadId);
        if ($lead) {
            $lead->update([
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'company_name' => $this->company_name,
                'value' => $this->value,
                'status' => $this->status,
                'notes' => $this->notes,
            ]);

            ActivityLog::create([
                'description' => "Updated lead '{$lead->name}' details",
                'subject_id' => $lead->id,
                'subject_type' => Lead::class,
            ]);

            $this->dispatch('notify', message: 'Lead updated successfully.', type: 'success');
        }
    } else {
        $lead = Lead::create([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company_name' => $this->company_name,
            'value' => $this->value,
            'status' => $this->status,
            'notes' => $this->notes,
        ]);

        ActivityLog::create([
            'description' => "Created lead '{$lead->name}'",
            'subject_id' => $lead->id,
            'subject_type' => Lead::class,
        ]);

        $this->dispatch('notify', message: 'Lead created successfully.', type: 'success');
    }

    $this->resetForm();
    $this->dispatch('close-modal');
};

$editLead = function ($leadId) {
    \Illuminate\Support\Facades\Gate::authorize('edit leads');
    $lead = Lead::find($leadId);
    if ($lead) {
        $this->selectedLeadId = $lead->id;
        $this->name = $lead->name;
        $this->email = $lead->email;
        $this->phone = $lead->phone;
        $this->company_name = $lead->company_name;
        $this->value = $lead->value;
        $this->status = $lead->status;
        $this->notes = $lead->notes;

        $this->dispatch('open-edit-modal');
    }
};

$deleteLead = function ($leadId) {
    \Illuminate\Support\Facades\Gate::authorize('delete leads');
    $lead = Lead::find($leadId);
    if ($lead) {
        $name = $lead->name;
        $lead->delete();

        ActivityLog::create([
            'description' => "Deleted lead '{$name}'",
            'subject_id' => $leadId,
            'subject_type' => Lead::class,
        ]);

        $this->dispatch('notify', message: 'Lead deleted successfully.', type: 'success');
    }
};

$convertToClient = function ($leadId) {
    \Illuminate\Support\Facades\Gate::authorize('create clients');
    $lead = Lead::find($leadId);
    if ($lead) {
        // Create Client
        $client = Client::create([
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'company_name' => $lead->company_name,
            'status' => 'active',
            'currency' => Auth::user()->currentBusiness?->currency ?? 'INR',
        ]);

        // Mark lead as WON
        $lead->status = 'WON';
        $lead->save();

        ActivityLog::create([
            'description' => "Converted lead '{$lead->name}' to client '{$client->name}'",
            'subject_id' => $client->id,
            'subject_type' => Client::class,
        ]);

        $this->dispatch('notify', message: "Successfully converted '{$lead->name}' to Client!", type: 'success');
    }
};

with(function () {
    $business = Auth::user()?->currentBusiness;
    $currencySymbol = ($business?->currency ?? 'INR') === 'USD' ? '$' : '₹';

    $leadsQuery = Lead::query();
    
    if ($this->search) {
        $leadsQuery->where(function ($q) {
            $q->where('name', 'like', "%{$this->search}%")
              ->orWhere('company_name', 'like', "%{$this->search}%")
              ->orWhere('email', 'like', "%{$this->search}%");
        });
    }

    $leads = $leadsQuery->get();

    $stages = [
        'NEW' => [
            'title' => 'New Leads',
            'color' => 'bg-indigo-500',
            'border' => 'border-indigo-100 dark:border-indigo-950',
            'text' => 'text-indigo-600 dark:text-indigo-400',
            'bg' => 'bg-indigo-50/20 dark:bg-indigo-950/10',
            'items' => $leads->where('status', 'NEW')
        ],
        'CONTACTED' => [
            'title' => 'Contacted',
            'color' => 'bg-cyan-500',
            'border' => 'border-cyan-100 dark:border-cyan-950',
            'text' => 'text-cyan-600 dark:text-cyan-400',
            'bg' => 'bg-cyan-50/20 dark:bg-cyan-950/10',
            'items' => $leads->where('status', 'CONTACTED')
        ],
        'PROPOSAL_SENT' => [
            'title' => 'Proposal Sent',
            'color' => 'bg-amber-500',
            'border' => 'border-amber-100 dark:border-amber-950',
            'text' => 'text-amber-600 dark:text-amber-400',
            'bg' => 'bg-amber-50/20 dark:bg-amber-950/10',
            'items' => $leads->where('status', 'PROPOSAL_SENT')
        ],
        'NEGOTIATION' => [
            'title' => 'Negotiation',
            'color' => 'bg-purple-500',
            'border' => 'border-purple-100 dark:border-purple-950',
            'text' => 'text-purple-600 dark:text-purple-400',
            'bg' => 'bg-purple-50/20 dark:bg-purple-950/10',
            'items' => $leads->where('status', 'NEGOTIATION')
        ],
        'WON' => [
            'title' => 'Won',
            'color' => 'bg-emerald-500',
            'border' => 'border-emerald-100 dark:border-emerald-950',
            'text' => 'text-emerald-600 dark:text-emerald-400',
            'bg' => 'bg-emerald-50/20 dark:bg-emerald-950/10',
            'items' => $leads->where('status', 'WON')
        ],
        'LOST' => [
            'title' => 'Lost',
            'color' => 'bg-rose-500',
            'border' => 'border-rose-100 dark:border-rose-950',
            'text' => 'text-rose-600 dark:text-rose-400',
            'bg' => 'bg-rose-50/20 dark:bg-rose-950/10',
            'items' => $leads->where('status', 'LOST')
        ],
    ];

    return [
        'stages' => $stages,
        'currencySymbol' => $currencySymbol,
    ];
});

?>

<div x-data="{ openModal: false, isEdit: false }" 
     @open-edit-modal.window="openModal = true; isEdit = true"
     @close-modal.window="openModal = false; isEdit = false">
    
    <!-- Top Action Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <!-- Search bar -->
        <div class="relative max-w-md w-full">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </span>
            <input type="text" 
                   wire:model.live="search" 
                   placeholder="Search leads by name, email or company..." 
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white transition-all duration-200" />
        </div>

        <!-- Add Lead Button -->
        @can('create leads')
        <button @click="openModal = true; isEdit = false; $wire.resetForm()" 
                class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-650 text-white text-sm font-semibold rounded-xl transition-all duration-150 shadow-md flex-shrink-0">
            <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Add Lead
        </button>
        @endcan
    </div>

    <!-- Alert Messages -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/40 text-sm flex items-center shadow-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            <svg class="w-5 h-5 mr-2.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Kanban Board Container -->
    <div class="flex space-x-6 overflow-x-auto pb-6 h-[calc(100vh-220px)] min-h-[500px]" style="scrollbar-width: thin;">
        @foreach($stages as $stageKey => $stage)
            <!-- Column -->
            <div class="flex-shrink-0 w-80 flex flex-col rounded-2xl {{ $stage['bg'] }} border border-dashed {{ $stage['border'] }} p-4 h-full"
                 @dragover.prevent=""
                 @drop="const id = event.dataTransfer.getData('text/plain'); $wire.updateLeadStatus(id, '{{ $stageKey }}')">
                
                <!-- Column Header -->
                <div class="flex items-center justify-between mb-4 flex-shrink-0">
                    <div class="flex items-center space-x-2">
                        <span class="w-2.5 h-2.5 rounded-full {{ $stage['color'] }}"></span>
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white">{{ $stage['title'] }}</h3>
                    </div>
                    <span class="px-2 py-0.5 rounded-md bg-white dark:bg-gray-800 text-[11px] font-bold text-gray-500 border border-gray-100 dark:border-gray-700 shadow-sm">
                        {{ $stage['items']->count() }}
                    </span>
                </div>

                <!-- Cards Wrapper -->
                <div class="flex-1 overflow-y-auto space-y-3.5 pr-1" style="scrollbar-width: none;">
                    @forelse($stage['items'] as $lead)
                        <!-- Lead Card -->
                        <div draggable="true" 
                             @dragstart="event.dataTransfer.setData('text/plain', {{ $lead->id }})"
                             class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-150 dark:border-gray-750 shadow-sm hover:shadow-md cursor-grab active:cursor-grabbing hover:border-gray-300 dark:hover:border-gray-700 transition-all duration-150 relative group">
                            
                            <!-- Deal Value Badging -->
                            <div class="flex justify-between items-start mb-2">
                                <span class="text-[10px] font-bold tracking-wider uppercase text-gray-400 dark:text-gray-500">
                                    {{ $lead->company_name ?? 'Individual' }}
                                </span>
                                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                    {{ $currencySymbol }}{{ number_format($lead->value, 2) }}
                                </span>
                            </div>

                            <!-- Lead Name -->
                            <h4 class="text-sm font-bold text-gray-900 dark:text-white leading-snug">{{ $lead->name }}</h4>
                            
                            <!-- Details -->
                            <div class="mt-2 space-y-1 text-gray-500 dark:text-gray-400 text-[11px]">
                                <div class="flex items-center truncate">
                                    <svg class="w-3.5 h-3.5 mr-1.5 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    <span class="truncate">{{ $lead->email }}</span>
                                </div>
                                @if($lead->phone)
                                    <div class="flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1.5 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <span>{{ $lead->phone }}</span>
                                    </div>
                                @endif
                            </div>

                            <!-- Action bar appearing on Hover -->
                            <div class="mt-4 pt-3.5 border-t border-gray-150 dark:border-gray-700/60 flex items-center justify-between opacity-0 group-hover:opacity-100 transition-opacity duration-150">
                                <div class="flex items-center justify-end space-x-2">
                                    @can('edit leads')
                                    <button wire:click="editLead({{ $lead->id }})" 
                                            class="p-1.5 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                            title="Edit Lead">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    @endcan
                                    
                                    @can('delete leads')
                                    <x-confirm-action action="deleteLead({{ $lead->id }})" title="Delete Lead" message="Are you sure you want to delete this lead?" buttonText="Delete">
                                        <x-slot:trigger>
                                            <button type="button" class="p-1.5 text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 rounded-lg transition-colors" title="Delete Lead">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </x-slot:trigger>
                                    </x-confirm-action>
                                    @endcan

                                    @can('create clients')
                                    @if($lead->status !== 'WON')
                                    <button wire:click="convertToClient({{ $lead->id }})" 
                                            class="ml-auto inline-flex items-center px-2 py-1 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-400 dark:hover:bg-emerald-900/40 rounded-lg transition-colors"
                                            title="Convert to Client">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        Convert
                                    </button>
                                    @endif
                                    @endcan
                                </div>
                                @if($lead->status === 'WON')
                                    <span class="inline-flex items-center text-[10px] font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/25 px-2 py-0.5 rounded-md border border-emerald-100/10">
                                        Client Active
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 text-gray-400 dark:text-gray-500 border border-dashed border-gray-200 dark:border-gray-800 rounded-xl bg-white/40 dark:bg-gray-850/40">
                            <span class="text-xs">No leads</span>
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <!-- Create/Edit Modal Backed by Alpine.js -->
    <div x-show="openModal" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-gray-650 bg-opacity-75"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">
        
        <!-- Modal Content Container -->
        <div class="bg-white dark:bg-gray-850 rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100 dark:border-gray-800"
             @click.away="openModal = false">
            
            <div class="flex items-center justify-between mb-5 border-b border-gray-100 dark:border-gray-800 pb-3 flex-shrink-0">
                <h3 class="text-base font-bold text-gray-900 dark:text-white" x-text="isEdit ? 'Edit Lead Details' : 'Add New CRM Lead'"></h3>
                <button @click="openModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-white">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Form -->
            <form wire:submit.prevent="saveLead" class="space-y-4">
                <!-- Name -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Lead Name *</label>
                    <input type="text" 
                           wire:model="name" 
                           required
                           class="w-full px-3.5 py-2 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white" />
                    @error('name') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Email *</label>
                    <input type="email" 
                           wire:model="email" 
                           required
                           class="w-full px-3.5 py-2 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white" />
                    @error('email') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Phone -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Phone Number</label>
                    <input type="text" 
                           wire:model="phone" 
                           class="w-full px-3.5 py-2 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white" />
                    @error('phone') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Company Name -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Company Name</label>
                    <input type="text" 
                           wire:model="company_name" 
                           class="w-full px-3.5 py-2 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white" />
                    @error('company_name') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Deal Value & Stage Status -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Deal Value ({{ $currencySymbol }}) *</label>
                        <input type="number" 
                               step="0.01"
                               wire:model="value" 
                               required
                               class="w-full px-3.5 py-2 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white" />
                        @error('value') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Pipeline Stage *</label>
                        <x-custom-select wire:model="status" placeholder="Select stage"
                            :options="[
                                ['id' => 'NEW', 'name' => 'New'],
                                ['id' => 'CONTACTED', 'name' => 'Contacted'],
                                ['id' => 'PROPOSAL_SENT', 'name' => 'Proposal Sent'],
                                ['id' => 'NEGOTIATION', 'name' => 'Negotiation'],
                                ['id' => 'WON', 'name' => 'Won'],
                                ['id' => 'LOST', 'name' => 'Lost']
                            ]" />
                        @error('status') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Notes</label>
                    <textarea wire:model="notes" 
                              rows="3"
                              class="w-full px-3.5 py-2 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white"></textarea>
                    @error('notes') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Footer buttons -->
                <div class="pt-4 flex items-center justify-end space-x-3.5 border-t border-gray-100 dark:border-gray-800 mt-4 flex-shrink-0">
                    <button type="button" 
                            @click="openModal = false" 
                            class="px-4 py-2 border border-gray-250 dark:border-gray-750 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl text-sm transition-colors duration-150">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-650 text-white rounded-xl text-sm font-semibold transition-all duration-150 shadow-md">
                        Save Lead
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
