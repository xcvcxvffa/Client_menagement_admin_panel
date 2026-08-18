<?php

use App\Models\Quote;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, with, mount};

state([
    'search' => '',
    'statusFilter' => 'all',
]);

$deleteQuote = function ($id) {
    \Illuminate\Support\Facades\Gate::authorize('delete quotes');
    $quote = Quote::find($id);
    if ($quote) {
        $quoteNumber = $quote->quote_number;
        $clientId = $quote->client_id;
        
        $quote->items()->delete();
        $quote->delete();

        ActivityLog::create([
            'description' => "Deleted quote #{$quoteNumber}",
            'subject_id' => $clientId,
            'subject_type' => App\Models\Client::class,
        ]);
        
        $this->dispatch('notify', message: 'Quote deleted successfully.', type: 'success');
    }
};

with(function () {
    $query = Quote::with('client');

    if ($this->search) {
        $query->where(function($q) {
            $q->where('quote_number', 'like', "%{$this->search}%")
              ->orWhere('title', 'like', "%{$this->search}%")
              ->orWhereHas('client', fn($c) => $c->where('name', 'like', "%{$this->search}%"));
        });
    }

    if ($this->statusFilter !== 'all') {
        $query->where('status', $this->statusFilter);
    }

    $quotes = $query->latest()->paginate(15);

    return [
        'quotes' => $quotes,
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
                       placeholder="Search quotes, clients..."
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:text-white transition-all duration-200" />
            </div>

            <!-- Status Filter -->
            <x-custom-select wire:model.live="statusFilter" placeholder="All Statuses" class="w-full sm:w-44 mt-0 z-40"
                :options="[
                    ['id' => 'all', 'name' => 'All Statuses'],
                    ['id' => 'draft', 'name' => 'Draft'],
                    ['id' => 'sent', 'name' => 'Sent'],
                    ['id' => 'accepted', 'name' => 'Accepted'],
                    ['id' => 'rejected', 'name' => 'Rejected']
                ]" />
        </div>

        @can('create quotes')
        <a href="{{ route('quotes.create') }}" wire:navigate
           class="inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-650 text-white text-sm font-semibold rounded-xl transition-all duration-150 shadow-md">
            <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Create Quote
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

    <!-- Quotes Table -->
    <div class="bg-white dark:bg-gray-850 rounded-2xl border border-gray-150 dark:border-gray-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 dark:bg-gray-800/40 border-b border-gray-150 dark:border-gray-800 text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <th class="px-6 py-4">Quote Number</th>
                        <th class="px-6 py-4">Client</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4">Amount</th>
                        <th class="px-6 py-4">Valid Until</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-150 dark:divide-gray-800 text-sm">
                    @forelse($quotes as $quote)
                        <tr class="hover:bg-gray-50/55 dark:hover:bg-gray-800/25 transition-colors duration-150">
                            <td class="px-6 py-4">
                                <a href="{{ route('quotes.show', $quote->id) }}" wire:navigate class="font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                    {{ $quote->quote_number }}
                                </a>
                                @if($quote->title)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $quote->title }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($quote->client)
                                    <span class="font-medium text-gray-900 dark:text-gray-200">{{ $quote->client->name }}</span>
                                    @if($quote->client->company_name)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $quote->client->company_name }}</div>
                                    @endif
                                @else
                                    <span class="text-gray-400 italic">No Client</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @switch($quote->status)
                                    @case('draft')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">Draft</span>
                                        @break
                                    @case('sent')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-400">Sent</span>
                                        @break
                                    @case('accepted')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400">Accepted</span>
                                        @break
                                    @case('rejected')
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400">Rejected</span>
                                        @break
                                @endswitch
                            </td>
                            <td class="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                ₹{{ number_format($quote->total, 2) }}
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                @if($quote->valid_until)
                                    {{ \Carbon\Carbon::parse($quote->valid_until)->format('M d, Y') }}
                                    @if(\Carbon\Carbon::parse($quote->valid_until)->isPast() && in_array($quote->status, ['draft', 'sent']))
                                        <span class="block text-[10px] font-bold text-rose-500 uppercase mt-0.5">Expired</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end space-x-2">
                                    @can('view quotes')
                                    <a href="{{ route('quotes.show', $quote->id) }}" wire:navigate
                                       class="p-1.5 text-gray-500 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                       title="View Quote">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    @endcan
                                    
                                    @can('export quotes')
                                    <a href="{{ route('quotes.pdf', $quote->id) }}" target="_blank"
                                       class="p-1.5 text-gray-500 hover:text-emerald-600 dark:hover:text-emerald-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                                       title="Download PDF">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                    </a>
                                    @endcan

                                    @can('delete quotes')
                                    <x-confirm-action action="deleteQuote({{ $quote->id }})" title="Delete Quote" message="Are you sure you want to delete this quote forever?" buttonText="Delete">
                                        <x-slot:trigger>
                                            <button type="button" class="p-1.5 text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/20 rounded-lg transition-colors" title="Delete Quote">
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
                            <td colspan="6" class="px-6 py-16 text-center text-gray-400 dark:text-gray-500">
                                <svg class="w-14 h-14 mx-auto stroke-1 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-sm mt-3 font-medium">No quotes found</p>
                                <p class="text-xs mt-1 text-gray-400">Create your first quote to send to a client.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($quotes->hasPages())
            <div class="px-6 py-4 border-t border-gray-150 dark:border-gray-800">
                {{ $quotes->links() }}
            </div>
        @endif
    </div>
</div>
