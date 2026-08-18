<?php

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, with};

with(function () {
    $clientId = Auth::guard('client')->id();
    
    // We only show non-draft invoices to the client (sent, paid, cancelled)
    $invoices = Invoice::where('client_id', $clientId)
                   ->whereIn('status', ['sent', 'paid', 'cancelled'])
                   ->orderBy('created_at', 'desc')
                   ->get();

    return [
        'invoices' => $invoices,
    ];
});

?>

<div class="space-y-6">
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-150 dark:border-gray-750 shadow-sm overflow-hidden">
        @if($invoices->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-gray-800/40 border-b border-gray-150 dark:border-gray-750">
                            <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Invoice Info</th>
                            <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Amount Due</th>
                            <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                            <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-150 dark:divide-gray-750">
                        @foreach($invoices as $invoice)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="font-bold text-gray-900 dark:text-white">{{ $invoice->title }}</div>
                                    <div class="text-sm text-gray-500 mt-1">#{{ $invoice->invoice_number }} &bull; Due by {{ \Carbon\Carbon::parse($invoice->due_date)->format('M d, Y') }}</div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="font-bold text-gray-900 dark:text-white">₹{{ number_format($invoice->total - $invoice->amount_paid, 2) }}</div>
                                    @if($invoice->amount_paid > 0)
                                        <div class="text-xs text-gray-500 mt-1">Paid: ₹{{ number_format($invoice->amount_paid, 2) }} / ₹{{ number_format($invoice->total, 2) }}</div>
                                    @else
                                        <div class="text-xs text-gray-500 mt-1">Total: ₹{{ number_format($invoice->total, 2) }}</div>
                                    @endif
                                </td>
                                <td class="py-4 px-6">
                                    @php
                                        $isOverdue = $invoice->status !== 'paid' && \Carbon\Carbon::parse($invoice->due_date)->isPast();
                                    @endphp
                                    @if($isOverdue)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-900/50">Overdue</span>
                                    @elseif($invoice->status === 'paid')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">Paid</span>
                                    @elseif($invoice->status === 'sent')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-400">Unpaid</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <a href="{{ route('invoices.pdf', $invoice->id) }}" target="_blank" class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow-sm transition-colors">
                                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        Download PDF
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-10 text-center text-gray-500">
                You have no invoices available.
            </div>
        @endif
    </div>
</div>
