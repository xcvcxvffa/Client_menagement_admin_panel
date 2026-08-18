@props([
    'options' => [],
    'placeholder' => 'Select an option',
    'id' => null,
    'disabled' => false,
])

@php
    $wireModel = $attributes->wire('model');
@endphp

<div x-data="{
    open: false,
    value: @entangle($wireModel),
    options: {{ json_encode($options) }},
    disabled: {{ json_encode($disabled) }},
    get selectedName() {
        let opt = this.options.find(o => o.id == this.value);
        return opt ? opt.name : '{{ $placeholder }}';
    }
}" 
{{ $attributes->only('class')->merge(['class' => 'relative text-left w-full mt-1']) }}
@click.outside="open = false">
    {{-- Trigger button --}}
    <button type="button" @click="if(!disabled) open = !open" :disabled="disabled"
        class="w-full rounded-xl border-2 bg-white text-sm px-4 py-2.5 flex justify-between items-center transition-all duration-150 shadow-sm outline-none disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-50"
        :class="open ? 'border-black text-gray-900' : 'border-gray-200 hover:border-gray-300 text-gray-500'">
        <span x-text="selectedName" :class="value ? 'text-gray-900 font-medium' : 'text-gray-400 font-normal'" class="truncate text-sm leading-5"></span>
        {{-- Chevron --}}
        <svg class="h-4 w-4 flex-shrink-0 ml-2 transition-transform duration-200"
             :class="open ? 'rotate-180 text-orange-500' : 'text-gray-400'"
             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    {{-- Dropdown panel --}}
    <div x-show="open" x-cloak style="display: none;"
         class="absolute z-50 left-0 min-w-full w-max max-w-[280px] mt-1.5 bg-white rounded-2xl border border-gray-100 shadow-xl p-1.5 space-y-0.5 max-h-60 overflow-y-auto"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 translate-y-1 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-1 scale-95">
        <template x-for="opt in options" :key="opt.id">
            <div @click="value = opt.id; open = false"
                 class="flex items-center justify-between px-4 py-2.5 text-sm cursor-pointer select-none rounded-lg transition-colors duration-100"
                 :class="value == opt.id
                    ? 'bg-orange-50 text-orange-600 font-semibold'
                    : 'text-gray-700 font-normal hover:bg-gray-50 hover:text-gray-900'">
                <span x-text="opt.name" class="leading-5 pr-3"></span>
                <svg x-show="value == opt.id"
                     class="h-4 w-4 flex-shrink-0 text-orange-500"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
        </template>
    </div>
</div>
