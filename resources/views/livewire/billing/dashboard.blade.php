<?php

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Retainer;
use App\Models\Expense;
use App\Models\Client;
use Carbon\Carbon;
use function Livewire\Volt\{computed, state};

$metrics = computed(function () {
    $currentMonth = Carbon::now()->month;
    $currentYear = Carbon::now()->year;

    // Current Month Income
    $currentMonthIncome = Payment::whereMonth('paid_at', $currentMonth)
        ->whereYear('paid_at', $currentYear)
        ->sum('amount');
        
    // Current Month Expenses
    $currentMonthExpense = Expense::whereMonth('date', $currentMonth)
        ->whereYear('date', $currentYear)
        ->sum('amount');
        
    $currentMonthProfit = $currentMonthIncome - $currentMonthExpense;
    
    // MRR (Monthly Recurring Revenue)
    $activeRetainers = Retainer::where('status', '!=', 'completed')
        ->where('status', '!=', 'cancelled')
        ->get();
        
    $mrr = 0;
    foreach($activeRetainers as $ret) {
        $cycle = $ret->billing_cycle ?? 'monthly';
        if ($cycle === 'monthly') {
            $mrr += $ret->amount;
        } elseif ($cycle === 'quarterly') {
            $mrr += ($ret->amount / 3);
        } elseif ($cycle === 'yearly') {
            $mrr += ($ret->amount / 12);
        }
    }
    
    return [
        'currentMonthIncome' => $currentMonthIncome,
        'currentMonthExpense' => $currentMonthExpense,
        'currentMonthProfit' => $currentMonthProfit,
        'mrr' => $mrr,
        'activeRetainersCount' => $activeRetainers->count(),
    ];
});

$recentInvoices = computed(function () {
    return Invoice::with('client')
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();
});

$recentPayments = computed(function () {
    return Payment::with(['invoice.client'])
        ->orderBy('paid_at', 'desc')
        ->take(5)
        ->get();
});

$upcomingRenewals = computed(function () {
    return Retainer::where('status', '!=', 'completed')
        ->where('status', '!=', 'cancelled')
        ->whereNotNull('renewal_date')
        ->orderBy('renewal_date', 'asc')
        ->take(5)
        ->get();
});

$clientLTV = computed(function () {
    return Client::all()->map(function($client) {
        $totalPaid = Payment::whereHas('invoice', function($q) use ($client) {
            $q->where('client_id', $client->id);
        })->sum('amount');
        
        $totalExpenses = Expense::where('client_id', $client->id)->sum('amount');
        
        return (object)[
            'id' => $client->id,
            'name' => $client->name,
            'ltv' => $totalPaid,
            'profit' => $totalPaid - $totalExpenses
        ];
    })->filter(fn($c) => $c->ltv > 0 || $c->profit < 0)
      ->sortByDesc('ltv')
      ->take(5)
      ->values();
});

?>

<div class="relative w-full">
    <div wire:loading.delay.long class="w-full">
        <x-skeleton-loader type="dashboard" />
    </div>
    <div wire:loading.remove.delay.long class="space-y-6 w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Billing Center</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Your agency's financial command center.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('expenses.index') }}" wire:navigate class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-bold rounded-lg transition-colors shadow-sm">
                Log Expense
            </a>
            <a href="{{ route('payments.index') }}" wire:navigate class="inline-flex items-center justify-center px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white text-sm font-bold rounded-lg transition-colors shadow-sm">
                Log Payment
            </a>
        </div>
    </div>

    <!-- Top Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- MRR -->
        <div class="bg-gradient-to-br from-[#ea580c] to-[#c2410c] rounded-xl p-5 shadow-sm text-white relative overflow-hidden group">
            <div class="absolute inset-0 bg-white opacity-0 group-hover:opacity-10 transition-opacity"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[11px] font-bold text-orange-100 uppercase tracking-wider">MRR</p>
                    <svg class="w-5 h-5 text-orange-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                </div>
                <p class="text-3xl font-bold">₹{{ number_format($this->metrics['mrr']) }}</p>
                <p class="text-xs text-orange-200 mt-1">From {{ $this->metrics['activeRetainersCount'] }} active retainers</p>
            </div>
        </div>

        <!-- Income (Current Month) -->
        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">This Month's Income</p>
                    <svg class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                </div>
                <p class="text-2xl font-bold text-gray-900">₹{{ number_format($this->metrics['currentMonthIncome']) }}</p>
            </div>
        </div>

        <!-- Expense (Current Month) -->
        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-rose-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">This Month's Expense</p>
                    <svg class="w-5 h-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" /></svg>
                </div>
                <p class="text-2xl font-bold text-gray-900">₹{{ number_format($this->metrics['currentMonthExpense']) }}</p>
            </div>
        </div>
        
        <!-- Profit (Current Month) -->
        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm flex flex-col justify-center relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-sky-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Net Profit</p>
                    <svg class="w-5 h-5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                </div>
                <p class="text-2xl font-bold {{ $this->metrics['currentMonthProfit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                    ₹{{ number_format($this->metrics['currentMonthProfit']) }}
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Recent Invoices -->
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-900">Recent Invoices</h3>
                <a href="{{ route('invoices.index') }}" class="text-[12px] font-bold text-orange-600 hover:text-orange-700">View All</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($this->recentInvoices as $invoice)
                <div class="p-4 hover:bg-gray-50/50 transition-colors flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[13px] font-bold text-gray-900">{{ $invoice->invoice_number }}</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider 
                                {{ $invoice->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 
                                  ($invoice->status === 'partial' ? 'bg-blue-100 text-blue-700' : 
                                  ($invoice->status === 'draft' ? 'bg-gray-100 text-gray-700' : 'bg-amber-100 text-amber-700')) }}">
                                {{ $invoice->status }}
                            </span>
                        </div>
                        <p class="text-[12px] text-gray-500">{{ $invoice->client?->name ?? 'Unknown Client' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[14px] font-bold text-gray-900">₹{{ number_format($invoice->total) }}</p>
                        <p class="text-[11px] text-gray-400">{{ \Carbon\Carbon::parse($invoice->created_at)->diffForHumans() }}</p>
                    </div>
                </div>
                @empty
                <div class="p-6 text-center text-sm text-gray-500">No recent invoices found.</div>
                @endforelse
            </div>
        </div>

        <!-- Upcoming Retainer Renewals -->
        <div class="bg-white border border-gray-100 rounded-xl shadow-sm flex flex-col">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-900">Upcoming Renewals</h3>
                <a href="{{ route('retainers.index') }}" class="text-[12px] font-bold text-orange-600 hover:text-orange-700">View Retainers</a>
            </div>
            <div class="divide-y divide-gray-100 flex-1">
                @forelse($this->upcomingRenewals as $retainer)
                <div class="p-4 hover:bg-gray-50/50 transition-colors flex items-center justify-between">
                    <div>
                        <p class="text-[13px] font-bold text-gray-900">{{ $retainer->name }}</p>
                        <p class="text-[12px] text-gray-500 capitalize">{{ $retainer->billing_cycle }} • ₹{{ number_format($retainer->amount) }}</p>
                    </div>
                    <div class="text-right">
                        @php
                            $renewalDate = \Carbon\Carbon::parse($retainer->renewal_date);
                            $isOverdue = $renewalDate->isPast() && !$renewalDate->isToday();
                        @endphp
                        <p class="text-[13px] font-bold {{ $isOverdue ? 'text-rose-600' : 'text-gray-900' }}">
                            {{ $renewalDate->format('M d, Y') }}
                        </p>
                        <p class="text-[11px] {{ $isOverdue ? 'text-rose-500' : 'text-gray-400' }}">{{ $renewalDate->diffForHumans() }}</p>
                    </div>
                </div>
                @empty
                <div class="p-6 text-center text-sm text-gray-500">No upcoming renewals found.</div>
                @endforelse
            </div>
        </div>
        
    </div>

    <!-- Client Lifetime Value (LTV) -->
    <div class="bg-white border border-gray-100 rounded-xl shadow-sm mt-6">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Client Lifetime Value (LTV) & Profitability</h3>
                <p class="text-[11px] text-gray-500 mt-0.5">Top clients by revenue generated vs expenses incurred.</p>
            </div>
            <a href="{{ route('clients.index') }}" class="text-[12px] font-bold text-orange-600 hover:text-orange-700">View Clients</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100 text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3">Client</th>
                        <th class="px-5 py-3 text-right">Lifetime Value (Revenue)</th>
                        <th class="px-5 py-3 text-right">Total Expenses</th>
                        <th class="px-5 py-3 text-right">Net Profit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($this->clientLTV as $client)
                    <tr class="hover:bg-gray-50/30 transition-colors">
                        <td class="px-5 py-3">
                            <span class="text-[13px] font-bold text-gray-900">{{ $client->name }}</span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-[13px] font-semibold text-emerald-600">₹{{ number_format($client->ltv) }}</span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-[13px] font-semibold text-rose-500">₹{{ number_format($client->ltv - $client->profit) }}</span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <span class="text-[13px] font-bold {{ $client->profit >= 0 ? 'text-gray-900' : 'text-rose-600' }}">
                                ₹{{ number_format($client->profit) }}
                            </span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="p-6 text-center text-sm text-gray-500">No client data available yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>
</div>
