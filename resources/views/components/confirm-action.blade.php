@props(['action', 'title' => 'Confirm Action', 'message' => 'Are you sure you want to proceed?', 'buttonText' => 'Confirm', 'buttonColor' => 'rose'])

<div x-data="{ show: false }" class="inline-block">
    <div @click.prevent.stop="show = true">
        {{ $trigger }}
    </div>

    <div x-show="show" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-[2px] transition-opacity" @click="show = false"></div>

        {{-- Compact Modal Card --}}
        <div class="relative w-full max-w-[380px] bg-white rounded-2xl p-5 shadow-xl border border-gray-100 z-10 text-left transform transition-all">
            <h3 class="text-base font-bold text-gray-900 leading-snug">{{ $title }}</h3>
            <p class="mt-1.5 text-xs text-gray-500 leading-relaxed">{{ $message }}</p>
            
            <div class="mt-5 flex items-center justify-end gap-2.5">
                <button type="button" @click="show = false" class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors shadow-sm">
                    Cancel
                </button>
                <button type="button" @click="show = false; @if($action) $wire.{{ $action }} @endif" 
                        class="px-4 py-2 text-white rounded-lg text-xs font-bold shadow-sm transition-colors
                               {{ $buttonColor === 'rose' ? 'bg-rose-600 hover:bg-rose-700 shadow-rose-600/20' : 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-600/20' }}">
                    {{ $buttonText }}
                </button>
            </div>
        </div>
    </div>
</div>
