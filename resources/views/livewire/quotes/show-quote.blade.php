<?php

use App\Models\Quote;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, mount, on};

state([
    'quote' => null,
]);

mount(function (Quote $quote) {
    $this->quote = $quote->load(['client', 'items']);
});

$markAs = function ($status) {
    if (in_array($status, ['draft', 'sent', 'accepted', 'rejected'])) {
        $this->quote->update([
            'status' => $status,
            'accepted_at' => $status === 'accepted' ? now() : null,
        ]);

        ActivityLog::create([
            'description' => "Marked quote #{$this->quote->quote_number} as " . ucfirst($status),
            'subject_id' => $this->quote->client_id,
            'subject_type' => App\Models\Client::class,
        ]);

        $this->dispatch('notify', message: 'Quote status updated to ' . ucfirst($status) . '.', type: 'success');
    }
};

?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
                Quote {{ $this->quote->quote_number }}
                @switch($quote->status)
                    @case('draft')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">Draft</span>
                        @break
                    @case('sent')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-400">Sent</span>
                        @break
                    @case('accepted')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">Accepted</span>
                        @break
                    @case('rejected')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400">Rejected</span>
                        @break
                @endswitch
            </h2>
            <div class="text-sm text-gray-500 mt-1">
                {{ $this->quote->title }}
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('quotes.index') }}" wire:navigate class="px-4 py-2 border border-gray-250 dark:border-gray-750 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl text-sm font-medium transition-colors">
                Back
            </a>
            
            <!-- Actions Dropdown -->
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" @click.away="open = false"
                        class="inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-650 text-white text-sm font-semibold rounded-xl transition-all shadow-md">
                    Actions
                    <svg class="w-4 h-4 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" style="display: none;"
                     class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-150 dark:border-gray-750 py-1 z-50">
                    
                    <a href="{{ route('quotes.pdf', $this->quote->id) }}" target="_blank"
                       class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750 flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                        Download PDF
                    </a>
                    
                    <hr class="my-1 border-gray-150 dark:border-gray-750">
                    
                    @if($this->quote->status !== 'sent')
                        <button wire:click="markAs('sent')" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-750">
                            Mark as Sent
                        </button>
                    @endif
                    @if($this->quote->status !== 'accepted')
                        <button wire:click="markAs('accepted')" class="w-full text-left px-4 py-2 text-sm text-emerald-600 hover:bg-gray-50 dark:hover:bg-gray-750">
                            Mark as Accepted
                        </button>
                    @endif
                    @if($this->quote->status !== 'rejected')
                        <button wire:click="markAs('rejected')" class="w-full text-left px-4 py-2 text-sm text-rose-600 hover:bg-gray-50 dark:hover:bg-gray-750">
                            Mark as Rejected
                        </button>
                    @endif
                    @if($this->quote->status !== 'draft')
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
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-850 rounded-2xl border border-gray-150 dark:border-gray-800 shadow-sm p-8 sm:p-10">
                <!-- Header -->
                <div class="flex justify-between items-start mb-10">
                    <div>
                        <div class="w-12 h-12 rounded-lg bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-xl mb-4 shadow-md">
                            FH
                        </div>
                        <h1 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-wider">Quotation</h1>
                        <p class="text-gray-500 mt-1">#{{ $this->quote->quote_number }}</p>
                    </div>
                    <div class="text-right">
                        <h3 class="font-bold text-gray-900 dark:text-white mb-1">FreelanceHub Inc.</h3>
                        <p class="text-sm text-gray-500">123 Business Road</p>
                        <p class="text-sm text-gray-500">Tech City, TX 75001</p>
                        <p class="text-sm text-gray-500 mt-2">hello@freelancehub.local</p>
                    </div>
                </div>

                <!-- Client & Dates -->
                <div class="grid grid-cols-2 gap-8 mb-10 pb-10 border-b border-gray-150 dark:border-gray-800">
                    <div>
                        <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-2">Quote To:</h4>
                        <p class="font-bold text-gray-900 dark:text-white">{{ $this->quote->client->name }}</p>
                        @if($this->quote->client->company_name)
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $this->quote->client->company_name }}</p>
                        @endif
                        @if($this->quote->client->email)
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $this->quote->client->email }}</p>
                        @endif
                        @if($this->quote->client->phone)
                            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $this->quote->client->phone }}</p>
                        @endif
                    </div>
                    <div class="text-right">
                        <div class="mb-4">
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Date Issued:</h4>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $this->quote->created_at->format('M d, Y') }}</p>
                        </div>
                        <div>
                            <h4 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mb-1">Valid Until:</h4>
                            <p class="font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($this->quote->valid_until)->format('M d, Y') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="mb-10">
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
                            @foreach($this->quote->items as $item)
                                <tr>
                                    <td class="py-4 font-medium text-gray-900 dark:text-white">{{ $item->description }}</td>
                                    <td class="py-4 text-right text-gray-600 dark:text-gray-400">{{ $item->quantity }}</td>
                                    <td class="py-4 text-right text-gray-600 dark:text-gray-400">₹{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="py-4 text-right font-semibold text-gray-900 dark:text-white">₹{{ number_format($item->subtotal, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Totals -->
                <div class="flex justify-end mb-10">
                    <div class="w-full sm:w-1/2">
                        <div class="flex items-center justify-between py-2 text-sm text-gray-600 dark:text-gray-400">
                            <span>Subtotal</span>
                            <span class="font-medium text-gray-900 dark:text-white">₹{{ number_format($this->quote->subtotal, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between py-2 text-sm text-gray-600 dark:text-gray-400 border-b border-gray-150 dark:border-gray-800 pb-4">
                            <span>Tax</span>
                            <span class="font-medium text-gray-900 dark:text-white">₹{{ number_format($this->quote->tax_total, 2) }}</span>
                        </div>
                        <div class="flex items-center justify-between py-4">
                            <span class="font-bold text-gray-900 dark:text-white uppercase tracking-wider text-sm">Total</span>
                            <span class="text-2xl font-black text-indigo-600 dark:text-indigo-400">₹{{ number_format($this->quote->total, 2) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                @if($this->quote->notes)
                    <div class="pt-6 border-t border-gray-150 dark:border-gray-800 text-sm text-gray-500 leading-relaxed whitespace-pre-wrap">
                        <span class="font-bold text-gray-700 dark:text-gray-300 block mb-1">Terms & Conditions:</span>
                        {{ $this->quote->notes }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar / Client Details -->
        <div class="space-y-6">
            <div class="bg-white dark:bg-gray-850 rounded-2xl border border-gray-150 dark:border-gray-800 shadow-sm p-6">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-4 border-b border-gray-100 dark:border-gray-800 pb-3">Client Information</h3>
                <div class="space-y-3">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-gray-400 mt-0.5 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $this->quote->client->name }}</p>
                            @if($this->quote->client->company_name)
                                <p class="text-xs text-gray-500">{{ $this->quote->client->company_name }}</p>
                            @endif
                        </div>
                    </div>
                    @if($this->quote->client->email)
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            <a href="mailto:{{ $this->quote->client->email }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">{{ $this->quote->client->email }}</a>
                        </div>
                    @endif
                    @if($this->quote->client->phone)
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                            <a href="tel:{{ $this->quote->client->phone }}" class="text-sm text-gray-600 dark:text-gray-300">{{ $this->quote->client->phone }}</a>
                        </div>
                    @endif
                </div>
                
                <div class="mt-6">
                    <a href="{{ route('clients.show', $this->quote->client->id) }}" wire:navigate class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wide hover:underline">
                        View Client Profile &rarr;
                    </a>
                </div>
            </div>
            
            <div class="bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl border border-indigo-100 dark:border-indigo-800/30 p-6">
                <h3 class="text-sm font-bold text-indigo-900 dark:text-indigo-200 mb-2">Quote Status</h3>
                <p class="text-xs text-indigo-700 dark:text-indigo-300 mb-4 leading-relaxed">
                    Once the client approves this quote, you can mark it as accepted and convert it into a project or invoice.
                </p>
                
                @if($this->quote->status !== 'accepted')
                    <button wire:click="markAs('accepted')" class="w-full px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-sm font-bold transition-all shadow-sm">
                        Mark as Accepted
                    </button>
                @else
                    <div class="flex items-center text-emerald-600 font-bold text-sm bg-emerald-100/50 dark:bg-emerald-900/30 px-3 py-2 rounded-lg">
                        <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                        Accepted on {{ \Carbon\Carbon::parse($this->quote->accepted_at)->format('M d, Y') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
