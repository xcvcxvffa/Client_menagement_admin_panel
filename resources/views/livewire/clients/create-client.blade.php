<?php

use App\Models\Client;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state};

state([
    'name' => '',
    'email' => '',
    'phone' => '',
    'currency' => 'INR',
]);

$saveClient = function () {
    \Illuminate\Support\Facades\Gate::authorize('create clients');

    $this->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:clients,email',
        'phone' => 'nullable|string|max:50',
        'currency' => 'required|string|max:3',
    ]);

    $client = Client::create([
        'name' => $this->name,
        'email' => $this->email,
        'phone' => $this->phone,
        'currency' => $this->currency,
        'status' => 'active',
        'business_id' => Auth::user()->current_business_id,
    ]);

    ActivityLog::create([
        'description' => "Created client '{$client->name}'",
        'subject_id' => $client->id,
        'subject_type' => Client::class,
        'business_id' => Auth::user()->current_business_id,
        'user_id' => Auth::id(),
    ]);

    return redirect()->route('clients.index')->with('status', 'Client created successfully.');
};

?>

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 mt-6">
        <form wire:submit="saveClient" class="space-y-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Client Name <span class="text-rose-500">*</span></label>
                <input type="text" wire:model="name" required placeholder="John Doe"
                       class="w-full px-4 py-2.5 border border-gray-250 bg-white rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500" />
                @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Email <span class="text-rose-500">*</span></label>
                <input type="email" wire:model="email" required placeholder="client@example.com"
                       class="w-full px-4 py-2.5 border border-gray-250 bg-white rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500" />
                <p class="text-xs text-gray-500 mt-1.5">Used to send invoices, documents, and the client-portal invite.</p>
                @error('email') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Phone Number</label>
                <input type="text" wire:model="phone" placeholder="+1 (555) 123-4567"
                       class="w-full px-4 py-2.5 border border-gray-250 bg-white rounded-lg text-sm focus:ring-orange-500 focus:border-orange-500" />
                @error('phone') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Invoice Currency</label>
                <div x-data="{ 
                        open: false, 
                        selectedId: @entangle('currency'),
                        options: [
                            {id: 'INR', name: 'INR — Indian Rupee (₹) · your currency'},
                            {id: 'USD', name: 'USD — US Dollar ($)'},
                            {id: 'EUR', name: 'EUR — Euro (€)'},
                            {id: 'GBP', name: 'GBP — British Pound (£)'}
                        ]
                    }" class="relative w-full z-40">
                    <button @click="open = !open" @click.away="open = false" type="button" 
                            :class="open ? 'border-black text-gray-900' : 'border-gray-200 hover:border-gray-300 text-gray-500'"
                            class="w-full px-4 py-2.5 bg-white border-2 rounded-xl text-sm flex items-center justify-between transition-all duration-150 shadow-sm outline-none">
                        <span class="text-gray-900 truncate pr-2" x-text="options.find(o => o.id == selectedId)?.name || 'Select a currency'"></span>
                        <svg class="w-4 h-4 flex-shrink-0 transition-transform" :class="open ? 'rotate-180 text-orange-500' : 'text-gray-400'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" style="display: none;"
                         class="absolute z-50 left-0 w-full mt-1.5 bg-white rounded-2xl border border-gray-100 shadow-xl p-1.5 max-h-60 overflow-y-auto space-y-0.5">
                        <template x-for="opt in options" :key="opt.id">
                            <button @click="selectedId = opt.id; open = false" type="button"
                                    :class="selectedId == opt.id ? 'text-orange-600 bg-orange-50 font-semibold' : 'text-gray-700 font-normal hover:bg-gray-50 hover:text-gray-900'"
                                    class="w-full text-left px-3.5 py-2 text-sm rounded-xl flex items-center justify-between transition-colors mb-0.5">
                                <span x-text="opt.name" class="truncate pr-2 leading-5"></span>
                                <svg x-show="selectedId == opt.id" class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                            </button>
                        </template>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-1.5">Invoices for this client will be shown in this currency.</p>
                @error('currency') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
            </div>

            <div class="pt-4 flex items-center justify-end gap-3">
                <a href="{{ route('clients.index') }}" wire:navigate
                   class="px-5 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="px-5 py-2.5 text-sm font-semibold text-white bg-[#ea580c] hover:bg-orange-700 rounded-lg transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Save Client
                </button>
            </div>
        </form>
    </div>
</div>
