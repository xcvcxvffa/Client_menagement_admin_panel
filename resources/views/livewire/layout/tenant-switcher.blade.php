<?php

use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{state};

state([
    'businesses' => fn() => Auth::check() ? Auth::user()->businesses : collect(),
    'currentBusiness' => fn() => Auth::check() ? (Auth::user()->currentBusiness ?? Auth::user()->businesses->first()) : null,
]);

$switchBusiness = function ($businessId) {
    $user = Auth::user();
    if ($user && $user->businesses->contains('id', $businessId)) {
        $user->current_business_id = $businessId;
        $user->save();
        session(['active_business_id' => $businessId]);
        
        // Refresh the page
        $this->redirect(request()->header('Referer', route('dashboard')), navigate: true);
    }
};

?>

<div x-data="{ open: false }" class="relative inline-block text-left w-full">
    @if($currentBusiness)
        <div>
            <button @click="open = !open" type="button" class="inline-flex justify-between items-center w-full rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm px-4 py-3 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-750 focus:outline-none transition-all duration-200" id="menu-button" aria-expanded="true" aria-haspopup="true">
                <span class="flex items-center space-x-3">
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 animate-pulse" style="background-color: {{ $currentBusiness->branding_color ?? '#6366F1' }}"></span>
                    <span class="font-semibold truncate max-w-[150px]">{{ $currentBusiness->name ?? 'Select Business' }}</span>
                </span>
                <svg class="ml-2 h-4 w-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': open }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>

        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" class="origin-top-right absolute right-0 left-0 mt-2 rounded-xl shadow-xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-750 focus:outline-none z-50 divide-y divide-gray-100 dark:divide-gray-700" role="menu" aria-orientation="vertical" aria-labelledby="menu-button" tabindex="-1">
            <div class="py-1.5 px-1.5 max-h-60 overflow-y-auto" role="none">
                @foreach($businesses as $biz)
                    <button wire:click="switchBusiness({{ $biz->id }})" class="w-full text-left rounded-lg px-3 py-2 text-sm flex items-center justify-between transition-colors duration-150 mb-0.5 {{ $biz->id === $currentBusiness->id ? 'font-bold text-[#9a3412] bg-[#fff7ed]' : 'text-gray-700 dark:text-gray-250 hover:bg-gray-100 dark:hover:bg-gray-700' }}" role="menuitem" tabindex="-1">
                        <span class="flex items-center space-x-3 truncate">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $biz->branding_color ?? '#6366F1' }}"></span>
                            <span class="truncate">{{ $biz->name }}</span>
                        </span>
                        @if($biz->id === $currentBusiness->id)
                            <svg class="h-4 w-4 text-[#ea580c] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
