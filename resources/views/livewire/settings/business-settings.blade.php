<?php

use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use function Livewire\Volt\{state, mount, usesFileUploads};

usesFileUploads();

state([
    'name' => '',
    'currency' => '',
    'branding_color' => '',
    'address' => '',
    'tax_number' => '',
    'logo' => null,
    'favicon' => null,
]);

mount(function () {
    \Illuminate\Support\Facades\Gate::authorize('view settings');

    $business = Business::find(Auth::user()->current_business_id);
    
    $this->name = $business->name;
    $this->currency = $business->currency;
    $this->branding_color = $business->branding_color;
    $this->address = $business->address;
    $this->tax_number = $business->tax_number;
});

$saveSettings = function () {
    \Illuminate\Support\Facades\Gate::authorize('edit settings');

    $this->validate([
        'name' => 'required|string|max:255',
        'currency' => 'required|string|size:3',
        'branding_color' => 'required|string|max:7',
        'address' => 'nullable|string',
        'tax_number' => 'nullable|string|max:255',
        'logo' => 'nullable|image|max:2048',
        'favicon' => 'nullable|image|mimes:ico,png,jpg,jpeg|max:1024',
    ]);

    $business = Business::find(Auth::user()->current_business_id);
    
    if ($this->logo) {
        if ($business->logo_path) { Storage::disk('public')->delete($business->logo_path); }
        $business->logo_path = $this->logo->store('branding', 'public');
    }
    
    if ($this->favicon) {
        if ($business->favicon_path) { Storage::disk('public')->delete($business->favicon_path); }
        $business->favicon_path = $this->favicon->store('branding', 'public');
    }

    $business->update([
        'name' => $this->name,
        'currency' => strtoupper($this->currency),
        'branding_color' => $this->branding_color,
        'address' => $this->address,
        'tax_number' => $this->tax_number,
    ]);

    $this->logo = null;
    $this->favicon = null;

    $this->dispatch('notify', message: 'Business settings updated successfully.', type: 'success');
};

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">Business Settings</h2>
            <p class="text-sm text-gray-500 mt-1">Manage your business profile and global preferences.</p>
        </div>
    </div>



    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-150 dark:border-gray-750 shadow-sm overflow-hidden">
        <form wire:submit.prevent="saveSettings">
            <div class="p-6 md:p-8 space-y-6">
                <!-- Branding Uploads -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-gray-150 dark:border-gray-750">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Company Logo</label>
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                                @if($logo)
                                    <img src="{{ $logo->temporaryUrl() }}" class="w-full h-full object-contain">
                                @elseif(\App\Models\Business::find(auth()->user()->current_business_id)->logo_path)
                                    <img src="{{ asset('storage/' . \App\Models\Business::find(auth()->user()->current_business_id)->logo_path) }}" class="w-full h-full object-contain">
                                @else
                                    <span class="text-xs text-gray-400">No Logo</span>
                                @endif
                            </div>
                            <div>
                                <input type="file" wire:model="logo" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer" accept="image/*">
                                <p class="text-xs text-gray-500 mt-1">PNG, JPG up to 2MB</p>
                            </div>
                        </div>
                        @error('logo') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Favicon</label>
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-lg border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden flex-shrink-0">
                                @if($favicon)
                                    <img src="{{ $favicon->temporaryUrl() }}" class="w-full h-full object-contain p-1">
                                @elseif(\App\Models\Business::find(auth()->user()->current_business_id)->favicon_path)
                                    <img src="{{ asset('storage/' . \App\Models\Business::find(auth()->user()->current_business_id)->favicon_path) }}" class="w-full h-full object-contain p-1">
                                @else
                                    <span class="text-[10px] text-gray-400">None</span>
                                @endif
                            </div>
                            <div>
                                <input type="file" wire:model="favicon" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer" accept="image/*, .ico">
                                <p class="text-xs text-gray-500 mt-1">ICO, PNG up to 1MB</p>
                            </div>
                        </div>
                        @error('favicon') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Business Name -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Business Name *</label>
                    <input type="text" wire:model="name" required
                           class="w-full md:w-1/2 px-4 py-3 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white" />
                    @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Currency -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Default Currency (3 Letter Code) *</label>
                        <input type="text" wire:model="currency" required maxlength="3" placeholder="USD, EUR, INR"
                               class="w-full px-4 py-3 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white uppercase" />
                        @error('currency') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Branding Color -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Branding Color (Hex) *</label>
                        <div class="flex items-center gap-3">
                            <input type="color" wire:model="branding_color" required
                                   class="h-11 w-11 rounded-lg border-0 cursor-pointer bg-transparent p-0" />
                            <input type="text" wire:model="branding_color" required
                                   class="flex-1 px-4 py-3 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white uppercase" />
                        </div>
                        @error('branding_color') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Tax Number -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Tax/VAT Number</label>
                    <input type="text" wire:model="tax_number"
                           class="w-full md:w-1/2 px-4 py-3 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white" />
                </div>

                <!-- Address -->
                <div>
                    <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Business Address</label>
                    <textarea wire:model="address" rows="3"
                              class="w-full px-4 py-3 border border-gray-250 dark:border-gray-750 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white"></textarea>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-150 dark:border-gray-750 flex justify-end rounded-b-2xl">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-bold shadow-md transition-colors">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
