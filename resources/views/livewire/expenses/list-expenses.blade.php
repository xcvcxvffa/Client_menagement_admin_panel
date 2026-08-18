<?php

use App\Models\Expense;
use App\Models\ActivityLog;
use function Livewire\Volt\{state, on, computed};
use Illuminate\Validation\Rule;

state([
    'editingExpense' => null,
    
    // Form fields
    'date' => '',
    'amount' => '',
    'category' => '',
    'description' => '',
    'receipt_path' => null,
    
    // Recurring fields
    'is_recurring' => false,
    'billing_cycle' => 'monthly',
    'next_renewal_date' => '',
    
    // Linking fields
    'client_id' => '',
    'project_id' => '',
]);

$categories = computed(function () {
    return [
        'Software',
        'Salaries',
        'Marketing',
        'Utilities',
        'Office',
        'Travel',
        'Contractors',
        'Other'
    ];
});

$expenses = computed(function () {
    return Expense::with(['client', 'project'])->orderBy('date', 'desc')->get();
});

$clients = computed(function () {
    return \App\Models\Client::orderBy('name')->get();
});

$projects = computed(function () {
    if (!$this->client_id) return collect();
    return \App\Models\Project::where('client_id', $this->client_id)->orderBy('name')->get();
});

$updatedClientId = function () {
    $this->project_id = '';
};

$openModal = function ($id = null) {
    $this->resetForm();
    if ($id) {
        $expense = Expense::find($id);
        if ($expense) {
            $this->editingExpense = $expense;
            $this->date = $expense->date->format('Y-m-d');
            $this->amount = $expense->amount;
            $this->category = $expense->category;
            $this->description = $expense->description;
            $this->is_recurring = $expense->is_recurring;
            $this->billing_cycle = $expense->billing_cycle ?? 'monthly';
            $this->next_renewal_date = $expense->next_renewal_date ? $expense->next_renewal_date->format('Y-m-d') : '';
            $this->client_id = $expense->client_id ?? '';
            $this->project_id = $expense->project_id ?? '';
        }
    } else {
        $this->date = now()->format('Y-m-d');
    }
    $this->dispatch('open-modal', 'expense-modal');
};

$closeModal = function () {
    $this->dispatch('close-modal', 'expense-modal');
    $this->resetForm();
};

// Auto-calculate next_renewal_date when date changes
$updatedDate = function () {
    if ($this->is_recurring && $this->date) {
        $this->next_renewal_date = $this->calcNextRenewal($this->date, $this->billing_cycle);
    }
};

// Auto-calculate next_renewal_date when billing_cycle changes
$updatedBillingCycle = function () {
    if ($this->is_recurring && $this->date) {
        $this->next_renewal_date = $this->calcNextRenewal($this->date, $this->billing_cycle);
    }
};

// Auto-calculate when recurring toggle is turned ON
$updatedIsRecurring = function () {
    if ($this->is_recurring && $this->date && !$this->next_renewal_date) {
        $this->next_renewal_date = $this->calcNextRenewal($this->date, $this->billing_cycle);
    }
};

// Helper: calculate next renewal date based on billing cycle
$calcNextRenewal = function (string $date, string $cycle): string {
    try {
        $d = \Carbon\Carbon::parse($date);
        return match($cycle) {
            'monthly'   => $d->addMonth()->format('Y-m-d'),
            'quarterly' => $d->addMonths(3)->format('Y-m-d'),
            'yearly'    => $d->addYear()->format('Y-m-d'),
            default     => $d->addMonth()->format('Y-m-d'),
        };
    } catch (\Exception $e) {
        return '';
    }
};

$resetForm = function () {
    $this->editingExpense = null;
    $this->date = '';
    $this->amount = '';
    $this->category = '';
    $this->description = '';
    $this->receipt_path = null;
    $this->is_recurring = false;
    $this->billing_cycle = 'monthly';
    $this->next_renewal_date = '';
    $this->client_id = '';
    $this->project_id = '';
    $this->resetValidation();
};

$saveExpense = function () {
    $this->validate([
        'date' => 'required|date',
        'amount' => 'required|numeric|min:0.01',
        'category' => 'required|string',
        'description' => 'nullable|string',
        'is_recurring' => 'boolean',
        'billing_cycle' => 'required_if:is_recurring,true|in:monthly,quarterly,yearly',
        'next_renewal_date' => 'required_if:is_recurring,true|date',
        'client_id' => 'nullable|exists:clients,id',
        'project_id' => 'nullable|exists:projects,id',
    ]);

    $data = [
        'date' => $this->date,
        'amount' => $this->amount,
        'category' => $this->category,
        'description' => $this->description,
        'is_recurring' => $this->is_recurring,
        'billing_cycle' => $this->is_recurring ? $this->billing_cycle : null,
        'next_renewal_date' => $this->is_recurring ? $this->next_renewal_date : null,
        'client_id' => $this->client_id ?: null,
        'project_id' => $this->project_id ?: null,
        'business_id' => auth()->user()->current_business_id ?? \App\Models\Business::first()->id,
    ];

    if ($this->editingExpense) {
        $this->editingExpense->update($data);
        $message = 'Expense updated successfully.';
    } else {
        $expense = Expense::create($data);
        $message = 'Expense logged successfully.';
        
        ActivityLog::create([
            'description' => "Logged an expense of ₹" . number_format($this->amount, 2) . " for " . $this->category,
        ]);
    }

    $this->closeModal();
    $this->dispatch('notify', message: $message, type: 'success');
};

$deleteExpense = function ($id) {
    $expense = Expense::find($id);
    if ($expense) {
        $amount = $expense->amount;
        $category = $expense->category;
        $expense->delete();
        
        ActivityLog::create([
            'description' => "Deleted expense of ₹" . number_format($amount, 2) . " for " . $category,
        ]);
        
        $this->dispatch('notify', message: 'Expense deleted successfully.', type: 'success');
    }
};

?>

<div class="space-y-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Expenses</h1>
            <p class="text-sm text-gray-500 mt-1">Track and manage your agency's outgoing costs.</p>
        </div>
        <button wire:click="openModal(null)" class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Log Expense
        </button>
    </div>

    <!-- Flash Message -->
    @if (session()->has('message'))
        <div class="p-4 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 text-sm flex items-center shadow-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            <svg class="w-5 h-5 mr-2.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <div class="relative">
        <div wire:loading.delay.long>
            <x-skeleton-loader type="table" rows="6" cols="6" />
        </div>
        <div wire:loading.remove.delay.long class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700 text-xs font-bold text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-4">Date</th>
                        <th class="px-6 py-4">Client / Project</th>
                        <th class="px-6 py-4">Category</th>
                        <th class="px-6 py-4">Renewal</th>
                        <th class="px-6 py-4">Description</th>
                        <th class="px-6 py-4 text-right">Amount</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($this->expenses as $expense)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800 transition-colors">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $expense->date->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4">
                                @if($expense->client)
                                    <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $expense->client->name }}</div>
                                    @if($expense->project)
                                        <div class="text-xs text-gray-500">{{ $expense->project->name }}</div>
                                    @endif
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-500 border border-gray-200">INTERNAL</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold bg-orange-50 text-orange-700 border border-orange-100">
                                    {{ $expense->category }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($expense->is_recurring && $expense->next_renewal_date)
                                    <span class="inline-flex items-center text-[10px] font-bold text-sky-600 bg-sky-50 px-2 py-0.5 rounded border border-sky-100" title="Renews {{ ucfirst($expense->billing_cycle) }}">
                                        <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        {{ $expense->next_renewal_date->format('M d, Y') }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ $expense->description ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm font-bold text-gray-900 dark:text-gray-100">
                                ₹{{ number_format($expense->amount, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    <button wire:click="openModal({{ $expense->id }})" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    <x-confirm-action action="deleteExpense({{ $expense->id }})" title="Delete Expense" message="Are you sure you want to delete this expense? This action cannot be undone." buttonText="Delete">
                                        <x-slot:trigger>
                                            <button type="button" class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </x-slot:trigger>
                                    </x-confirm-action>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="w-16 h-16 bg-gray-50 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                    </svg>
                                </div>
                                <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">No expenses recorded</h3>
                                <p class="text-sm text-gray-500 mb-4">You haven't logged any business expenses yet.</p>
                                <button wire:click="openModal(null)" class="inline-flex items-center px-4 py-2 bg-orange-600 hover:bg-orange-700 text-white text-sm font-semibold rounded-lg shadow-sm transition-colors">
                                    <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    Log First Expense
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
    </div>

    <!-- Expense Modal -->
    <x-modal name="expense-modal" maxWidth="md">
        <!-- Premium Header -->
        <div class="relative bg-gradient-to-br from-orange-500 to-orange-600 px-7 py-6 rounded-t-2xl overflow-hidden">
            <!-- Decorative circles -->
            <div class="absolute -top-6 -right-6 w-28 h-28 bg-white/10 rounded-full"></div>
            <div class="absolute -bottom-4 -left-4 w-16 h-16 bg-white/10 rounded-full"></div>
            <div class="relative flex items-center gap-4">
                <div class="w-11 h-11 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-white tracking-tight">
                        {{ $editingExpense ? 'Edit Expense' : 'Log New Expense' }}
                    </h2>
                    <p class="text-xs text-orange-100 mt-0.5">Track outgoing costs for your agency</p>
                </div>
            </div>
        </div>

        <div class="px-7 py-6">
            <form wire:submit="saveExpense" class="space-y-5">
                <!-- Amount & Date Row -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Amount (₹)</label>
                        <div class="relative">
                            <input type="number" step="0.01" wire:model="amount"
                                class="w-full pl-8 pr-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-sm font-semibold text-gray-900 dark:text-white focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-all shadow-sm"
                                placeholder="0.00">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <span class="text-gray-400 font-semibold text-sm">₹</span>
                            </div>
                        </div>
                        @error('amount') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Date</label>
                        <x-date-picker wire:model="date" placeholder="Select date" class="w-full mt-0" />
                        @error('date') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- Select Client -->
                    <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Client (Optional)</label>
                        <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                            <button type="button" @click="open = !open"
                                :class="open ? 'border-orange-500 ring-2 ring-orange-500/20' : 'border-gray-200 dark:border-gray-700'"
                                class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg border bg-white dark:bg-gray-900 text-sm font-medium focus:outline-none transition-all duration-150 shadow-sm">
                                <span class="{{ $client_id ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">
                                    {{ $client_id ? $this->clients->find($client_id)?->name : '-- Internal / No Client --' }}
                                </span>
                                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="open ? '-rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 -translate-y-1"
                                 class="absolute z-50 mt-1.5 w-full bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-100 dark:border-gray-700 p-1.5 space-y-0.5 max-h-48 overflow-y-auto"
                                 style="display:none;">
                                <button type="button"
                                    wire:click="$set('client_id', '')"
                                    @click="open = false"
                                    class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 {{ !$client_id ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                    <span>-- Internal / No Client --</span>
                                    @if(!$client_id)
                                        <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </button>
                                @foreach($this->clients as $c)
                                <button type="button"
                                    wire:click="$set('client_id', '{{ $c->id }}')"
                                    @click="open = false"
                                    class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 {{ $client_id == $c->id ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                                    <span>{{ $c->name }}</span>
                                    @if($client_id == $c->id)
                                        <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </button>
                                @endforeach
                            </div>
                        </div>
                        @error('client_id') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>

                    <!-- Select Project -->
                    <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Project (Optional)</label>
                        @if(!$client_id)
                            <div class="w-full flex items-center gap-2 px-4 py-2.5 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-400 cursor-not-allowed">
                                -- Select Client First --
                            </div>
                        @elseif($this->projects->isEmpty())
                            <div class="w-full flex items-center gap-2 px-4 py-2.5 rounded-lg border border-amber-100 bg-amber-50 text-sm text-amber-600">
                                No active projects
                            </div>
                        @else
                            <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                                <button type="button" @click="open = !open"
                                    :class="open ? 'border-orange-500 ring-2 ring-orange-500/20' : 'border-gray-200 dark:border-gray-700'"
                                    class="w-full flex items-center justify-between px-4 py-2.5 rounded-lg border bg-white dark:bg-gray-900 text-sm font-medium focus:outline-none transition-all duration-150 shadow-sm">
                                    <span class="{{ $project_id ? 'text-gray-900 dark:text-white' : 'text-gray-400' }}">
                                        @if($project_id)
                                            {{ $this->projects->find($project_id)?->name ?? '-- Select Project --' }}
                                        @else
                                            -- Select Project --
                                        @endif
                                    </span>
                                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" :class="open ? '-rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="open"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 -translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 translate-y-0"
                                     x-transition:leave-end="opacity-0 -translate-y-1"
                                     class="absolute z-50 mt-1.5 w-full bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-100 dark:border-gray-700 p-1.5 space-y-0.5 max-h-48 overflow-y-auto"
                                     style="display:none;">
                                    <button type="button"
                                        wire:click="$set('project_id', '')"
                                        @click="open = false"
                                        class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 {{ !$project_id ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                        <span>-- Unassigned --</span>
                                        @if(!$project_id)
                                            <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </button>
                                    @foreach($this->projects as $p)
                                    <button type="button"
                                        wire:click="$set('project_id', '{{ $p->id }}')"
                                        @click="open = false"
                                        class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 text-left {{ $project_id == $p->id ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white' }}">
                                        <span>{{ $p->name }}</span>
                                        @if($project_id == $p->id)
                                            <svg class="w-4 h-4 text-orange-500 flex-shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @error('project_id') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Category Input -->
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Category</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <svg class="w-4 h-4 text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        </div>
                        <input type="text" wire:model="category" 
                            class="w-full pl-10 pr-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-sm font-medium focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-all duration-150 shadow-sm"
                            placeholder="e.g. Utilities">
                    </div>
                    @error('category') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Description (Optional)</label>
                    <textarea wire:model="description" rows="2"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-sm text-gray-900 dark:text-white shadow-sm focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-all resize-none"
                        placeholder="What was this expense for?"></textarea>
                    @error('description') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                
                <!-- Recurring Toggle -->
                <div class="flex items-center justify-between py-3 px-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-700">
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-white">Recurring Expense</p>
                        <p class="text-xs text-gray-500 mt-0.5">Subscription, retainer, or regular bill</p>
                    </div>
                    <label class="relative flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="is_recurring" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-orange-500/30 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-orange-500"></div>
                    </label>
                </div>
                
                @if($is_recurring)
                <div class="grid grid-cols-2 gap-4 bg-orange-50/50 p-4 rounded-xl border border-orange-100">
                    <!-- Billing Cycle Custom Dropdown - wire:click for Livewire reactivity -->
                    <div x-data="{
                        open: false,
                        options: [
                            { value: 'monthly', label: 'Monthly' },
                            { value: 'quarterly', label: 'Quarterly' },
                            { value: 'yearly', label: 'Yearly' },
                        ]
                    }" @click.outside="open = false" class="relative">
                        <label class="block text-[13px] font-bold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">Billing Cycle</label>
                        <!-- Trigger Button -->
                        <button type="button" @click="open = !open"
                            :class="open ? 'border-orange-500 ring-2 ring-orange-500/20' : 'border-gray-200 dark:border-gray-600'"
                            class="w-full flex items-center justify-between px-4 py-2.5 rounded-xl border bg-white dark:bg-gray-900 text-sm font-medium text-gray-800 dark:text-white focus:outline-none transition-all duration-150 shadow-sm">
                            <span class="text-gray-800 dark:text-gray-100">
                                @php
                                    $labels = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];
                                @endphp
                                {{ $labels[$billing_cycle] ?? 'Select' }}
                            </span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="open ? '-rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <!-- Dropdown Panel - opens UPWARD -->
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-2"
                             class="absolute z-50 bottom-full mb-2 w-full bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-100 dark:border-gray-700 p-1.5 space-y-0.5"
                             style="display:none;">
                            <button type="button"
                                wire:click="$set('billing_cycle', 'monthly')" @click="open = false"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 {{ $billing_cycle === 'monthly' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900' }}">
                                <span>Monthly</span>
                                @if($billing_cycle === 'monthly')
                                    <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </button>
                            <button type="button"
                                wire:click="$set('billing_cycle', 'quarterly')" @click="open = false"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 {{ $billing_cycle === 'quarterly' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900' }}">
                                <span>Quarterly</span>
                                @if($billing_cycle === 'quarterly')
                                    <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </button>
                            <button type="button"
                                wire:click="$set('billing_cycle', 'yearly')" @click="open = false"
                                class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 {{ $billing_cycle === 'yearly' ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900' }}">
                                <span>Yearly</span>
                                @if($billing_cycle === 'yearly')
                                    <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </button>
                        </div>
                    </div>
                        @error('billing_cycle') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-[13px] font-bold text-gray-700 dark:text-gray-300 mb-1.5 uppercase tracking-wider">Next Renewal</label>
                        <x-date-picker wire:model="next_renewal_date" placeholder="Select date" class="w-full mt-0" />
                        @error('next_renewal_date') <span class="text-rose-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
                @endif

                <!-- Premium Footer -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-100 dark:border-gray-700 gap-3">
                    <button type="button" wire:click="closeModal"
                        class="flex-1 px-4 py-2.5 text-sm font-semibold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-all">
                        Cancel
                    </button>
                    <button type="submit"
                        class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 rounded-xl shadow-sm shadow-orange-200 transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        {{ $editingExpense ? 'Update Expense' : 'Log Expense' }}
                    </button>
                </div>
            </form>
        </div>
    </x-modal>
</div>
