<?php

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state, computed, uses};

state([
    'search' => '',
    'statusFilter' => 'all',
    'clientFilter' => '',
    'methodFilter' => '',
    
    // Add Payment Modal State
    'showAddPaymentModal' => false,
    'payment_invoice_id' => '',
    'payment_amount' => '',
    'payment_method' => 'bank_transfer',
    'payment_date' => '',
    'payment_notes' => '',
    'payment_client_id' => '',

    // Edit Payment Modal State
    'showEditPaymentModal' => false,
    'edit_payment_id' => null,
    
    // Delete State
    'confirmTrashId' => null,
]);

// When client changes, reset invoice selection and auto-select if only one
$updatedPaymentClientId = function () {
    $this->payment_invoice_id = '';
    
    // Fetch pending invoices for the newly selected client
    $pending = Invoice::where('client_id', $this->payment_client_id)
        ->whereIn('status', ['draft', 'sent', 'partial'])
        ->get();
        
    // Auto-select if exactly 1 pending invoice exists
    if ($pending->count() === 1) {
        $this->payment_invoice_id = $pending->first()->id;
    }
};

// Fetch Clients for filters and modal (scoped to business)
$clients = computed(function () {
    $businessId = Auth::user()->current_business_id;
    return Client::where('business_id', $businessId)->orderBy('name')->get();
});

// Fetch unpaid/partially paid invoices for the selected client (scoped to business)
$pendingInvoices = computed(function () {
    if (!$this->payment_client_id) return collect();
    $businessId = Auth::user()->current_business_id;
    
    return Invoice::where('business_id', $businessId)
        ->where('client_id', $this->payment_client_id)
        ->whereIn('status', ['draft', 'sent', 'partial'])
        ->get();
});

// Fetch Payments with Filtering (scoped to business)
$payments = computed(function () {
    $businessId = Auth::user()->current_business_id;
    $query = Payment::whereHas('invoice', function ($q) use ($businessId) {
        $q->where('business_id', $businessId);
    })->with(['invoice.client', 'invoice.project']);

    if ($this->search) {
        $query->where(function ($q) {
            $q->whereHas('invoice', function ($qi) {
                $qi->where('invoice_number', 'like', '%' . $this->search . '%')
                   ->orWhere('title', 'like', '%' . $this->search . '%')
                   ->orWhereHas('client', function ($qc) {
                       $qc->where('name', 'like', '%' . $this->search . '%');
                   })
                   ->orWhereHas('project', function ($qp) {
                       $qp->where('name', 'like', '%' . $this->search . '%');
                   });
            })->orWhere('payment_method', 'like', '%' . $this->search . '%');
        });
    }

    if ($this->clientFilter) {
        $query->whereHas('invoice', function ($q) {
            $q->where('client_id', $this->clientFilter);
        });
    }

    if ($this->methodFilter) {
        $query->where('payment_method', $this->methodFilter);
    }

    if ($this->statusFilter === 'this_month') {
        $query->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year);
    } elseif ($this->statusFilter === 'last_month') {
        $query->whereMonth('paid_at', now()->subMonth()->month)->whereYear('paid_at', now()->subMonth()->year);
    } elseif ($this->statusFilter === 'year_to_date') {
        $query->whereYear('paid_at', now()->year);
    }

    return $query->orderBy('paid_at', 'desc')->get();
});

// Stat Calculations (scoped to business)
$stats = computed(function () {
    $businessId = Auth::user()->current_business_id;

    // Total Revenue All Time for this business
    $totalRevenue = Payment::whereHas('invoice', function ($q) use ($businessId) {
        $q->where('business_id', $businessId);
    })->sum('amount');
    
    // Revenue This Month for this business
    $revenueThisMonth = Payment::whereHas('invoice', function ($q) use ($businessId) {
        $q->where('business_id', $businessId);
    })->whereMonth('paid_at', now()->month)
        ->whereYear('paid_at', now()->year)
        ->sum('amount');
        
    // Outstanding Invoices for this business (where status is not paid)
    $unpaidInvoices = Invoice::where('business_id', $businessId)->where('status', '!=', 'paid')->get();
    $outstanding = 0;
    foreach($unpaidInvoices as $inv) {
        $paid = $inv->amount_paid;
        $outstanding += max(0, $inv->total - $paid);
    }

    return [
        'totalRevenue' => $totalRevenue,
        'revenueThisMonth' => $revenueThisMonth,
        'outstanding' => $outstanding
    ];
});

$addPayment = function () {
    \Illuminate\Support\Facades\Gate::authorize('create payments');
    
    $businessId = Auth::user()->current_business_id;
    $this->validate([
        'payment_client_id' => 'required|exists:clients,id,business_id,' . $businessId,
        'payment_invoice_id' => 'required|exists:invoices,id,business_id,' . $businessId,
        'payment_amount' => 'required|numeric|min:0.01',
        'payment_method' => 'required|string',
        'payment_date' => 'required|date',
        'payment_notes' => 'nullable|string',
    ]);

    $invoice = Invoice::where('business_id', $businessId)->find($this->payment_invoice_id);
    if ($invoice) {
        $payment = $invoice->payments()->create([
            'amount' => $this->payment_amount,
            'paid_at' => $this->payment_date,
            'payment_method' => $this->payment_method,
            'notes' => $this->payment_notes,
        ]);
        
        // Update Invoice status if fully paid
        $totalPaid = round((float) $invoice->payments()->sum('amount'), 2);
        if ($totalPaid >= round((float) $invoice->total, 2)) {
            $invoice->update(['status' => 'paid', 'amount_paid' => $totalPaid]);
        } else {
            $invoice->update(['status' => 'partial', 'amount_paid' => $totalPaid]);
        }
        
        ActivityLog::create([
            'description' => "Logged global payment of ₹{$this->payment_amount} for Invoice {$invoice->invoice_number}",
            'subject_id' => $payment->id,
            'subject_type' => Payment::class,
        ]);
        
        $this->showAddPaymentModal = false;
        $this->reset(['payment_client_id', 'payment_invoice_id', 'payment_amount', 'payment_date', 'payment_notes']);
        $this->dispatch('notify', message: 'Payment recorded successfully.', type: 'success');
    }
};

$openEditPayment = function ($id) {
    \Illuminate\Support\Facades\Gate::authorize('edit payments');
    
    $businessId = Auth::user()->current_business_id;
    $payment = Payment::whereHas('invoice', function ($q) use ($businessId) {
        $q->where('business_id', $businessId);
    })->with('invoice')->find($id);

    if ($payment) {
        $this->edit_payment_id = $payment->id;
        $this->payment_client_id = $payment->invoice->client_id;
        $this->payment_invoice_id = $payment->invoice_id;
        $this->payment_amount = $payment->amount;
        $this->payment_method = $payment->payment_method;
        $this->payment_date = \Carbon\Carbon::parse($payment->paid_at)->format('Y-m-d');
        $this->payment_notes = $payment->notes;
        
        $this->showEditPaymentModal = true;
    }
};

$updatePayment = function () {
    \Illuminate\Support\Facades\Gate::authorize('edit payments');
    
    $businessId = Auth::user()->current_business_id;
    $this->validate([
        'payment_invoice_id' => 'required|exists:invoices,id,business_id,' . $businessId,
        'payment_amount' => 'required|numeric|min:0.01',
        'payment_method' => 'required|string',
        'payment_date' => 'required|date',
        'payment_notes' => 'nullable|string',
    ]);

    $payment = Payment::whereHas('invoice', function ($q) use ($businessId) {
        $q->where('business_id', $businessId);
    })->find($this->edit_payment_id);

    if ($payment) {
        $oldInvoice = $payment->invoice;

        $payment->update([
            'invoice_id' => $this->payment_invoice_id,
            'amount' => $this->payment_amount,
            'paid_at' => $this->payment_date,
            'payment_method' => $this->payment_method,
            'notes' => $this->payment_notes,
        ]);
        
        // Re-calculate old invoice status
        if ($oldInvoice) {
            $oldPaid = round((float) $oldInvoice->payments()->sum('amount'), 2);
            $oldInvoice->update([
                'amount_paid' => $oldPaid,
                'status'      => ($oldInvoice->total > 0 && $oldPaid >= $oldInvoice->total) ? 'paid' : ($oldPaid > 0 ? 'partial' : 'sent'),
            ]);
        }

        // Re-calculate new/current invoice status
        $invoice = Invoice::where('business_id', $businessId)->find($this->payment_invoice_id);
        if ($invoice) {
            $totalPaid = round((float) $invoice->payments()->sum('amount'), 2);
            if ($totalPaid >= round((float) $invoice->total, 2)) {
                $invoice->update(['status' => 'paid', 'amount_paid' => $totalPaid]);
            } else {
                $invoice->update(['status' => 'partial', 'amount_paid' => $totalPaid]);
            }
        }
        
        $this->showEditPaymentModal = false;
        $this->reset(['edit_payment_id', 'payment_client_id', 'payment_invoice_id', 'payment_amount', 'payment_date', 'payment_notes']);
        $this->dispatch('notify', message: 'Payment updated successfully.', type: 'success');
    }
};

$confirmTrash = function ($id) {
    $this->confirmTrashId = $id;
    $this->dispatch('open-trash-modal');
};

$deletePayment = function () {
    if (!$this->confirmTrashId) return;
    \Illuminate\Support\Facades\Gate::authorize('delete payments');
    
    $businessId = Auth::user()->current_business_id;
    $payment = Payment::whereHas('invoice', function ($q) use ($businessId) {
        $q->where('business_id', $businessId);
    })->find($this->confirmTrashId);

    if ($payment) {
        $invoiceId = $payment->invoice_id;
        $payment->delete();
        
        // Re-calculate invoice status
        $invoice = Invoice::where('business_id', $businessId)->find($invoiceId);
        if ($invoice) {
            $totalPaid = round((float) $invoice->payments()->sum('amount'), 2);
            if ($totalPaid >= round((float) $invoice->total, 2)) {
                $invoice->update(['status' => 'paid', 'amount_paid' => $totalPaid]);
            } else if ($totalPaid > 0) {
                $invoice->update(['status' => 'partial', 'amount_paid' => $totalPaid]);
            } else {
                $invoice->update(['status' => 'sent', 'amount_paid' => 0]);
            }
        }
        
        $this->dispatch('notify', message: 'Payment deleted successfully.', type: 'success');
    }
    
    $this->confirmTrashId = null;
    $this->dispatch('close-trash-modal');
};

?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Payments Overview</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Track revenue and manage client payments centrally.</p>
        </div>
        <div class="flex items-center gap-3">
            @can('create payments')
            <button wire:click="$set('showAddPaymentModal', true)" class="px-4 py-2 bg-[#ea580c] hover:bg-orange-700 text-white rounded-lg text-[13px] font-bold shadow-sm flex items-center gap-2 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                Log Payment
            </button>
            @endcan
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Total Revenue -->
        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Total Revenue</p>
                    <p class="text-2xl font-bold text-gray-900">₹{{ number_format($this->stats['totalRevenue']) }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-500 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
        </div>

        <!-- Revenue This Month -->
        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-blue-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">This Month</p>
                    <p class="text-2xl font-bold text-blue-600">₹{{ number_format($this->stats['revenueThisMonth']) }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                </div>
            </div>
        </div>

        <!-- Outstanding Balance -->
        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm relative overflow-hidden group">
            <div class="absolute inset-0 bg-gradient-to-br from-rose-50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="relative flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Outstanding Balance</p>
                    <p class="text-2xl font-bold text-rose-600">₹{{ number_format($this->stats['outstanding']) }}</p>
                </div>
                <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center text-rose-500 group-hover:scale-110 transition-transform">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="relative flex-1 max-w-md">
            <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search payments, invoices, clients..." 
                   class="w-full pl-9 pr-4 py-2 bg-gray-50 border-transparent focus:bg-white focus:border-orange-500 focus:ring-1 focus:ring-orange-500 rounded-lg text-[13px] transition-colors" />
        </div>
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <x-custom-select wire:model.live="clientFilter" placeholder="All Clients" class="flex-1 sm:flex-none sm:w-48 mt-0"
                :options="collect($this->clients)->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->prepend(['id' => '', 'name' => 'All Clients'])->toArray()" />
            
            <x-custom-select wire:model.live="methodFilter" placeholder="All Methods" class="flex-1 sm:flex-none sm:w-48 mt-0"
                :options="[
                    ['id' => '', 'name' => 'All Methods'],
                    ['id' => 'bank_transfer', 'name' => 'Bank Transfer'],
                    ['id' => 'credit_card', 'name' => 'Credit Card'],
                    ['id' => 'paypal', 'name' => 'PayPal'],
                    ['id' => 'cash', 'name' => 'Cash'],
                    ['id' => 'other', 'name' => 'Other']
                ]" />
            
            <x-custom-select wire:model.live="statusFilter" placeholder="All Time" class="flex-1 sm:flex-none sm:w-48 mt-0"
                :options="[
                    ['id' => 'all', 'name' => 'All Time'],
                    ['id' => 'this_month', 'name' => 'This Month'],
                    ['id' => 'last_month', 'name' => 'Last Month'],
                    ['id' => 'year_to_date', 'name' => 'Year to Date']
                ]" />
        </div>
    </div>

    <!-- Payments Table -->
    <div class="relative">
        <div wire:loading.delay.long>
            <x-skeleton-loader type="table" rows="7" cols="7" />
        </div>
        <div wire:loading.remove.delay.long class="bg-white border border-gray-100 rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="py-3 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="py-3 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Client</th>
                        <th class="py-3 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Project / Retainer</th>
                        <th class="py-3 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Invoice</th>
                        <th class="py-3 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Method</th>
                        <th class="py-3 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider text-right">Amount</th>
                        <th class="py-3 px-4 text-[11px] font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($this->payments as $payment)
                        <tr class="hover:bg-gray-50/50 transition-colors group">
                            <td class="py-3 px-4">
                                <span class="text-[13px] font-medium text-gray-900">{{ \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y') }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-[13px] text-gray-700 font-medium">{{ $payment->invoice?->client?->name ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-[13px] text-gray-500">{{ $payment->invoice?->project?->name ?? '-' }}</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                    {{ $payment->invoice?->invoice_number ?? '-' }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-[12px] text-gray-500 capitalize">{{ str_replace('_', ' ', $payment->payment_method) }}</span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <span class="text-[14px] font-bold text-emerald-600">₹{{ number_format($payment->amount) }}</span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    @can('edit payments')
                                    <button wire:click="openEditPayment({{ $payment->id }})" class="p-1.5 text-gray-400 hover:text-orange-600 hover:bg-orange-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </button>
                                    @endcan
                                    @can('delete payments')
                                    <button wire:click="confirmTrash({{ $payment->id }})" class="p-1.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </div>
                                    <h3 class="text-[14px] font-bold text-gray-900 mb-1">No payments found</h3>
                                    <p class="text-[13px] text-gray-500 max-w-sm">No payments match your current filters. Adjust your search or log a new payment.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
    </div>

    <!-- Modals -->

    <!-- Add/Edit Payment Modal -->
    @if($showAddPaymentModal || $showEditPaymentModal)
    <div class="fixed inset-0 z-[80] overflow-y-auto">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true" wire:click="$set('{{ $showAddPaymentModal ? 'showAddPaymentModal' : 'showEditPaymentModal' }}', false)">
                <div class="absolute inset-0 bg-gray-500 opacity-75 backdrop-blur-sm"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-visible shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                <!-- Premium Header -->
                <div class="relative bg-gradient-to-br from-orange-500 to-orange-600 px-7 py-6 rounded-t-2xl overflow-hidden">
                    <!-- Decorative circles -->
                    <div class="absolute -top-6 -right-6 w-28 h-28 bg-white/10 rounded-full"></div>
                    <div class="absolute -bottom-4 -left-4 w-16 h-16 bg-white/10 rounded-full"></div>
                    <div class="relative flex items-center gap-4">
                        <div class="w-11 h-11 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-white tracking-tight">
                                {{ $showAddPaymentModal ? 'Log New Payment' : 'Edit Payment' }}
                            </h2>
                            <p class="text-xs text-orange-100 mt-0.5">Track revenue and manage client payments centrally</p>
                        </div>
                    </div>
                </div>

                <div class="px-7 py-6">
                    <div class="space-y-5">
                        
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Select Client</label>
                            <!-- Custom Client Dropdown - uses wire:click for proper Livewire reactivity -->
                            <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                                <button type="button" @click="open = !open"
                                    :class="open ? 'border-orange-500 ring-2 ring-orange-500/20' : 'border-gray-200'"
                                    class="w-full flex items-center justify-between px-4 py-3 rounded-xl border bg-white text-sm font-medium focus:outline-none transition-all duration-150 shadow-sm">
                                    <span class="{{ $payment_client_id ? 'text-gray-900' : 'text-gray-400' }}">
                                        {{ $payment_client_id ? $this->clients->find($payment_client_id)?->name : '-- Choose Client --' }}
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
                                     class="absolute z-50 mt-1.5 w-full bg-white rounded-xl shadow-2xl border border-gray-100 p-1.5 space-y-0.5 max-h-60 overflow-y-auto"
                                     style="display:none;">
                                    <button type="button"
                                        wire:click="$set('payment_client_id', '')"
                                        @click="open = false"
                                        class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 {{ !$payment_client_id ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-500 hover:bg-gray-50' }}">
                                        <span>-- Choose Client --</span>
                                        @if(!$payment_client_id)
                                            <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </button>
                                    @foreach($this->clients as $c)
                                    <button type="button"
                                        wire:click="$set('payment_client_id', '{{ $c->id }}')"
                                        @click="open = false"
                                        class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 {{ $payment_client_id == $c->id ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                        <span>{{ $c->name }}</span>
                                        @if($payment_client_id == $c->id)
                                            <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                            @error('payment_client_id') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Select Invoice</label>
                            @if(!$payment_client_id)
                                <div class="w-full flex items-center gap-2 px-4 py-3 rounded-xl border border-gray-100 bg-gray-50 text-sm text-gray-400 cursor-not-allowed">
                                    <svg class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    -- Choose Invoice --
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">Please select a client first to see their pending invoices.</p>
                            @elseif($this->pendingInvoices->isEmpty())
                                <div class="w-full flex items-center gap-2 px-4 py-3 rounded-xl border border-amber-100 bg-amber-50 text-sm font-medium text-amber-600">
                                    No unpaid invoices for this client.
                                </div>
                            @else
                            <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                                <button type="button" @click="open = !open"
                                    :class="open ? 'border-orange-500 ring-2 ring-orange-500/20' : 'border-gray-200'"
                                    class="w-full flex items-center justify-between px-4 py-3 rounded-xl border bg-white text-sm font-medium focus:outline-none transition-all duration-150 shadow-sm">
                                    <span class="truncate {{ $payment_invoice_id ? 'text-gray-900' : 'text-gray-400' }}">
                                        @if($payment_invoice_id)
                                            @php $selInv = $this->pendingInvoices->find($payment_invoice_id); @endphp
                                            {{ $selInv ? $selInv->invoice_number.' ('.$selInv->project?->name.')' : '-- Choose Invoice --' }}
                                        @else
                                            -- Choose Invoice --
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
                                     class="absolute z-50 mt-1.5 w-full bg-white rounded-xl shadow-2xl border border-gray-100 p-1.5 space-y-0.5 max-h-56 overflow-y-auto"
                                     style="display:none;">
                                    @foreach($this->pendingInvoices as $inv)
                                    @php $balance = $inv->total - $inv->payments()->sum('amount'); @endphp
                                    <button type="button"
                                        wire:click="$set('payment_invoice_id', '{{ $inv->id }}')"
                                        @click="open = false"
                                        class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 text-left {{ $payment_invoice_id == $inv->id ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                        <div>
                                            <div class="font-medium">{{ $inv->invoice_number }}</div>
                                            <div class="text-xs {{ $payment_invoice_id == $inv->id ? 'text-orange-400' : 'text-gray-400' }}">{{ $inv->project?->name ?? 'No Project' }} · Balance: ₹{{ number_format($balance, 2) }}</div>
                                        </div>
                                        @if($payment_invoice_id == $inv->id)
                                            <svg class="w-4 h-4 text-orange-500 flex-shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        @endif
                                    </button>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                            @error('payment_invoice_id') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Amount Paid (₹)</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400 font-semibold text-sm pointer-events-none">₹</span>
                                    <input type="number" wire:model="payment_amount" step="0.01" min="0"
                                        class="w-full pl-8 pr-4 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-900 focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-all shadow-sm"
                                        placeholder="0.00">
                                </div>
                                @error('payment_amount') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Date Paid</label>
                                <x-date-picker wire:model="payment_date" placeholder="Select date" class="w-full mt-0" />
                                @error('payment_date') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Payment Method</label>
                            <div x-data="{ open: false }" @click.outside="open = false" class="relative">
                                <button type="button" @click="open = !open"
                                    :class="open ? 'border-orange-500 ring-2 ring-orange-500/20' : 'border-gray-200'"
                                    class="w-full flex items-center justify-between px-4 py-3 rounded-xl border bg-white text-sm font-medium text-gray-800 focus:outline-none transition-all duration-150 shadow-sm">
                                    <span>
                                        @php
                                            $methods = [
                                                'bank_transfer' => 'Bank Transfer',
                                                'credit_card' => 'Credit Card',
                                                'paypal' => 'PayPal',
                                                'cash' => 'Cash',
                                                'other' => 'Other'
                                            ];
                                        @endphp
                                        {{ $methods[$payment_method] ?? 'Select Method' }}
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
                                     class="absolute z-50 bottom-full mb-2 w-full bg-white rounded-xl shadow-2xl border border-gray-100 p-1.5 space-y-0.5"
                                     style="display:none;">
                                    @foreach($methods as $val => $label)
                                        <button type="button"
                                            wire:click="$set('payment_method', '{{ $val }}')"
                                            @click="open = false"
                                            class="w-full flex items-center justify-between px-4 py-2.5 text-sm rounded-lg transition-colors duration-100 {{ $payment_method === $val ? 'bg-orange-50 text-orange-600 font-semibold' : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900' }}">
                                            <span>{{ $label }}</span>
                                            @if($payment_method === $val)
                                                <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            </div>
                            @error('payment_method') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-500 mb-1.5 uppercase tracking-widest">Notes (Optional)</label>
                            <textarea wire:model="payment_notes" rows="2" class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-all resize-none shadow-sm" placeholder="Any additional notes..."></textarea>
                            @error('payment_notes') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    
                    <!-- Premium Footer -->
                    <div class="flex items-center justify-between mt-6 pt-5 border-t border-gray-100 gap-3">
                        <button type="button" wire:click="$set('{{ $showAddPaymentModal ? 'showAddPaymentModal' : 'showEditPaymentModal' }}', false)"
                            class="flex-1 px-4 py-2.5 text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl transition-all">
                            Cancel
                        </button>
                        <button type="button" wire:click="{{ $showAddPaymentModal ? 'addPayment' : 'updatePayment' }}"
                            class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-bold text-white bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 rounded-xl shadow-sm shadow-orange-200 transition-all">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            {{ $showAddPaymentModal ? 'Log Payment' : 'Save Changes' }}
                        </button>
                    </div>
                </div>
        </div>
    </div>
    @endif

    <!-- Delete Confirmation Modal -->
    <div x-data="{ open: @entangle('confirmTrashId') }" x-show="open" class="fixed inset-0 z-[100] overflow-y-auto" style="display: none;">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="open" class="fixed inset-0 transition-opacity" aria-hidden="true" @click="open = false">
                <div class="absolute inset-0 bg-gray-500 opacity-75 backdrop-blur-sm"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="open" class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-bold text-gray-900">Delete Payment</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">Are you sure you want to delete this payment record? The corresponding invoice balance will be updated automatically. This action cannot be undone.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" wire:click="deletePayment" class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-rose-600 text-base font-bold text-white hover:bg-rose-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Delete Payment
                    </button>
                    <button type="button" @click="open = false" class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
