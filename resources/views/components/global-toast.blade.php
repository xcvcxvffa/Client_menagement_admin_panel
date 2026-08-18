<div x-data="toastComponent"
     @notify.window="add($event.detail)"
     class="fixed bottom-4 right-4 z-50 flex flex-col gap-3 w-full max-w-sm pointer-events-none"
     style="display: none;"
     x-show="true">
    
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.visible"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:translate-x-full"
             x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="pointer-events-auto w-full bg-white dark:bg-gray-800 shadow-lg rounded-xl pointer-events-auto border border-gray-100 dark:border-gray-700 overflow-hidden flex items-start p-4">
            
            <!-- Icon -->
            <div class="flex-shrink-0 mr-3">
                <template x-if="toast.type === 'success'">
                    <div class="w-8 h-8 rounded-full bg-green-50 dark:bg-green-900/30 flex items-center justify-center text-green-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </template>
                <template x-if="toast.type === 'error'">
                    <div class="w-8 h-8 rounded-full bg-red-50 dark:bg-red-900/30 flex items-center justify-center text-red-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                </template>
                <template x-if="toast.type === 'info'">
                    <div class="w-8 h-8 rounded-full bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-500">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </template>
            </div>
            
            <!-- Message -->
            <div class="flex-1 w-0 pt-1">
                <p class="text-sm font-semibold text-gray-900 dark:text-white" x-text="toast.message"></p>
                <template x-if="toast.description">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="toast.description"></p>
                </template>
            </div>
            
            <!-- Close Button -->
            <div class="ml-4 flex-shrink-0 flex">
                <button type="button" @click.prevent.stop="remove(toast.id)" class="bg-white dark:bg-transparent rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 cursor-pointer relative z-10">
                    <span class="sr-only">Close</span>
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
        </div>
    </template>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('toastComponent', () => ({
            toasts: [],
            
            init() {
                // Check for session flashes injected from backend on initial page load
                @if(session()->has('message'))
                    this.add({ message: '{{ session("message") }}', type: 'success' });
                @endif
                
                @if(session()->has('error'))
                    this.add({ message: '{{ session("error") }}', type: 'error' });
                @endif
            },

            add(toast) {
                // Can accept string or object.
                let t = typeof toast === 'string' ? { message: toast } : toast;
                
                t.id = Date.now() + Math.random().toString(36).substring(2, 9);
                t.visible = false;
                t.type = t.type || 'success';
                
                this.toasts.push(t);
                
                // Trigger animation
                setTimeout(() => {
                    let index = this.toasts.findIndex(i => i.id === t.id);
                    if(index !== -1) this.toasts[index].visible = true;
                }, 50);

                // Auto dismiss
                setTimeout(() => {
                    this.remove(t.id);
                }, 4000);
            },
            
            remove(id) {
                let index = this.toasts.findIndex(toast => toast.id === id);
                if (index !== -1) {
                    this.toasts[index].visible = false;
                    setTimeout(() => {
                        this.toasts = this.toasts.filter(toast => toast.id !== id);
                    }, 300); // Wait for transition
                }
            }
        }));
    });
</script>
