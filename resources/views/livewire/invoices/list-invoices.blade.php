<?php

use App\Models\Invoice;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, with, mount};

state([
    'search' => '',
    'statusFilter' => 'all',
]);

$markPaid = function ($id) {
    \Illuminate\Support\Facades\Gate::authorize('edit invoices');
    $invoice = Invoice::find($id);
    if ($invoice && $invoice->status !== 'paid') {
        $amountToPay = max(0, $invoice->total - $invoice->amount_paid);
        if ($amountToPay > 0) {
            $invoice->payments()->create([
                'amount' => $amountToPay,
                'payment_method' => 'bank_transfer',
                'paid_at' => now()->format('Y-m-d'),
                'notes' => 'Marked as paid quickly from invoice list.',
            ]);
            
            $invoice->update([
                'status' => 'paid',
                'amount_paid' => $invoice->total
            ]);
            
            ActivityLog::create([
                'description' => "Quick marked invoice {$invoice->invoice_number} as Paid.",
                'subject_id' => $invoice->client_id,
                'subject_type' => \App\Models\Client::class,
            ]);
            
            $this->dispatch('notify', message: 'Invoice marked as paid.', type: 'success');
        }
    }
};

$markSent = function ($id) {
    \Illuminate\Support\Facades\Gate::authorize('edit invoices');
    $invoice = Invoice::find($id);
    if ($invoice && $invoice->status === 'draft') {
        $invoice->update(['status' => 'sent']);
        
        ActivityLog::create([
            'description' => "Marked invoice {$invoice->invoice_number} as Sent.",
            'subject_id' => $invoice->client_id,
            'subject_type' => \App\Models\Client::class,
        ]);
        
        $this->dispatch('notify', message: 'Invoice status updated to Sent.', type: 'success');
    }
};

$deleteInvoice = function ($id) {
    \Illuminate\Support\Facades\Gate::authorize('delete invoices');
    $invoice = Invoice::find($id);
    if ($invoice) {
        $invoiceNumber = $invoice->invoice_number;
        $clientId = $invoice->client_id;
        
        $invoice->items()->delete();
        $invoice->payments()->delete();
        $invoice->delete();

        ActivityLog::create([
            'description' => "Deleted invoice #{$invoiceNumber}",
            'subject_id' => $clientId,
            'subject_type' => App\Models\Client::class,
        ]);
        
        $this->dispatch('notify', message: 'Invoice deleted successfully.', type: 'success');
    }
};

with(function () {
    $query = Invoice::with('client');

    if ($this->search) {
        $query->where(function($q) {
            $q->where('invoice_number', 'like', "%{$this->search}%")
              ->orWhere('title', 'like', "%{$this->search}%")
              ->orWhereHas('client', fn($c) => $c->where('name', 'like', "%{$this->search}%"));
        });
    }

    if ($this->statusFilter !== 'all') {
        if ($this->statusFilter === 'overdue') {
            $query->where('status', '!=', 'paid')
                  ->where('due_date', '<', now()->format('Y-m-d'));
        } else {
            $query->where('status', $this->statusFilter);
        }
    }

    $invoices = $query->latest()->paginate(15);

    return [
        'invoices' => $invoices,
    ];
});

?>

<div>
    <!-- Top Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
            <!-- Search -->
            <div class="relative w-full sm:w-72">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-gray-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </span>
                <input type="text"
                       wire:model.live="search"
                       placeholder="Search invoices, clients..."
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500 dark:text-white transition-all duration-200" />
            </div>

            <!-- Status Filter -->
            <x-custom-select wire:model.live="statusFilter" placeholder="All Statuses" class="w-full sm:w-44 mt-0 z-40"
                :options="[
                    ['id' => 'all', 'name' => 'All Statuses'],
                    ['id' => 'draft', 'name' => 'Draft'],
                    ['id' => 'sent', 'name' => 'Sent'],
                    ['id' => 'paid', 'name' => 'Paid'],
                    ['id' => 'overdue', 'name' => 'Overdue']
                ]" />
        </div>

        @can('create invoices')
        <a href="{{ route('invoices.create') }}" wire:navigate
           class="inline-flex items-center justify-center px-4 py-2.5 bg-orange-600 hover:bg-orange-700 dark:bg-orange-500 dark:hover:bg-orange-650 text-white text-sm font-semibold rounded-xl transition-all duration-150 shadow-md">
            <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Create Invoice
        </a>
        @endcan
    </div>

    <!-- Flash Message -->
    @if (session()->has('message'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/40 text-sm flex items-center shadow-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            <svg class="w-5 h-5 mr-2.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <!-- Invoices Table -->
    <div class="bg-white dark:bg-gray-850 rounded-2xl border border-gray-150 dark:border-gray-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 dark:bg-gray-800/40 border-b border-gray-150 dark:border-gray-800 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4">Invoice #</th>
                        <th class="px-6 py-4">Client</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Total</th>
                        <th class="px-6 py-4 text-right">Due</th>
                        <th class="px-6 py-4">Due Date</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-150 dark:divide-gray-800 text-sm">
                    @forelse($invoices as $invoice)
                        @php
                            $isOverdue = $invoice->status !== 'paid' && \Carbon\Carbon::parse($invoice->due_date)->isPast();
                            $dueAmount = $invoice->total - $invoice->amount_paid;
                        @endphp
                        <tr class="hover:bg-gray-50/55 dark:hover:bg-gray-800/25 transition-colors duration-150">
                            <td class="px-6 py-4">
                                <a href="{{ route('invoices.show', $invoice->id) }}" wire:navigate class="font-bold text-orange-600 dark:text-orange-400 hover:underline">
                                    {{ $invoice->invoice_number }}
                                </a>
                                @if($invoice->title)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $invoice->title }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($invoice->client)
                                    <span class="font-medium text-gray-900 dark:text-gray-200">{{ $invoice->client->name }}</span>
                                    @if($invoice->client->company_name)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $invoice->client->company_name }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400 italic">No Client</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($isOverdue)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50">Overdue</span>
                                @else
                                    @switch($invoice->status)
                                        @case('draft')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700">Draft</span>
                                            @break
                                        @case('sent')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-400 border border-sky-200 dark:border-sky-900/50">Sent</span>
                                            @break
                                        @case('paid')
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/50">Paid</span>
                                            @break
                                    @endswitch
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right font-medium text-gray-900 dark:text-gray-300">
                                ₹{{ number_format($invoice->total, 2) }}
                            </td>
                            <td class="px-6 py-4 text-right font-bold {{ $dueAmount > 0 ? ($isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white') : 'text-emerald-600 dark:text-emerald-400' }}">
                                ₹{{ number_format($dueAmount, 2) }}
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                @if($invoice->due_date)
                                    <span class="{{ $isOverdue ? 'text-rose-600 dark:text-rose-400 font-semibold' : '' }}">
                                        {{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    @can('view invoices')
                                    <a href="{{ route('invoices.show', $invoice->id) }}" wire:navigate
                                       class="p-1.5 text-gray-500 hover:text-orange-600 dark:hover:text-orange-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                       title="View Invoice">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    @endcan
                                    @can('export invoices')
                                    <a href="{{ route('invoices.pdf', $invoice->id) }}" target="_blank"
                                       class="p-1.5 text-gray-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                       title="Download PDF">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                    @endcan
                                    
                                    @can('edit invoices')
                                        @if($invoice->status === 'draft')
                                        <button wire:click="markSent({{ $invoice->id }})" class="p-1.5 text-gray-500 hover:text-sky-600 dark:hover:text-sky-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors" title="Mark as Sent">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                            </svg>
                                        </button>
                                        @endif
                                        @if($invoice->status !== 'paid')
                                        <button wire:click="markPaid({{ $invoice->id }})" class="p-1.5 text-gray-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors" title="Mark as Paid">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                        @endif
                                    @endcan

                                    @can('delete invoices')
                                    <x-confirm-action action="deleteInvoice({{ $invoice->id }})" title="Delete Invoice" message="Delete this invoice forever?" buttonText="Delete">
                                        <x-slot:trigger>
                                            <button type="button" class="p-1.5 text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 rounded-lg transition-colors" title="Delete Invoice">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </x-slot:trigger>
                                    </x-confirm-action>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center text-gray-400 dark:text-gray-500">
                                <svg class="w-14 h-14 mx-auto stroke-1 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-sm mt-3 font-medium">No invoices found</p>
                                <p class="text-xs mt-1 text-gray-400">Create your first invoice to get paid.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($invoices->hasPages())
            <div class="px-6 py-4 border-t border-gray-150 dark:border-gray-800">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
