@props([
    'placeholder' => 'dd-mm-yyyy',
    'id' => null,
    'disabled' => false,
])

@php
    $wireModel = $attributes->wire('model');
@endphp

@once
<script>
(function() {
    function initDatePicker() {
        if (window.Alpine && !window.Alpine.data('customDatePicker')) {
            window.Alpine.data('customDatePicker', (wireValue, disabled) => ({
                open: false,
                dropUp: false,
                alignRight: false,
                value: wireValue,
                currentYear: new Date().getFullYear(),
                currentMonth: new Date().getMonth(),
                monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                disabled: disabled,

                init() {
                    this.updateFromValue();
                    this.$watch('value', (val) => {
                        this.updateFromValue();
                    });
                },
                updateFromValue() {
                    if (this.value) {
                        let parts = String(this.value).split('T')[0].split('-');
                        if (parts.length === 3) {
                            this.currentYear = parseInt(parts[0]);
                            this.currentMonth = parseInt(parts[1]) - 1;
                        }
                    }
                },
                toggleOpen() {
                    if (this.disabled) return;
                    if (!this.open) {
                        let rect = this.$refs.button.getBoundingClientRect();
                        let container = this.$refs.button.closest('[role="dialog"], .bg-white, form, .modal') || document.body;
                        let containerRect = container.getBoundingClientRect();
                        let rightLimit = containerRect.right > 0 ? containerRect.right : window.innerWidth;
                        let leftLimit = containerRect.left >= 0 ? containerRect.left : 0;

                        let spaceBelow = window.innerHeight - rect.bottom;
                        let spaceRight = rightLimit - rect.left;

                        this.dropUp = spaceBelow < 340;
                        this.alignRight = spaceRight < 300 || (rect.left - leftLimit) > ((rightLimit - leftLimit) / 2);
                    }
                    this.open = !this.open;
                },
                get daysInMonth() {
                    return new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
                },
                get firstDayOfWeek() {
                    return new Date(this.currentYear, this.currentMonth, 1).getDay();
                },
                get prevMonthDays() {
                    let prevDays = new Date(this.currentYear, this.currentMonth, 0).getDate();
                    let days = [];
                    for (let i = this.firstDayOfWeek - 1; i >= 0; i--) {
                        days.push(prevDays - i);
                    }
                    return days;
                },
                get nextMonthDays() {
                    let totalCells = this.prevMonthDays.length + this.daysInMonth;
                    let nextCount = (totalCells % 7 === 0) ? 0 : 7 - (totalCells % 7);
                    let days = [];
                    for (let i = 1; i <= nextCount; i++) {
                        days.push(i);
                    }
                    return days;
                },
                prevMonth() {
                    if (this.currentMonth === 0) {
                        this.currentMonth = 11;
                        this.currentYear--;
                    } else {
                        this.currentMonth--;
                    }
                },
                nextMonth() {
                    if (this.currentMonth === 11) {
                        this.currentMonth = 0;
                        this.currentYear++;
                    } else {
                        this.currentMonth++;
                    }
                },
                selectDay(d) {
                    let m = String(this.currentMonth + 1).padStart(2, '0');
                    let dayStr = String(d).padStart(2, '0');
                    this.value = `${this.currentYear}-${m}-${dayStr}`;
                    this.open = false;
                },
                selectToday() {
                    let today = new Date();
                    this.currentYear = today.getFullYear();
                    this.currentMonth = today.getMonth();
                    this.selectDay(today.getDate());
                },
                isToday(d) {
                    let today = new Date();
                    return today.getFullYear() === this.currentYear && today.getMonth() === this.currentMonth && today.getDate() === d;
                },
                isSelected(d) {
                    if (!this.value) return false;
                    let parts = String(this.value).split('T')[0].split('-');
                    if (parts.length !== 3) return false;
                    return parseInt(parts[0]) === this.currentYear && (parseInt(parts[1]) - 1) === this.currentMonth && parseInt(parts[2]) === d;
                },
                get displayDate() {
                    if (!this.value) return '';
                    let parts = String(this.value).split('T')[0].split('-');
                    if (parts.length !== 3) return this.value;
                    return `${parts[2]}-${parts[1]}-${parts[0]}`;
                }
            }));
        }
    }
    document.addEventListener('alpine:init', initDatePicker);
    initDatePicker();
})();
</script>
@endonce

<div x-data="customDatePicker(@entangle($wireModel), {{ json_encode($disabled) }})" @click.outside="open = false" 
{{ $attributes->only('class')->merge(['class' => 'relative w-full mt-1']) }}>
    <button x-ref="button" type="button" @click="toggleOpen()" :disabled="disabled"
        class="w-full rounded-xl border-2 bg-white text-sm px-2.5 sm:px-3.5 py-2.5 flex items-center justify-between transition-all duration-150 shadow-sm outline-none disabled:opacity-50 disabled:cursor-not-allowed"
        :class="open ? 'border-orange-500 ring-2 ring-orange-500/20 text-gray-900' : 'border-gray-200 hover:border-gray-300 text-gray-700'">
        <span x-text="displayDate || '{{ $placeholder }}'"
              :class="value ? 'text-gray-900 font-medium' : 'text-gray-400 font-normal'"
              class="text-xs sm:text-sm whitespace-nowrap truncate"></span>
        <svg class="h-4 w-4 text-gray-400 flex-shrink-0 ml-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
    </button>

    <div x-show="open" x-cloak style="display: none;"
         class="absolute z-50 bg-white rounded-3xl border border-gray-100 shadow-[0_12px_36px_rgba(0,0,0,0.12)] p-5 w-[290px]"
         :class="[
            dropUp ? 'bottom-full mb-2' : 'top-full mt-2',
            alignRight ? 'right-0 left-auto' : 'left-0 right-auto'
         ]"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-2 scale-95">

        {{-- Month Header --}}
        <div class="flex items-center justify-between mb-4 px-1">
            <button type="button" @click="prevMonth()" class="p-1 rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <span class="text-base font-bold text-gray-900" x-text="monthNames[currentMonth] + ' ' + currentYear"></span>
            <button type="button" @click="nextMonth()" class="p-1 rounded-full text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>

        {{-- Day names header --}}
        <div class="grid grid-cols-7 gap-1 text-center mb-2">
            <template x-for="dayName in daysOfWeek" :key="dayName">
                <div class="text-xs font-bold text-gray-400 py-1" x-text="dayName"></div>
            </template>
        </div>

        {{-- Days grid --}}
        <div class="grid grid-cols-7 gap-1 text-center text-sm">
            <template x-for="d in prevMonthDays" :key="'prev-' + d">
                <div class="py-1.5 text-gray-300 font-normal" x-text="d"></div>
            </template>

            <template x-for="d in daysInMonth" :key="'curr-' + d">
                <button type="button" @click="selectDay(d)"
                    class="relative py-1.5 rounded-full hover:bg-orange-50 hover:text-orange-600 transition-colors focus:outline-none flex flex-col items-center justify-center"
                    :class="{
                        'bg-orange-500 !text-white font-bold shadow-md shadow-orange-500/30': isSelected(d),
                        'text-gray-800 font-semibold': !isSelected(d)
                    }">
                    <span x-text="d"></span>
                    <template x-if="isToday(d)">
                        <span class="w-1 h-1 rounded-full absolute bottom-0.5"
                              :class="isSelected(d) ? 'bg-white' : 'bg-orange-500'"></span>
                    </template>
                </button>
            </template>

            <template x-for="d in nextMonthDays" :key="'next-' + d">
                <div class="py-1.5 text-gray-300 font-normal" x-text="d"></div>
            </template>
        </div>

        {{-- Today action link --}}
        <div class="mt-4 pt-3 border-t border-gray-100 text-left">
            <button type="button" @click="selectToday()" class="text-sm font-bold text-orange-500 hover:text-orange-600 transition-colors">
                Today
            </button>
        </div>
    </div>
</div>
