<?php

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Client;
use App\Models\Project;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use function Livewire\Volt\{state, with, mount};

state([
    'client_id' => '',
    'project_id' => '',
    'title' => '',
    'issue_date' => '',
    'due_date' => '',
    'notes' => '',
    
    // Line items array
    'items' => [
        ['description' => '', 'quantity' => 1, 'unit_price' => 0]
    ],
    
    'taxRate' => 18, // Default 18% GST for India example
    
    'subtotal' => 0,
    'tax_total' => 0,
    'total' => 0,
]);

mount(function () {
    $this->issue_date = now()->format('Y-m-d');
    $this->due_date = now()->addDays(14)->format('Y-m-d');
});

$addItem = function () {
    $this->items[] = ['description' => '', 'quantity' => 1, 'unit_price' => 0];
    $this->calculateTotals();
};

$removeItem = function ($index) {
    if (count($this->items) > 1) {
        unset($this->items[$index]);
        $this->items = array_values($this->items); // re-index
        $this->calculateTotals();
    }
};

$calculateTotals = function () {
    $sub = 0;
    foreach ($this->items as $item) {
        $q = (float)($item['quantity'] ?? 0);
        $p = (float)($item['unit_price'] ?? 0);
        $sub += ($q * $p);
    }
    
    $this->subtotal = round($sub, 2);
    $this->tax_total = round(($this->subtotal * ($this->taxRate / 100)), 2);
    $this->total = round($this->subtotal + $this->tax_total, 2);
};

// Listen for updates to recalculate totals, or when client changes, clear project
$updated = function ($property, $value) {
    if (str_starts_with($property, 'items') || $property === 'taxRate') {
        $this->calculateTotals();
    }
    if ($property === 'client_id') {
        $this->project_id = '';
    }
};

$saveInvoice = function () {
    \Illuminate\Support\Facades\Gate::authorize('create invoices');

    $businessId = Auth::user()->current_business_id;

    $this->validate([
        'client_id' => 'required|exists:clients,id,business_id,' . $businessId,
        'project_id' => 'nullable|exists:projects,id,business_id,' . $businessId,
        'title' => 'required|string|max:255',
        'issue_date' => 'required|date',
        'due_date' => 'required|date|after_or_equal:issue_date',
        'items' => 'required|array|min:1',
        'items.*.description' => 'required|string|max:255',
        'items.*.quantity' => 'required|numeric|min:0.1',
        'items.*.unit_price' => 'required|numeric|min:0',
        'taxRate' => 'required|numeric|min:0|max:100',
    ]);

    $this->calculateTotals();

    // Ensure invoice number uniqueness
    do {
        $invoiceNumber = 'INV-' . strtoupper(Str::random(6));
    } while (Invoice::where('invoice_number', $invoiceNumber)->where('business_id', $businessId)->exists());

    $invoice = Invoice::create([
        'business_id' => $businessId,
        'client_id' => $this->client_id,
        'project_id' => $this->project_id ?: null,
        'invoice_number' => $invoiceNumber,
        'title' => $this->title,
        'status' => 'draft',
        'issue_date' => $this->issue_date,
        'due_date' => $this->due_date,
        'subtotal' => $this->subtotal,
        'tax_rate' => $this->taxRate,
        'tax_total' => $this->tax_total,
        'discount_total' => 0,
        'total' => $this->total,
        'amount_paid' => 0,
        'notes' => $this->notes,
    ]);

    foreach ($this->items as $item) {
        $qty = (float)$item['quantity'];
        $price = (float)$item['unit_price'];
        $itemSubtotal = round($qty * $price, 2);
        $itemTax = round($itemSubtotal * ($this->taxRate / 100), 2);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $item['description'],
            'quantity' => $qty,
            'unit_price' => $price,
            'subtotal' => $itemSubtotal,
            'tax' => $itemTax,
            'total' => $itemSubtotal + $itemTax,
        ]);
    }

    ActivityLog::create([
        'description' => "Created invoice #{$invoiceNumber}",
        'subject_id' => $this->client_id,
        'subject_type' => Client::class,
    ]);

    $this->dispatch('notify', message: 'Invoice created successfully.', type: 'success');
    $this->redirectRoute('invoices.show', $invoice->id, navigate: true);
};

with(function () {
    $businessId = Auth::user()->current_business_id;
    $clients = Client::where('business_id', $businessId)->orderBy('name')->get(['id', 'name', 'company_name']);
    $projects = collect();
    
    if ($this->client_id) {
        $projects = Project::where('business_id', $businessId)->where('client_id', $this->client_id)->orderBy('name')->get(['id', 'name']);
    }
    
    return [
        'clients' => $clients,
        'projects' => $projects,
    ];
});

?>

<div>
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Create New Invoice</h2>
        <a href="{{ route('invoices.index') }}" wire:navigate class="text-sm text-gray-500 hover:text-orange-600 dark:hover:text-orange-400">
            &larr; Back to Invoices
        </a>
    </div>

    <form wire:submit.prevent="saveInvoice" class="space-y-6">
        <!-- Main Details Card -->
        <div class="bg-white dark:bg-gray-850 rounded-2xl border border-gray-150 dark:border-gray-800 shadow-sm p-6">
            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-5">Invoice Details</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Client -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Client *</label>
                    <x-custom-select wire:model.live="client_id" placeholder="— Select client —" class="w-full mt-0 z-40"
                        :options="collect([
                            ['id' => '', 'name' => '— Select client —']
                        ])->concat($clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name . ($c->company_name ? ' (' . $c->company_name . ')' : '')]))->toArray()" />
                    @error('client_id') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Project (Optional) -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Project (Optional)</label>
                    <x-custom-select wire:model.live="project_id" placeholder="— No Project —" class="w-full mt-0 z-30"
                        wire:key="project-dropdown-{{ $this->client_id }}"
                        :disabled="empty($this->client_id)"
                        :options="collect([
                            ['id' => '', 'name' => '— No Project —']
                        ])->concat($projects->map(fn($p) => ['id' => $p->id, 'name' => $p->name]))->toArray()" />
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Invoice Title *</label>
                    <input type="text" wire:model="title" required placeholder="e.g. Website Redesign - Phase 1"
                           class="w-full px-4 py-3 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-orange-500 focus:border-orange-500 dark:text-white" />
                    @error('title') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Tax Rate -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Tax Rate (%) *</label>
                    <input type="number" wire:model.live="taxRate" required min="0" max="100" step="0.1"
                           class="w-full px-4 py-3 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-orange-500 focus:border-orange-500 dark:text-white" />
                    @error('taxRate') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Issue Date -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Issue Date *</label>
                    <x-date-picker wire:model="issue_date" placeholder="dd-mm-yyyy" class="w-full mt-0" />
                    @error('issue_date') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>

                <!-- Due Date -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Due Date *</label>
                    <x-date-picker wire:model="due_date" placeholder="dd-mm-yyyy" class="w-full mt-0" />
                    @error('due_date') <span class="text-xs text-rose-500 block mt-1">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="bg-white dark:bg-gray-850 rounded-2xl border border-gray-150 dark:border-gray-800 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-150 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-800/40">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-wider">Line Items</h3>
            </div>

            <div class="p-6 overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[700px]">
                    <thead>
                        <tr class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b border-gray-150 dark:border-gray-800">
                            <th class="pb-3 w-7/12">Description</th>
                            <th class="pb-3 w-2/12">Quantity</th>
                            <th class="pb-3 w-2/12">Unit Price (₹)</th>
                            <th class="pb-3 w-1/12 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach($items as $index => $item)
                            <tr>
                                <td class="py-4 pr-4">
                                    <input type="text" wire:model="items.{{ $index }}.description" required placeholder="Item description"
                                           class="w-full px-3 py-2 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 dark:text-white" />
                                </td>
                                <td class="py-4 pr-4">
                                    <input type="number" wire:model.live.debounce.300ms="items.{{ $index }}.quantity" required min="0.1" step="0.1"
                                           class="w-full px-3 py-2 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 dark:text-white" />
                                </td>
                                <td class="py-4 pr-4">
                                    <input type="number" wire:model.live.debounce.300ms="items.{{ $index }}.unit_price" required min="0" step="0.01"
                                           class="w-full px-3 py-2 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500 dark:text-white" />
                                </td>
                                <td class="py-4 text-right">
                                    <button type="button" wire:click="removeItem({{ $index }})"
                                            class="p-2 text-gray-400 hover:text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-lg transition-colors"
                                            {{ count($items) <= 1 ? 'disabled' : '' }}>
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="mt-4">
                    <button type="button" wire:click="addItem"
                            class="inline-flex items-center text-sm font-semibold text-orange-600 dark:text-orange-400 hover:text-orange-700 dark:hover:text-orange-300">
                        <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Add Line Item
                    </button>
                </div>
            </div>

            <!-- Totals Area -->
            <div class="bg-gray-50 dark:bg-gray-800/40 p-6 border-t border-gray-150 dark:border-gray-800 flex flex-col md:flex-row justify-between gap-6">
                <div class="w-full md:w-1/2">
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Payment Notes / Bank Details</label>
                    <textarea wire:model="notes" rows="4" placeholder="Bank Name: XYZ, Account No: 1234..."
                              class="w-full px-4 py-3 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-orange-500 focus:border-orange-500 dark:text-white"></textarea>
                </div>

                <div class="w-full md:w-1/3 space-y-3 pt-2">
                    <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                        <span>Subtotal</span>
                        <span class="font-semibold text-gray-900 dark:text-white tabular-nums">₹{{ number_format($subtotal, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                        <span>Tax ({{ $taxRate }}%)</span>
                        <span class="font-semibold text-gray-900 dark:text-white tabular-nums">₹{{ number_format($tax_total, 2) }}</span>
                    </div>
                    <div class="pt-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <span class="font-bold text-gray-900 dark:text-white">Total</span>
                        <span class="text-xl font-extrabold text-orange-600 dark:text-orange-400 tabular-nums">₹{{ number_format($total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-3 bg-orange-600 hover:bg-orange-700 dark:bg-orange-500 dark:hover:bg-orange-650 text-white rounded-xl font-bold transition-all duration-150 shadow-md">
                Generate Invoice
            </button>
        </div>
    </form>
</div>
