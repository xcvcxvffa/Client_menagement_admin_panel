<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, mount, on};

state([
    'invoice' => null,
    'showPaymentModal' => false,
    
    // Payment Form
    'payment_amount' => 0,
    'payment_date' => '',
    'payment_method' => 'bank_transfer',
    'transaction_reference' => '',
    'payment_notes' => '',
]);

mount(function (Invoice $invoice) {
    if ($invoice->business_id !== Auth::user()->current_business_id) {
        abort(403, 'Unauthorized action.');
    }
    $this->invoice = $invoice->load(['client', 'items', 'payments']);
    $this->payment_date = now()->format('Y-m-d');
    $this->payment_amount = round($this->invoice->total - $this->invoice->amount_paid, 2);
});

$markAs = function ($status) {
    \Illuminate\Support\Facades\Gate::authorize('edit invoices');
    if ($this->invoice->business_id !== Auth::user()->current_business_id) {
        abort(403, 'Unauthorized action.');
    }

    if (in_array($status, ['draft', 'sent', 'cancelled'])) {
        $this->invoice->update(['status' => $status]);

        ActivityLog::create([
            'description' => "Marked invoice #{$this->invoice->invoice_number} as " . ucfirst($status),
            'subject_id' => $this->invoice->client_id,
            'subject_type' => App\Models\Client::class,
        ]);

        $this->dispatch('notify', message: 'Invoice status updated to ' . ucfirst($status) . '.', type: 'success');
    }
};

$openPaymentModal = function () {
    $this->payment_amount = round($this->invoice->total - $this->invoice->amount_paid, 2);
    $this->showPaymentModal = true;
};

$recordPayment = function () {
    \Illuminate\Support\Facades\Gate::authorize('create payments');
    if ($this->invoice->business_id !== Auth::user()->current_business_id) {
        abort(403, 'Unauthorized action.');
    }

    $this->validate([
        'payment_amount' => 'required|numeric|min:0.1|max:' . ($this->invoice->total - $this->invoice->amount_paid + 0.01),
        'payment_date' => 'required|date',
        'payment_method' => 'required|string',
        'transaction_reference' => 'nullable|string|max:255',
        'payment_notes' => 'nullable|string',
    ]);

    $payment = Payment::create([
        'invoice_id' => $this->invoice->id,
        'amount' => round($this->payment_amount, 2),
        'paid_at' => $this->payment_date,
        'payment_method' => $this->payment_method,
        'transaction_reference' => $this->transaction_reference,
        'notes' => $this->payment_notes,
    ]);

    // Update invoice total paid
    $newAmountPaid = round($this->invoice->amount_paid + $this->payment_amount, 2);
    $status = $this->invoice->status;
    
    if ($newAmountPaid >= round($this->invoice->total, 2)) {
        $status = 'paid';
    } elseif ($status === 'draft') {
        $status = 'sent'; // Automatically mark sent if it was draft and got paid partially
    }

    $this->invoice->update([
        'amount_paid' => $newAmountPaid,
        'status' => $status
    ]);

    ActivityLog::create([
        'description' => "Recorded payment of ₹" . number_format($this->payment_amount, 2) . " for invoice #{$this->invoice->invoice_number}",
        'subject_id' => $this->invoice->client_id,
        'subject_type' => App\Models\Client::class,
    ]);

    $this->invoice->refresh();
    $this->showPaymentModal = false;
    
    // reset form
    $this->transaction_reference = '';
    $this->payment_notes = '';
    
    $this->dispatch('notify', message: 'Payment recorded successfully.', type: 'success');
};

$deletePayment = function ($paymentId) {
    \Illuminate\Support\Facades\Gate::authorize('delete payments');
    if ($this->invoice->business_id !== Auth::user()->current_business_id) {
        abort(403, 'Unauthorized action.');
    }

    $payment = Payment::find($paymentId);
    if ($payment && $payment->invoice_id === $this->invoice->id) {
        $amount = $payment->amount;
        $payment->delete();
        
        $newAmountPaid = round($this->invoice->amount_paid - $amount, 2);
        $status = $newAmountPaid < round($this->invoice->total, 2) ? 'sent' : 'paid';
        if ($newAmountPaid <= 0) {
             $status = 'sent';
        }
        
        $this->invoice->update([
            'amount_paid' => max(0, $newAmountPaid),
            'status' => $status
        ]);
        
        $this->invoice->refresh();
        $this->dispatch('notify', message: 'Payment deleted and invoice totals recalculated.', type: 'success');
    }
};

?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                Invoice {{ $this->invoice->invoice_number }}
                @php
                    $isOverdue = $invoice->status !== 'paid' && \Carbon\Carbon::parse($invoice->due_date)->isPast();
                @endphp
                
                @if($isOverdue)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50">Overdue</span>
                @else
                    @switch($quote->status ?? $invoice->status)
                        @case('draft')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">Draft</span>
                            @break
                        @case('sent')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-400">Sent</span>
                            @break
                        @case('paid')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">Paid</span>
                            @break
                    @endswitch
                @endif
            </h2>
            <div class="text-sm text-gray-500 mt-1">
                {{ $this->invoice->title }}
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('invoices.index') }}" wire:navigate class="px-4 py-2 border border-gray-250 dark:border-gray-750 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl text-sm font-medium transition-colors">
                Back
            </a>
            
            @if($this->invoice->status !== 'paid')
                <button wire:click="openPaymentModal" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-sm font-bold transition-all shadow-sm">
                    Record Payment
                </button>
            @endif

            <!-- Actions Dropdown -->
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" @click.away="open = false"
                        class="inline-flex items-center justify-center px-4 py-2 bg-orange-600 hover:bg-orange-700 dark:bg-orange-500 dark:hover:bg-orange-650 text-white text-sm font-semibold rounded-xl transition-all shadow-md">
                    Actions
                    <svg class="w-4 h-4 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" style="display: none;"
                     class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-150 dark:border-gray-750 py-1 z-50">
                    
                    <a href="{{ route('invoices.pdf', $this->invoice->id) }}" target="_blank"
                       class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        Download PDF
                    </a>
                    
                    <hr class="my-1 border-gray-150 dark:border-gray-750">
                    
                    @if($this->invoice->status === 'draft')
                        <button wire:click="markAs('sent')" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750">
                            Mark as Sent
                        </button>
                    @endif
                    @if($this->invoice->status === 'sent')
                        <button wire:click="markAs('draft')" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750">
                            Revert to Draft
                        </button>
                    @endif
                </div>
            </div>
        </div>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Document Area -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-white dark:bg-gray-850 rounded-2xl border border-gray-150 dark:border-gray-800 shadow-sm p-8 sm:p-10 relative overflow-hidden">
                <!-- Paid Watermark -->
                @if($this->invoice->status === 'paid')
                    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 rotate-[-15deg] pointer-events-none opacity-10 dark:opacity-5">
                        <span class="text-8xl font-black text-emerald-600 uppercase tracking-widest border-8 border-emerald-600 px-8 py-4 rounded-xl">Paid</span>
                    </div>
                @endif
                
                <!-- Header -->
                <div class="flex justify-between items-start mb-10 relative z-10">
                    <div>
                        <div class="w-12 h-12 rounded-lg bg-gradient-to-tr from-orange-500 to-orange-600 flex items-center justify-center text-white font-bold text-xl mb-4 shadow-md">
                            @php 
                                $business = \App\Models\Business::first();
                            @endphp
                            {{ strtoupper(substr($business->name ?? 'A', 0, 1)) }}
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-wider">Invoice</h1>
                        <p class="text-gray-500 mt-1">#{{ $this->invoice->invoice_number }}</p>
                    </div>
                    <div class="text-right">
                        @php 
                            $business = \App\Models\Business::first();
                            $user = auth()->user();
                        @endphp
                        <h3 class="font-bold text-gray-900 dark:text-white mb-1">{{ $business->name ?? 'Your Agency' }}</h3>
                        @if($business && $business->address)
                            <p class="text-sm text-gray-500 whitespace-pre-line">{{ $business->address }}</p>
                        @endif
                        @if($business && $business->tax_number)
                            <p class="text-sm text-gray-500 mt-1">Tax ID: {{ $business->tax_number }}</p>
                        @endif
                        <p class="text-sm text-gray-500 mt-2">{{ $user->email }}</p>
                    </div>
                </div>

                <!-- Client & Dates -->
                <div class="grid grid-cols-2 gap-8 mb-10 pb-10 border-b border-gray-150 dark:border-gray-800 relative z-10">
                    <div>
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Billed To:</h4>
                        <p class="font-bold text-gray-900 dark:text-white">{{ $this->invoice->client->name }}</p>
                        @if($this->invoice->client->company_name)
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $this->invoice->client->company_name }}</p>
                        @endif
                        @if($this->invoice->client->address)
                            <p class="text-sm text-gray-500 whitespace-pre-line mt-1">{{ $this->invoice->client->address }}</p>
                        @endif
                        <p class="text-sm text-gray-500 mt-1">{{ $this->invoice->client->email }}</p>
                        @if($this->invoice->client->phone)
                            <p class="text-sm text-gray-500">{{ $this->invoice->client->phone }}</p>
                        @endif
                        @if($this->invoice->client->tax_number)
                            <p class="text-sm text-gray-500 mt-1">Tax ID / GSTIN: {{ $this->invoice->client->tax_number }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="mb-4">
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Issue Date:</h4>
                            <p class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($this->invoice->issue_date)->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Due Date:</h4>
                            <p class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($this->invoice->due_date)->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="mb-10 relative z-10">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="text-[11px] font-bold uppercase tracking-wider text-gray-400 border-b-2 border-gray-150 dark:border-gray-800">
                                <th class="pb-3 w-7/12">Description</th>
                                <th class="pb-3 text-right">Qty</th>
                                <th class="pb-3 text-right">Unit Price</th>
                                <th class="pb-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($this->invoice->items as $item)
                                <tr>
                                    <td class="py-4 font-medium text-gray-900 dark:text-white">{{ $item->description }}</td>
                                    <td class="py-4 text-right text-gray-600 dark:text-gray-400">{{ $item->quantity }}</td>
                                    <td class="py-4 text-right text-gray-600 dark:text-gray-400">₹{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="py-4 text-right font-semibold text-gray-900 dark:text-white">₹{{ number_format($item->subtotal, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-gray-500 dark:text-gray-400 italic">No items found for this invoice.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="flex justify-end mb-10 relative z-10">
                    <div class="w-full sm:w-1/2">
                        <div class="flex items-center justify-between py-2 text-sm text-gray-600 dark:text-gray-400">
                            <span>Subtotal</span>
                            <span class="font-medium text-gray-900 dark:text-white">₹{{ number_format($this->invoice->subtotal, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 text-sm text-gray-600 dark:text-gray-400 border-b border-gray-150 dark:border-gray-800 pb-4">
                            <span>Tax</span>
                            <span class="font-medium text-gray-900 dark:text-white">₹{{ number_format($this->invoice->tax_total, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between py-4">
                            <span class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-sm">Total</span>
                            <span class="text-xl font-black text-gray-900 dark:text-white">₹{{ number_format($this->invoice->total, 2) }}</span>
                        </div>
                        @if($this->invoice->amount_paid > 0)
                        <div class="flex items-center justify-between py-2 text-emerald-600 dark:text-emerald-400 font-bold border-t border-gray-150 dark:border-gray-800 mt-2 pt-4">
                            <span>Amount Paid</span>
                            <span>-₹{{ number_format($this->invoice->amount_paid, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between py-4 bg-gray-50 dark:bg-gray-800/40 rounded-xl px-4 mt-2">
                            <span class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-sm">Amount Due</span>
                            <span class="text-2xl font-black text-orange-600 dark:text-orange-400">₹{{ number_format($this->invoice->total - $this->invoice->amount_paid, 2) }}</span>
                        </div>
                        @else
                        <div class="flex items-center justify-between py-4 bg-gray-50 dark:bg-gray-800/40 rounded-xl px-4 mt-2">
                            <span class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-sm">Amount Due</span>
                            <span class="text-2xl font-black text-orange-600 dark:text-orange-400">₹{{ number_format($this->invoice->total, 2) }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Notes -->
                @if($this->invoice->notes)
                    <div class="pt-6 border-t border-gray-150 dark:border-gray-800 text-sm text-gray-500 leading-relaxed whitespace-pre-wrap relative z-10">
                        <span class="font-bold text-gray-700 dark:text-gray-300 block mb-1">Notes / Bank Details:</span>
                        {{ $this->invoice->notes }}
                    </div>
                @endif
            </div>
            
            <!-- Payments History List -->
            @if(count($this->invoice->payments) > 0)
                <div class="bg-white dark:bg-gray-850 rounded-2xl border border-gray-150 dark:border-gray-800 shadow-sm p-6">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-4 border-b border-gray-100 dark:border-gray-800 pb-3">Payment History</h3>
                    <div class="space-y-4">
                        @foreach($this->invoice->payments as $payment)
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-gray-100 dark:border-gray-800">
                                <div>
                                    <div class="font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                        ₹{{ number_format($payment->amount, 2) }}
                                        <span class="text-[10px] uppercase tracking-wider bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400 px-2 py-0.5 rounded-full">{{ str_replace('_', ' ', $payment->payment_method) }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Paid on {{ \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y') }}
                                        @if($payment->transaction_reference)
                                            &bull; Ref: {{ $payment->transaction_reference }}
                                        @endif
                                    </div>
                                </div>
                                <x-confirm-action action="deletePayment({{ $payment->id }})" title="Delete Payment" message="Delete this payment record?" buttonText="Delete">
                                    <x-slot:trigger>
                                        <button type="button" class="text-gray-400 hover:text-rose-500 transition-colors p-2" title="Delete Payment">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </x-slot:trigger>
                                </x-confirm-action>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-850 rounded-2xl border border-gray-150 dark:border-gray-800 shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-4 border-b border-gray-100 dark:border-gray-800 pb-3">Client Information</h3>
                <div class="space-y-3">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-gray-400 mt-0.5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $this->invoice->client->name }}</p>
                            @if($this->invoice->client->company_name)
                                <p class="text-xs text-gray-500">{{ $this->invoice->client->company_name }}</p>
                            @endif
                        </div>
                    </div>
                    @if($this->invoice->client->email)
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            <a href="mailto:{{ $this->invoice->client->email }}" class="text-sm text-orange-600 dark:text-orange-400 hover:underline">{{ $this->invoice->client->email }}</a>
                        </div>
                    @endif
                </div>
            </div>
            
            @if($this->invoice->project)
                <div class="bg-white dark:bg-gray-850 rounded-2xl border border-gray-150 dark:border-gray-800 shadow-sm p-6">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-4 border-b border-gray-100 dark:border-gray-800 pb-3">Associated Project</h3>
                    <a href="{{ '#' }}" wire:navigate class="group flex items-center justify-between p-3 rounded-xl bg-gray-50 dark:bg-gray-800 hover:bg-orange-50 dark:hover:bg-orange-900/20 transition-colors">
                        <div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400">{{ $this->invoice->project->name }}</p>
                        </div>
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Record Payment Modal -->
    <div x-data="{ open: @entangle('showPaymentModal') }" x-show="open" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity bg-gray-500/75 dark:bg-gray-900/80 backdrop-blur-sm" aria-hidden="true"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="open" @click.away="open = false" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block w-full max-w-md overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-850 rounded-2xl shadow-xl sm:my-8 sm:align-middle border border-gray-150 dark:border-gray-750">
                
                <form wire:submit.prevent="recordPayment">
                    <div class="px-6 py-5 bg-white dark:bg-gray-850 border-b border-gray-150 dark:border-gray-750 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white" id="modal-title">Record Payment</h3>
                        <button type="button" @click="open = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none">
                            <span class="sr-only">Close</span>
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Amount Paid (₹) *</label>
                            <input type="number" wire:model="payment_amount" required min="0.1" step="0.01" max="{{ $this->invoice->total - $this->invoice->amount_paid }}"
                                   class="w-full px-4 py-3 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-orange-500 focus:border-orange-500 dark:text-white font-bold" />
                            @error('payment_amount') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Payment Date *</label>
                            <x-date-picker wire:model="payment_date" placeholder="dd-mm-yyyy" class="w-full mt-0" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Payment Method *</label>
                            <x-custom-select wire:model="payment_method" placeholder="Select Payment Method" class="w-full mt-0 z-40"
                                :options="[
                                    ['id' => 'bank_transfer', 'name' => 'Bank Transfer'],
                                    ['id' => 'cash', 'name' => 'Cash'],
                                    ['id' => 'credit_card', 'name' => 'Credit Card'],
                                    ['id' => 'paypal', 'name' => 'PayPal'],
                                    ['id' => 'upi', 'name' => 'UPI'],
                                    ['id' => 'other', 'name' => 'Other']
                                ]" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Transaction Reference (Optional)</label>
                            <input type="text" wire:model="transaction_reference" placeholder="e.g. TXN12345678"
                                   class="w-full px-4 py-3 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-orange-500 focus:border-orange-500 dark:text-white" />
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-150 dark:border-gray-750 flex justify-end gap-3 rounded-b-2xl">
                        <button type="button" @click="open = false" class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-sm font-bold shadow-sm transition-colors">
                            Save Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
