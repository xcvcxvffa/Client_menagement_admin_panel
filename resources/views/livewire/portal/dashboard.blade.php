<?php

use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, with};

with(function () {
    $client = Auth::guard('client')->user()->load(['projects' => function($q) {
        $q->where('status', 'active');
    }, 'invoices' => function($q) {
        $q->where('status', '!=', 'paid');
    }]);

    $totalDue = 0;
    foreach($client->invoices as $invoice) {
        $totalDue += ($invoice->total - $invoice->amount_paid);
    }

    return [
        'client' => $client,
        'activeProjectsCount' => $client->projects->count(),
        'unpaidInvoicesCount' => $client->invoices->count(),
        'totalDue' => $totalDue,
    ];
});

?>

<div class="space-y-6">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-3xl p-8 sm:p-10 text-white shadow-lg relative overflow-hidden">
        <div class="absolute top-0 right-0 p-12 opacity-10 pointer-events-none">
            <svg class="w-64 h-64 transform rotate-12" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L2 22h20L12 2zm0 3.83l6.58 13.17H5.42L12 5.83z"/></svg>
        </div>
        
        <div class="relative z-10">
            <h2 class="text-3xl font-black mb-2 tracking-tight">Hello, {{ $client->name }}!</h2>
            <p class="text-indigo-100 max-w-xl text-lg">Welcome to your client portal. Here you can review active projects, approve quotations, and pay outstanding invoices.</p>
        </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-150 dark:border-gray-750 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Active Projects</p>
                <p class="text-3xl font-black text-gray-900 dark:text-white">{{ $activeProjectsCount }}</p>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-150 dark:border-gray-750 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Unpaid Invoices</p>
                <p class="text-3xl font-black text-gray-900 dark:text-white">{{ $unpaidInvoicesCount }}</p>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 dark:text-amber-400">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-2xl p-6 border border-gray-150 dark:border-gray-750 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-sm font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-1">Total Due</p>
                <p class="text-3xl font-black text-rose-600 dark:text-rose-400">₹{{ number_format($totalDue, 2) }}</p>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-rose-50 dark:bg-rose-900/30 flex items-center justify-center text-rose-600 dark:text-rose-400">
                <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </div>
        </div>
    </div>

    <!-- Active Projects List -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-150 dark:border-gray-750 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-150 dark:border-gray-750 bg-gray-50/50 dark:bg-gray-800/40">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Active Projects</h3>
        </div>
        
        @if($client->projects->count() > 0)
            <div class="divide-y divide-gray-150 dark:divide-gray-750">
                @foreach($client->projects as $project)
                    <div class="p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h4 class="text-lg font-bold text-gray-900 dark:text-white">{{ $project->name }}</h4>
                            <p class="text-sm text-gray-500 mt-1 line-clamp-2 max-w-2xl">{{ $project->description }}</p>
                        </div>
                        <div class="text-sm font-semibold text-gray-500">
                            Started on {{ \Carbon\Carbon::parse($project->start_date)->format('M d, Y') }}
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="p-10 text-center text-gray-500">
                You have no active projects at the moment.
            </div>
        @endif
    </div>
</div>
