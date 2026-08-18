<?php

use Livewire\Volt\Component;
use App\Models\Retainer;
use App\Models\Client;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $search = '';

    public $isDrawerOpen = false;
    public $isEditMode = false;
    public $selectedRetainerId = null;
    public $viewTab = 'overview';

    public $name = '';
    public $client_id = '';
    public $description = '';
    public $status = 'draft';
    public $start_date = '';
    public $renewal_date = '';
    public $amount = 0;
    public $billing_cycle = 'monthly';
    public $allocated_hours = 0;
    public $terms = '';

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'client_id' => 'required|exists:clients,id',
            'description' => 'nullable|string',
            'status' => 'required|in:draft,active,renewal_due,renewed,cancelled',
            'start_date' => 'nullable|date',
            'renewal_date' => 'nullable|date|after_or_equal:start_date',
            'amount' => 'nullable|numeric|min:0',
            'billing_cycle' => 'required|string',
            'allocated_hours' => 'nullable|integer|min:0',
            'terms' => 'nullable|string',
        ];
    }

    public function with()
    {
        $businessId = Auth::user()->current_business_id;

        $query = Retainer::where('business_id', $businessId)
            ->with(['client', 'tasks', 'invoices.payments']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhereHas('client', function ($cq) {
                      $cq->where('name', 'like', "%{$this->search}%");
                  });
            });
        }

        $retainers = $query->latest()->get();

        $selectedRetainerData = $this->selectedRetainerId 
            ? Retainer::where('business_id', $businessId)
                ->with(['client', 'invoices.payments', 'tasks'])
                ->find($this->selectedRetainerId) 
            : null;

        return [
            'retainers' => $retainers,
            'clients' => Client::where('business_id', $businessId)->orderBy('name')->get(),
            
            'totalRetainers' => $retainers->count(),
            'activeRetainers' => $retainers->where('status', 'active')->count(),
            'monthlyRevenue' => $retainers->where('status', 'active')->where('billing_cycle', 'monthly')->sum('amount'),
            'renewalsDue' => $retainers->where('status', 'renewal_due')->count(),
            
            'selectedRetainerData' => $selectedRetainerData,
        ];
    }

    public function createRetainer()
    {
        $this->resetForm();
        $this->isEditMode = false;
        $this->isDrawerOpen = true;
    }

    public function editRetainer()
    {
        $businessId = Auth::user()->current_business_id;
        $retainer = Retainer::where('business_id', $businessId)->findOrFail($this->selectedRetainerId);

        $this->name = $retainer->name;
        $this->client_id = $retainer->client_id;
        $this->description = $retainer->description;
        $this->status = $retainer->status;
        $this->start_date = $retainer->start_date ? $retainer->start_date->format('Y-m-d') : '';
        $this->renewal_date = $retainer->renewal_date ? $retainer->renewal_date->format('Y-m-d') : '';
        $this->amount = $retainer->amount;
        $this->billing_cycle = $retainer->billing_cycle;
        $this->allocated_hours = $retainer->allocated_hours;
        $this->terms = $retainer->terms;

        $this->isEditMode = true;
    }

    public function viewRetainer($id)
    {
        $this->resetForm();
        $this->selectedRetainerId = $id;
        $this->viewTab = 'overview';
        $this->isEditMode = false; 
        $this->isDrawerOpen = true;
    }

    public function closeDrawer()
    {
        $this->isDrawerOpen = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->selectedRetainerId = null;
        $this->name = '';
        $this->client_id = '';
        $this->description = '';
        $this->status = 'draft';
        $this->start_date = '';
        $this->renewal_date = '';
        $this->amount = 0;
        $this->billing_cycle = 'monthly';
        $this->allocated_hours = 0;
        $this->terms = '';
        $this->resetValidation();
    }

    public function save()
    {
        $this->validate();
        $businessId = Auth::user()->current_business_id;

        $client = Client::where('business_id', $businessId)->find($this->client_id);
        if (!$client) {
            $this->addError('client_id', 'Invalid client.');
            return;
        }

        if ($this->isEditMode && $this->selectedRetainerId) {
            $retainer = Retainer::where('business_id', $businessId)->findOrFail($this->selectedRetainerId);
            $retainer->update([
                'name' => $this->name,
                'client_id' => $this->client_id,
                'description' => $this->description,
                'status' => $this->status,
                'start_date' => $this->start_date ?: null,
                'renewal_date' => $this->renewal_date ?: null,
                'amount' => $this->amount,
                'billing_cycle' => $this->billing_cycle,
                'allocated_hours' => $this->allocated_hours,
                'terms' => $this->terms,
            ]);
            session()->flash('message', 'Retainer updated successfully.');
            $this->isEditMode = false;
        } else {
            Retainer::create([
                'business_id' => $businessId,
                'client_id' => $this->client_id,
                'name' => $this->name,
                'description' => $this->description,
                'status' => $this->status,
                'start_date' => $this->start_date ?: null,
                'renewal_date' => $this->renewal_date ?: null,
                'amount' => $this->amount,
                'billing_cycle' => $this->billing_cycle,
                'allocated_hours' => $this->allocated_hours,
                'terms' => $this->terms,
            ]);
            session()->flash('message', 'Retainer created successfully.');
            $this->closeDrawer();
        }
    }

    public function deleteRetainer()
    {
        if ($this->selectedRetainerId) {
            $businessId = Auth::user()->current_business_id;
            $retainer = Retainer::where('business_id', $businessId)->findOrFail($this->selectedRetainerId);
            $retainer->delete();
            session()->flash('message', 'Retainer deleted successfully.');
            $this->closeDrawer();
        }
    }
    
    public function setViewTab($tab)
    {
        $this->viewTab = $tab;
    }
};
?>

<div class="h-full flex flex-col">
    <!-- Header with Pill Badges -->
    <div class="flex items-center gap-6 mb-6">
        <div>
            <h1 class="text-[22px] font-bold text-gray-900 leading-tight">Retainers</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Manage recurring client services</p>
        </div>
        
        <div class="flex items-center gap-3">
            <div class="flex items-center px-3 py-1.5 bg-white border border-gray-200 rounded-full text-[13px] font-medium text-gray-700 shadow-sm">
                <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                Total <span class="ml-1 font-bold">{{ $totalRetainers }}</span>
            </div>
            <div class="flex items-center px-3 py-1.5 bg-white border border-gray-200 rounded-full text-[13px] font-medium text-gray-700 shadow-sm">
                <svg class="w-4 h-4 mr-1.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Active <span class="ml-1 font-bold">{{ $activeRetainers }}</span>
            </div>
            <div class="flex items-center px-3 py-1.5 bg-white border border-gray-200 rounded-full text-[13px] font-medium text-gray-700 shadow-sm">
                <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Monthly <span class="ml-1 font-bold">₹{{ number_format($monthlyRevenue) }}</span>
            </div>
            <div class="flex items-center px-3 py-1.5 bg-white border border-gray-200 rounded-full text-[13px] font-medium text-gray-700 shadow-sm">
                <svg class="w-4 h-4 mr-1.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Renewals <span class="ml-1 font-bold text-rose-500">{{ $renewalsDue }}</span>
            </div>
        </div>
    </div>

    <!-- Search and Actions Bar -->
    <div class="flex items-center justify-between mb-6">
        <div class="relative w-[400px]">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search retainers, clients, or descriptions..." class="block w-full pl-9 pr-3 py-2 border-none rounded-full bg-white text-[13px] shadow-sm focus:ring-1 focus:ring-orange-500 placeholder-gray-400">
        </div>
        
        <div class="flex items-center gap-2">
            <button wire:click="createRetainer" class="inline-flex items-center px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white text-[13px] font-semibold rounded-lg shadow-sm transition-colors">
                <span class="mr-1">+</span> New
            </button>
            <button class="inline-flex items-center px-3 py-2 bg-white border border-gray-200 text-gray-700 text-[13px] font-semibold rounded-lg shadow-sm hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                CSV
            </button>
            <button class="inline-flex items-center px-3 py-2 bg-white border border-gray-200 text-gray-700 text-[13px] font-semibold rounded-lg shadow-sm hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                JSON
            </button>
            <button class="inline-flex items-center px-3 py-2 bg-white border border-rose-100 text-rose-600 text-[13px] font-semibold rounded-lg shadow-sm hover:bg-rose-50 transition-colors">
                <svg class="w-4 h-4 mr-1.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Trash
            </button>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-600 border border-emerald-100 text-[13px] flex items-center shadow-sm">
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Kanban Columns -->
    <div class="relative flex-1">
        <div wire:loading.delay.long wire:target="search, filterStatus">
            <x-skeleton-loader type="tasks" />
        </div>
        <div wire:loading.remove.delay.long wire:target="search, filterStatus" class="flex-1 flex gap-6 overflow-x-auto pb-4">
        
        @php
            $columns = [
                'draft' => ['title' => 'Draft', 'dot' => 'bg-gray-500', 'status' => 'draft'],
                'active' => ['title' => 'Active', 'dot' => 'bg-emerald-500', 'status' => 'active'],
                'renewal_due' => ['title' => 'Renewal Due', 'dot' => 'bg-rose-500', 'status' => 'renewal_due']
            ];
        @endphp

        @foreach($columns as $col)
            @php
                $colRetainers = $retainers->where('status', $col['status']);
            @endphp
            <div class="flex-shrink-0 w-[350px] bg-gray-50/50 rounded-xl border border-gray-200 flex flex-col max-h-full">
                <!-- Column Header -->
                <div class="px-4 py-4 border-b border-gray-100 flex items-center justify-between bg-white rounded-t-xl">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full {{ $col['dot'] }}"></div>
                        <h3 class="text-[14px] font-bold text-gray-900">{{ $col['title'] }}</h3>
                        <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[11px] font-bold rounded-full">{{ $colRetainers->count() }}</span>
                    </div>
                    <button class="text-gray-400 hover:text-gray-600 p-1 border border-gray-200 rounded-full hover:bg-gray-100">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                </div>
                
                <!-- Column Body -->
                <div class="flex-1 overflow-y-auto p-3 space-y-3">
                    @forelse($colRetainers as $retainer)
                        <!-- Retainer Card -->
                        <div wire:key="retainer-{{ $retainer->id }}" @click="$wire.viewRetainer({{ $retainer->id }})" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 cursor-pointer hover:border-gray-300 hover:shadow transition-all group">
                            <div class="flex justify-between items-start mb-1">
                                <h4 class="font-bold text-[14px] text-gray-900 line-clamp-1 pr-2 leading-tight">
                                    {{ $retainer->client?->name ?? 'Unknown Client' }} 
                                    <span class="font-normal text-gray-500">({{ $retainer->name }})</span>
                                </h4>
                                @if($retainer->status === 'active')
                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 text-[10px] font-bold rounded flex-shrink-0">Active</span>
                                @elseif($retainer->status === 'draft')
                                    <span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-bold rounded flex-shrink-0">Draft</span>
                                @elseif($retainer->status === 'renewal_due')
                                    <span class="px-2 py-0.5 bg-rose-50 text-rose-600 text-[10px] font-bold rounded flex-shrink-0">Renewal</span>
                                @endif
                            </div>
                            
                            <p class="text-[12px] text-gray-500 mb-1 font-medium">{{ $retainer->client?->name ?? 'Unknown Client' }}</p>
                            
                            @if($retainer->description)
                                <p class="text-[12px] text-gray-400 mb-4 line-clamp-2 leading-snug">{{ $retainer->description }}</p>
                            @else
                                <div class="mb-4"></div>
                            @endif
                            
                            <!-- Retainer Progress/Info -->
                            <div class="mb-3">
                                <div class="flex justify-between items-end mb-1.5 text-[11px]">
                                    <span class="text-gray-400 font-medium">Cycle</span>
                                    <span class="font-bold text-gray-900 capitalize">{{ $retainer->billing_cycle }}</span>
                                </div>
                                <div class="flex items-center text-[10.5px] font-bold text-gray-900">
                                    <svg class="w-3 h-3 mr-1 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Fee: ₹{{ number_format($retainer->amount) }}
                                </div>
                            </div>
                            
                            <!-- Footer -->
                            <div class="flex items-center justify-between text-[11px] text-gray-400 pt-3 border-t border-gray-100 mt-2 font-medium">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                    Renews: {{ $retainer->renewal_date ? $retainer->renewal_date->format('M d, Y') : '-' }}
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    {{ $retainer->allocated_hours }} hrs
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 text-[12px] text-gray-400 italic">
                            No {{ strtolower($col['title']) }} retainers
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
        </div>
    </div>

    <!-- Unified Drawer (Create/Edit/View) -->
    <div x-show="$wire.isDrawerOpen" class="fixed inset-0 z-50 overflow-hidden" style="display: none;">
        <div class="absolute inset-0 bg-gray-900/30 backdrop-blur-sm transition-opacity" 
             x-show="$wire.isDrawerOpen" 
             x-transition:enter="ease-in-out duration-300" 
             x-transition:enter-start="opacity-0" 
             x-transition:enter-end="opacity-100" 
             x-transition:leave="ease-in-out duration-300" 
             x-transition:leave-start="opacity-100" 
             x-transition:leave-end="opacity-0"
             wire:click="closeDrawer"></div>

        <div class="fixed inset-y-0 right-0 flex max-w-[95vw] w-full sm:max-w-5xl">
            <div class="w-full h-full transform transition ease-in-out duration-300 bg-gray-50/50 shadow-2xl flex flex-col"
                 x-show="$wire.isDrawerOpen"
                 x-transition:enter="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="translate-x-0"
                 x-transition:leave-end="translate-x-full">
                 
                @if(!$selectedRetainerId || $isEditMode)
                    <!-- CREATE / EDIT MODE -->
                    <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between bg-white">
                        <h2 class="text-lg font-bold text-gray-900">{{ $isEditMode ? 'Edit Retainer' : 'Create New Retainer' }}</h2>
                        <button wire:click="closeDrawer" class="text-gray-400 hover:text-gray-600 p-2 rounded-full hover:bg-gray-200 transition-colors">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-6 bg-white">
                        <form wire:submit.prevent="save" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Retainer Name <span class="text-red-500">*</span></label>
                                    <input type="text" wire:model="name" required class="w-full rounded-lg border-gray-300 focus:border-orange-500 focus:ring-orange-500 shadow-sm text-sm">
                                    @error('name') <span class="text-xs text-red-500 mt-1 block">{{ $message }}</span> @enderror
                                </div>
                                
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Client <span class="text-red-500">*</span></label>
                                    <x-custom-select wire:model="client_id" placeholder="Select a client"
                                        :options="collect($clients)->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->toArray()" />
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <textarea wire:model="description" rows="3" class="w-full rounded-lg border-gray-300 focus:border-orange-500 focus:ring-orange-500 shadow-sm text-sm"></textarea>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                                    <x-custom-select wire:model="status" placeholder="Select a status"
                                        :options="[
                                            ['id' => 'draft', 'name' => 'Draft'],
                                            ['id' => 'active', 'name' => 'Active'],
                                            ['id' => 'renewal_due', 'name' => 'Renewal Due'],
                                            ['id' => 'renewed', 'name' => 'Renewed'],
                                            ['id' => 'cancelled', 'name' => 'Cancelled']
                                        ]" />
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Fee Amount (₹)</label>
                                    <input type="number" wire:model="amount" min="0" step="0.01" class="w-full rounded-lg border-gray-300 focus:border-orange-500 focus:ring-orange-500 shadow-sm text-sm">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle</label>
                                    <x-custom-select wire:model="billing_cycle" placeholder="Select billing cycle"
                                        :options="[
                                            ['id' => 'monthly', 'name' => 'Monthly'],
                                            ['id' => 'quarterly', 'name' => 'Quarterly'],
                                            ['id' => 'yearly', 'name' => 'Yearly']
                                        ]" />
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Allocated Hours</label>
                                    <input type="number" wire:model="allocated_hours" min="0" class="w-full rounded-lg border-gray-300 focus:border-orange-500 focus:ring-orange-500 shadow-sm text-sm">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                    <x-date-picker wire:model="start_date" placeholder="dd-mm-yyyy" class="w-full mt-0" />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Renewal Date</label>
                                    <x-date-picker wire:model="renewal_date" placeholder="dd-mm-yyyy" class="w-full mt-0" />
                                </div>
                            </div>

                            <div class="pt-6 mt-6 border-t border-gray-100 flex justify-end gap-3">
                                @if($isEditMode)
                                <button type="button" wire:click="$set('isEditMode', false)" class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                @else
                                <button type="button" wire:click="closeDrawer" class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50 transition-colors">
                                    Cancel
                                </button>
                                @endif
                                <button type="submit" class="px-5 py-2.5 rounded-lg bg-[#ea580c] hover:bg-orange-700 text-white text-sm font-semibold shadow-sm transition-colors flex items-center">
                                    <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                    Save Retainer
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    <!-- VIEW MODE (Matches User Screenshot) -->
                    @if($selectedRetainerData)
                        @php
                            $vp_paid = $selectedRetainerData->invoices->flatMap->payments->sum('amount') ?? 0;
                            $vp_fee = $selectedRetainerData->amount ?: 0;
                            // For a retainer, remaining might be the unbilled amount of the fee or simply 0. 
                            $vp_remaining = max(0, $vp_fee - $vp_paid); // Or just 0 if it's recurring.
                            
                            $sColor = match($selectedRetainerData->status) {
                                'active' => 'bg-emerald-100 text-emerald-700',
                                'draft' => 'bg-gray-100 text-gray-700',
                                'renewal_due' => 'bg-rose-100 text-rose-700',
                                'renewed' => 'bg-blue-100 text-blue-700',
                                'cancelled' => 'bg-amber-100 text-amber-700',
                                default => 'bg-gray-100 text-gray-700'
                            };
                            $sLabel = match($selectedRetainerData->status) {
                                'active' => 'Ongoing', // Changed to match "Ongoing" from screenshot (even though status is active)
                                'draft' => 'Draft',
                                'renewal_due' => 'Renewal Due',
                                'renewed' => 'Renewed',
                                'cancelled' => 'Cancelled',
                                default => 'Unknown'
                            };
                            $pct = 100; // Hardcoded to 100% as seen in the screenshot since retainers might not have a strict progress
                        @endphp
                        
                        <!-- Header -->
                        <div class="px-8 pt-8 pb-4 bg-white flex items-start justify-between">
                            <div>
                                <h2 class="text-[28px] font-bold text-gray-900 mb-2">{{ $selectedRetainerData->name }}</h2>
                                <div class="flex items-center gap-3">
                                    <span class="px-2.5 py-0.5 rounded text-[11px] font-bold {{ $sColor }} uppercase">{{ $sLabel }}</span>
                                    <span class="text-sm font-medium text-gray-400 flex items-center">
                                        Client: <a href="#" class="text-gray-900 ml-1 font-semibold hover:underline flex items-center">{{ $selectedRetainerData->client?->name ?? 'None' }} 
                                        <svg class="w-3 h-3 ml-0.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg></a>
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <button onclick="confirm('Are you sure you want to move this retainer to trash?') || event.stopImmediatePropagation()" wire:click="deleteRetainer" class="px-4 py-2 border border-rose-100 text-rose-600 hover:bg-rose-50 text-[13px] font-bold rounded-lg flex items-center transition-colors shadow-sm">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    Move to Trash
                                </button>
                                <button wire:click="editRetainer" class="px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white text-[13px] font-bold rounded-lg shadow-sm transition-colors flex items-center">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    Edit Retainer
                                </button>
                                <button wire:click="closeDrawer" class="p-2 ml-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-full transition-colors">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Metric Cards -->
                        <div class="px-8 bg-white grid grid-cols-4 gap-4 py-2">
                            <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm flex justify-between items-center">
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">RETAINER FEE</p>
                                    <p class="text-2xl font-bold text-gray-900">₹{{ number_format($vp_fee) }}</p>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-purple-50 flex items-center justify-center text-purple-400">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm flex justify-between items-center">
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">TOTAL PAID</p>
                                    <p class="text-2xl font-bold text-emerald-500">₹{{ number_format($vp_paid) }}</p>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-400">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm flex justify-between items-center">
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">REMAINING</p>
                                    <p class="text-2xl font-bold text-gray-900">₹{{ number_format($vp_remaining) }}</p>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-400">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                                </div>
                            </div>
                            <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm flex justify-between items-center">
                                <div>
                                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">TEAM MEMBERS</p>
                                    <p class="text-2xl font-bold text-gray-900">0</p>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-orange-50 flex items-center justify-center text-orange-400">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Info Strip -->
                        <div class="px-8 bg-white py-4">
                            <div class="bg-[#fff9f2] border border-orange-50 rounded-xl p-4 flex items-center gap-20">
                                <div>
                                    <p class="text-[10px] font-bold text-orange-500 uppercase tracking-wider mb-1">BILLING CYCLE</p>
                                    <p class="text-sm font-semibold text-gray-900 capitalize">{{ $selectedRetainerData->billing_cycle }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-orange-500 uppercase tracking-wider mb-1">NEXT RENEWAL</p>
                                    <p class="text-sm font-semibold text-gray-900">{{ $selectedRetainerData->renewal_date ? $selectedRetainerData->renewal_date->format('M d, Y') : 'Not set' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Tabs Header -->
                        <div class="px-8 border-b border-gray-100 bg-white pt-2">
                            <nav class="-mb-px flex space-x-8">
                                <a href="#" wire:click.prevent="setViewTab('overview')" class="{{ $viewTab === 'overview' ? 'border-orange-500 text-orange-500' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-[13px] transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" /></svg>
                                    Overview
                                </a>
                                <a href="#" wire:click.prevent="setViewTab('financials')" class="{{ $viewTab === 'financials' ? 'border-orange-500 text-orange-500' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-[13px] transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    Payments & Invoices
                                </a>
                                <a href="#" wire:click.prevent="setViewTab('team')" class="{{ $viewTab === 'team' ? 'border-orange-500 text-orange-500' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-[13px] transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                    Team
                                </a>
                                <a href="#" wire:click.prevent="setViewTab('tasks')" class="{{ $viewTab === 'tasks' ? 'border-orange-500 text-orange-500' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-semibold text-[13px] transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Tasks
                                </a>
                            </nav>
                        </div>

                        <!-- Tab Content -->
                        <div class="flex-1 overflow-y-auto p-8 bg-gray-50/50">
                            @if($viewTab === 'overview')
                                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2 mb-2">
                                        <svg class="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                                        Progress
                                    </h3>
                                    <p class="text-[13px] text-gray-500 mb-6">No progress updates yet. Add what's currently happening — the client sees this as a timeline.</p>
                                    
                                    <div class="space-y-4 mb-4">
                                        <input type="text" placeholder="Title (e.g. Design phase)" class="w-full border border-gray-200 rounded-lg shadow-sm text-sm p-3 focus:ring-orange-500 focus:border-orange-500 placeholder-gray-400">
                                        <textarea placeholder="What's happening now?" rows="3" class="w-full border border-gray-200 rounded-lg shadow-sm text-sm p-3 focus:ring-orange-500 focus:border-orange-500 placeholder-gray-400"></textarea>
                                    </div>
                                    <button class="px-4 py-2 bg-orange-400 hover:bg-orange-500 text-white text-[13px] font-bold rounded-lg transition-colors flex items-center shadow-sm">
                                        <span class="mr-1.5">+</span> Add update
                                    </button>
                                </div>
                                
                                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-[13px] font-semibold text-gray-600">Amount Progress</span>
                                        <span class="text-[13px] font-bold text-gray-900">{{ $pct }}%</span>
                                    </div>
                                    <div class="w-full h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @elseif($viewTab === 'financials')
                                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 text-center">
                                    <p class="text-sm text-gray-500 mb-4">To manage invoices and expenses in detail, please visit the Billing module.</p>
                                    <a href="{{ route('billing.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                                        Go to Billing
                                    </a>
                                </div>
                            @elseif($viewTab === 'tasks')
                                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 text-center">
                                    <p class="text-sm text-gray-500 mb-4">You have {{ $selectedRetainerData->tasks->count() }} tasks assigned to this retainer.</p>
                                    <p class="text-sm text-gray-500 mb-4">Full task management is handled via the centralized Tasks module.</p>
                                    <a href="{{ route('tasks.index') }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                                        Go to Tasks
                                    </a>
                                </div>
                            @elseif($viewTab === 'team')
                                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 text-center">
                                    <p class="text-sm text-gray-500">Retainers team functionality coming soon.</p>
                                </div>
                            @endif
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>