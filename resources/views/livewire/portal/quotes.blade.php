<?php

use App\Models\Quote;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, with};

with(function () {
    $clientId = Auth::guard('client')->id();
    
    $quotes = Quote::where('client_id', $clientId)
                   ->whereIn('status', ['draft', 'sent', 'accepted'])
                   ->orderBy('created_at', 'desc')
                   ->get();

    return [
        'quotes' => $quotes,
    ];
});

$acceptQuote = function ($quoteId) {
    $quote = Quote::where('id', $quoteId)
                  ->where('client_id', Auth::guard('client')->id())
                  ->first();

    if ($quote && in_array($quote->status, ['draft', 'sent'])) {
        $quote->update(['status' => 'accepted']);
        
        ActivityLog::create([
            'description' => "Quote #{$quote->quote_number} was digitally accepted by client.",
            'subject_id' => $quote->client_id,
            'subject_type' => App\Models\Client::class,
        ]);
        
        $this->dispatch('notify', message: 'Quote accepted successfully! Thank you.', type: 'success');
    }
};

?>

<div class="space-y-6">
    @if (session()->has('message'))
        <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/40 text-sm flex items-center shadow-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            <svg class="w-5 h-5 mr-2.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-150 dark:border-gray-750 shadow-sm overflow-hidden">
        @if($quotes->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50 dark:bg-gray-800/40 border-b border-gray-150 dark:border-gray-750">
                            <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Quote Info</th>
                            <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Amount</th>
                            <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                            <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-150 dark:divide-gray-750">
                        @foreach($quotes as $quote)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                <td class="py-4 px-6">
                                    <div class="font-bold text-gray-900 dark:text-white">{{ $quote->title }}</div>
                                    <div class="text-sm text-gray-500 mt-1">#{{ $quote->quote_number }} &bull; Valid till {{ \Carbon\Carbon::parse($quote->valid_until)->format('M d, Y') }}</div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="font-bold text-gray-900 dark:text-white">₹{{ number_format($quote->total, 2) }}</div>
                                </td>
                                <td class="py-4 px-6">
                                    @if($quote->status === 'accepted')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400">Accepted</span>
                                    @elseif($quote->status === 'sent')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-400">Pending</span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">Draft</span>
                                    @endif
                                </td>
                                <td class="py-4 px-6 text-right space-x-2">
                                    <a href="{{ route('quotes.pdf', $quote->id) }}" target="_blank" class="inline-flex items-center px-3 py-1.5 border border-gray-250 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg text-sm font-medium transition-colors">
                                        View PDF
                                    </a>
                                    @if(in_array($quote->status, ['draft', 'sent']))
                                        <x-confirm-action action="acceptQuote({{ $quote->id }})" title="Accept Quote" message="Are you sure you want to accept this quote?" buttonText="Accept" buttonColor="indigo">
                                            <x-slot:trigger>
                                                <button type="button" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-bold shadow-sm transition-colors">
                                                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                    Accept
                                                </button>
                                            </x-slot:trigger>
                                        </x-confirm-action>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-10 text-center text-gray-500">
                You have no quotes available.
            </div>
        @endif
    </div>
</div>
