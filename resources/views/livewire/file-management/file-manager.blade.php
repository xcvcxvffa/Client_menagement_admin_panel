@php
function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
@endphp

<!-- Main Wrapper with Gray background (Single Root Element for Livewire) -->
<div class="bg-gray-50/50 min-h-[calc(100vh-64px)] w-full p-4 lg:p-8">
    <style>
        main > div.max-w-7xl {
            max-width: 100% !important;
        }
        main.p-4, main.sm\:p-6, main.lg\:p-8 {
            padding: 0 !important;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
    
    <!-- Premium Card Container -->
    <div class="bg-white rounded-3xl shadow-xl shadow-gray-200/50 border border-gray-100 flex flex-col lg:flex-row h-full overflow-hidden animate-fade-in-up">
        
        <!-- LEFT SIDEBAR -->
        <div class="w-full lg:w-64 bg-[#fcfcfd] border-r border-gray-100/80 flex flex-col shrink-0 relative z-10">
            <div class="p-6 pb-4">
                <button wire:click="$dispatch('open-modal', 'new-folder-modal')" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 px-4 rounded-xl flex items-center justify-center gap-2 transition-all duration-300 shadow-lg shadow-orange-500/30 hover:shadow-orange-500/50 hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    Create New
                </button>
            </div>
            
            <div class="flex-1 overflow-y-auto custom-scrollbar px-4 space-y-1">
                <p class="px-2 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest mb-3 mt-2">My Documents</p>
                
                <button wire:click="setActiveTab('all')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-bold transition-all duration-300 {{ $activeTab === 'all' ? 'bg-orange-50 text-orange-600 shadow-sm' : 'text-gray-500 hover:bg-gray-100/50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5 {{ $activeTab === 'all' ? 'text-orange-500' : 'text-gray-400' }}" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M20 5h-9.586L8.707 3.293A.997.997 0 0 0 8 3H4c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h16c1.103 0 2-.897 2-2V7c0-1.103-.897-2-2-2z"/>
                    </svg>
                    All Folder
                </button>
                
                <div x-data="{ open: true }">
                    <button @click="open = !open" wire:click="setActiveTab('recent')" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl text-[13px] font-bold transition-all duration-300 {{ $activeTab === 'recent' ? 'bg-orange-50 text-orange-600 shadow-sm' : 'text-gray-500 hover:bg-gray-100/50 hover:text-gray-900' }}">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 {{ $activeTab === 'recent' ? 'text-orange-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            Recent Files
                        </div>
                        <svg class="w-3.5 h-3.5 {{ $activeTab === 'recent' ? 'text-orange-500' : 'text-gray-400' }} transition-transform duration-300" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-collapse class="pl-11 pr-3 py-1 space-y-1">
                        <button wire:click="setFilterType('document')" class="w-full flex items-center justify-between text-[11px] font-semibold text-gray-500 hover:text-orange-600 py-1.5 transition-colors">
                            <div class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> DOC</div>
                            @if($filterType === 'document') <svg class="w-3 h-3 text-orange-500" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> @endif
                        </button>
                        <button wire:click="setFilterType('image')" class="w-full flex items-center justify-between text-[11px] font-semibold text-gray-500 hover:text-orange-600 py-1.5 transition-colors">
                            <div class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-red-400"></span> IMG</div>
                            @if($filterType === 'image') <svg class="w-3 h-3 text-orange-500" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> @endif
                        </button>
                        <button wire:click="setFilterType('video')" class="w-full flex items-center justify-between text-[11px] font-semibold text-gray-500 hover:text-orange-600 py-1.5 transition-colors">
                            <div class="flex items-center gap-2.5"><span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span> VID</div>
                            @if($filterType === 'video') <svg class="w-3 h-3 text-orange-500" fill="currentColor" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> @endif
                        </button>
                    </div>
                </div>
                

                <button wire:click="setActiveTab('shared')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-bold transition-all duration-300 {{ $activeTab === 'shared' ? 'bg-orange-50 text-orange-600 shadow-sm' : 'text-gray-500 hover:bg-gray-100/50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5 {{ $activeTab === 'shared' ? 'text-orange-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    Shared
                </button>
                
                <button wire:click="setActiveTab('starred')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-bold transition-all duration-300 {{ $activeTab === 'starred' ? 'bg-orange-50 text-orange-600 shadow-sm' : 'text-gray-500 hover:bg-gray-100/50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5 {{ $activeTab === 'starred' ? 'text-orange-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    Starred
                </button>

                <button wire:click="setActiveTab('trash')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-bold transition-all duration-300 {{ $activeTab === 'trash' ? 'bg-orange-50 text-orange-600 shadow-sm' : 'text-gray-500 hover:bg-gray-100/50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5 {{ $activeTab === 'trash' ? 'text-red-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Trash
                </button>
                
                <button wire:click="setActiveTab('settings')" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-[13px] font-bold transition-all duration-300 {{ $activeTab === 'settings' ? 'bg-orange-50 text-orange-600 shadow-sm' : 'text-gray-500 hover:bg-gray-100/50 hover:text-gray-900' }}">
                    <svg class="w-5 h-5 {{ $activeTab === 'settings' ? 'text-orange-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Setting
                </button>

                <p class="px-2 text-[10px] font-extrabold text-gray-400 uppercase tracking-widest mb-2 mt-4 pt-4 border-t border-gray-100">Favourite Files</p>
                <button wire:click="setFilterType('document')" class="w-full flex items-center gap-2 px-3 py-1.5 text-[12px] font-semibold text-gray-500 hover:text-orange-600 transition-colors">
                    <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> DOC
                </button>
                <button wire:click="setFilterType('image')" class="w-full flex items-center gap-2 px-3 py-1.5 text-[12px] font-semibold text-gray-500 hover:text-orange-600 transition-colors">
                    <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg> IMG
                </button>
            </div>
            
            <!-- Drag and Drop Upload Zone in Sidebar -->
            <div class="p-5 mt-auto bg-gray-50/30 rounded-bl-3xl border-t border-gray-100">

                <div x-data="{ isDropping: false }"
                     @dragover.prevent="isDropping = true"
                     @dragleave.prevent="isDropping = false"
                     @drop.prevent="isDropping = false; $refs.fileInputSidebar.files = $event.dataTransfer.files; $refs.fileInputSidebar.dispatchEvent(new Event('change'))"
                     class="border-2 border-dashed rounded-2xl p-5 text-center transition-all duration-300 cursor-pointer"
                     :class="isDropping ? 'border-orange-500 bg-orange-50 shadow-inner' : 'border-gray-200 bg-white hover:border-orange-300 hover:bg-orange-50/50 hover:shadow-sm'"
                     @click="$refs.fileInputSidebar.click()">
                     
                    <input type="file" wire:model="uploadFiles" x-ref="fileInputSidebar" class="hidden" multiple>
                    
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-3 transition-all duration-300" :class="isDropping ? 'bg-orange-100 text-orange-600 scale-110' : 'bg-gray-50 text-gray-400'">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2a5 5 0 0 0-5 5v2H5a3 3 0 0 0-3 3v8a3 3 0 0 0 3 3h14a3 3 0 0 0 3-3v-8a3 3 0 0 0-3-3h-2V7a5 5 0 0 0-5-5zm0 2a3 3 0 0 1 3 3v2H9V7a3 3 0 0 1 3-3z"/>
                        </svg>
                    </div>
                    <p class="text-[12px] font-bold text-gray-700">Drop Files Here</p>
                    <p class="text-[10px] text-gray-400 mt-1">or click to browse</p>
                    <div wire:loading wire:target="uploadFiles" class="text-[11px] text-orange-600 mt-2 font-bold animate-pulse">Uploading...</div>
                </div>
            </div>
        </div>

         <!-- CENTER MAIN CONTENT -->
        <div class="flex-1 overflow-y-auto custom-scrollbar p-6 lg:p-10 bg-white relative"
             x-data="{ 
                selectedItems: [], 
                uploading: false,
                processing: false,
                progress: 0,
                fileCount: 0,
                get allSelected() { 
                    const checkboxes = document.querySelectorAll('.bulk-checkbox'); 
                    return checkboxes.length > 0 && this.selectedItems.length === checkboxes.length; 
                }, 
                toggleAll() { 
                    const checkboxes = document.querySelectorAll('.bulk-checkbox'); 
                    const willSelect = !this.allSelected; 
                    this.selectedItems = []; 
                    if (willSelect) { 
                        checkboxes.forEach(cb => this.selectedItems.push(cb.value)); 
                    } 
                } 
             }" 
             @items-deleted.window="selectedItems = []"
             @files-uploaded.window="setTimeout(() => { uploading = false; processing = false; progress = 0; fileCount = 0; }, 1000);"
             x-on:livewire-upload-start="uploading = true; processing = false; progress = 0; fileCount = ($event.detail?.files?.length) || $refs.headerFileInput?.files?.length || $refs.fileInputSidebar?.files?.length || 1;"
             x-on:livewire-upload-finish="processing = true; progress = 100;"
             x-on:livewire-upload-error="uploading = false; processing = false; progress = 0; alert('Upload failed. You may have exceeded the server limits (e.g., too many files at once or file too large).');"
             x-on:livewire-upload-progress="progress = $event.detail.progress; if(progress >= 100) { processing = true; }"
        >
            
            <!-- Upload Progress Bar (Toast Message) -->
            <div x-show="uploading" style="display: none;" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4" class="fixed bottom-8 right-8 z-[100] w-96 bg-white border border-gray-100 rounded-2xl shadow-2xl p-5 flex flex-col gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center animate-spin shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    </div>
                    <div>
                        <p class="text-[14px] font-black text-gray-800" x-text="processing ? 'Processing ' + fileCount + ' file(s)...' : 'Uploading ' + fileCount + ' file(s)...'"></p>
                        <p class="text-[12px] font-semibold text-gray-500 mt-0.5" x-text="processing ? 'Saving to your folder, please wait.' : 'Please don\'t close this window.'"></p>
                    </div>
                </div>
                <div class="w-full">
                    <div class="flex justify-between text-[11px] font-bold text-gray-500 mb-2">
                        <span>Progress</span>
                        <span x-text="progress + '%'"></span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-orange-500 h-2.5 rounded-full transition-all duration-300" :style="'width: ' + progress + '%'"></div>
                    </div>
                </div>
            </div>

            @if(in_array($activeTab, ['all', 'recent', 'starred']))
            <!-- Top Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-10">
                <div class="bg-white border border-gray-100/80 rounded-2xl p-5 flex items-center gap-5 hover:shadow-xl hover:shadow-gray-200/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer group" wire:click="setFilterType('document')">
                    <div class="w-12 h-12 rounded-xl bg-orange-50 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-6 h-6 text-orange-400" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm6 1.5L18.5 10H12V3.5z"/></svg>
                    </div>
                    <div class="flex-1 text-right">
                        <h4 class="text-xl font-black text-gray-800">{{ $stats['docs']['count'] }}</h4>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Files</p>
                    </div>
                </div>
                
                <div class="bg-white border border-gray-100/80 rounded-2xl p-5 flex items-center gap-5 hover:shadow-xl hover:shadow-gray-200/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer group" wire:click="setFilterType('image')">
                    <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-6 h-6 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                    </div>
                    <div class="flex-1 text-right">
                        <h4 class="text-xl font-black text-gray-800">{{ $stats['images']['count'] }}</h4>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Files</p>
                    </div>
                </div>

                <div class="bg-white border border-gray-100/80 rounded-2xl p-5 flex items-center gap-5 hover:shadow-xl hover:shadow-gray-200/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer group" wire:click="setFilterType('video')">
                    <div class="w-12 h-12 rounded-xl bg-blue-50 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-6 h-6 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                    </div>
                    <div class="flex-1 text-right">
                        <h4 class="text-xl font-black text-gray-800">{{ $stats['videos']['count'] }}</h4>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Files</p>
                    </div>
                </div>

                <div class="bg-white border border-gray-100/80 rounded-2xl p-5 flex items-center gap-5 hover:shadow-xl hover:shadow-gray-200/40 hover:-translate-y-1 transition-all duration-300 cursor-pointer group" wire:click="setFilterType('audio')">
                    <div class="w-12 h-12 rounded-xl bg-yellow-50 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                        <svg class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                    </div>
                    <div class="flex-1 text-right">
                        <h4 class="text-xl font-black text-gray-800">{{ $stats['audio']['count'] }}</h4>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">Files</p>
                    </div>
                </div>
            </div>
            @endif

            @if($activeTab === 'settings')
                <div class="h-full flex flex-col items-center justify-center text-center animate-fade-in-up">
                    <div class="w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mb-6 border-4 border-white shadow-[0_0_15px_rgba(0,0,0,0.05)]">
                        <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h2 class="text-2xl font-black text-gray-900 capitalize mb-2">Settings</h2>
                    <p class="text-gray-500 font-semibold text-sm max-w-sm mb-8">
                        This section is currently empty. More features will be added here soon.
                    </p>
                </div>
            @else



            <!-- Breadcrumbs -->
            @if(!empty($breadcrumbs))
            <div class="inline-flex items-center gap-2 mb-6 bg-white border border-gray-100 shadow-sm rounded-xl px-4 py-2">
                <button wire:click="enterFolder(null)" class="text-gray-400 hover:text-orange-500 transition-colors flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                </button>
                @foreach($breadcrumbs as $crumb)
                    <svg class="w-3.5 h-3.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
                    <button wire:click="goToBreadcrumb({{ $crumb->id }})" class="{{ $loop->last ? 'text-gray-800' : 'text-gray-400 hover:text-orange-500' }} font-bold transition-colors text-[13px]">{{ $crumb->name }}</button>
                @endforeach
            </div>
            @endif

            <!-- Contextual Action Bar (Bulk Select) -->
            <div x-show="selectedItems.length > 0" x-transition class="flex items-center justify-between bg-orange-50 border border-orange-200 rounded-2xl p-4 mb-6 shadow-sm mt-4" style="display: none;">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 font-black text-sm">
                        <span x-text="selectedItems.length"></span>
                    </div>
                    <span class="text-[13px] font-bold text-orange-800">items selected</span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="selectedItems = []" class="px-4 py-2 text-[13px] font-bold text-orange-600 hover:bg-orange-100 rounded-xl transition-colors">Cancel</button>
                    <button @click="$wire.confirmBulkDelete(selectedItems)" class="px-4 py-2 text-[13px] font-bold text-white bg-red-500 hover:bg-red-600 rounded-xl shadow-sm shadow-red-500/30 transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete Selected
                    </button>
                </div>
            </div>

            <!-- Unified List Header -->
            <div x-show="selectedItems.length === 0" class="mb-6 mt-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <h3 class="text-xl font-black text-gray-800">
                            @if(!empty($search)) Search Results
                            @elseif($activeTab === 'recent') Recent Files
                            @elseif($activeTab === 'starred') ⭐ Starred Files
                            @elseif($activeTab === 'shared') 🔗 Shared Files
                            @elseif($activeTab === 'trash') 🗑️ Trash
                            @elseif(!is_null($currentFolderId)) Files in Folder
                            @else All Files
                            @endif
                        </h3>
                        <span class="bg-gray-100 text-gray-500 text-xs font-bold px-2.5 py-1 rounded-lg border border-gray-200 shadow-sm">{{ $folders->count() + $documents->count() }} items</span>
                    </div>
                    
                    @if(in_array($activeTab, ['all', 'recent']) || !is_null($currentFolderId))
                        <div>
                            <input type="file" wire:model="uploadFiles" x-ref="headerFileInput" class="hidden" multiple>
                            <button @click="$refs.headerFileInput.click()" class="px-5 py-2 bg-orange-500 text-white rounded-xl text-[13px] font-bold hover:bg-orange-600 transition-colors shadow-sm shadow-orange-500/30 flex items-center gap-2 whitespace-nowrap">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                <span>Upload File</span>
                            </button>
                        </div>
                    @endif
                </div>

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center">
                        @if(in_array($activeTab, ['all', 'recent', 'starred']))
                        <div class="bg-white border border-gray-200 rounded-2xl p-1.5 flex shadow-sm gap-1">
                            <button wire:click="setActiveTab('all')" class="px-5 py-1.5 text-[13px] font-bold rounded-xl transition-all {{ $activeTab === 'all' ? 'text-orange-500 bg-orange-50' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">All</button>
                            <button wire:click="setActiveTab('recent')" class="px-5 py-1.5 text-[13px] font-bold rounded-xl transition-all {{ $activeTab === 'recent' ? 'text-orange-500 bg-orange-50' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">Recents</button>
                            <button wire:click="setActiveTab('starred')" class="px-5 py-1.5 text-[13px] font-bold rounded-xl transition-all {{ $activeTab === 'starred' ? 'text-orange-500 bg-orange-50' : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">Starred</button>
                        </div>
                        @endif
                    </div>

                <div class="flex items-center gap-3">
                    @if($activeTab !== 'trash')
                    <!-- Search Bar -->
                    <div class="relative hidden lg:block w-64">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input wire:model.live.debounce.300ms="search" type="text" class="block w-full pl-9 pr-3 py-2 border {{ !empty($search) ? 'border-orange-500 ring-1 ring-orange-500' : 'border-gray-200' }} rounded-xl leading-5 bg-white placeholder-gray-400 focus:outline-none focus:ring-1 focus:ring-orange-500 focus:border-orange-500 text-sm font-semibold text-gray-700 shadow-sm transition-all" placeholder="Search files & folders...">
                    </div>

                    <!-- Sort Dropdown -->
                    <div class="relative w-48" x-data="{ sortOpen: false, selected: '{{ $sortBy }}' }">
                        <button @click="sortOpen = !sortOpen" type="button" class="w-full flex items-center justify-between bg-white border px-4 py-2 rounded-xl shadow-sm text-[13px] font-medium text-gray-700 transition-all" :class="sortOpen ? 'border-orange-500 ring-1 ring-orange-500' : 'border-gray-200 hover:border-gray-300'">
                            <span class="truncate">{{ str_replace('_', ' ', Str::title(str_replace('name_', 'A to Z ', str_replace('date_', 'Date ', str_replace('size_', 'Size ', $sortBy))))) }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform shrink-0" :class="sortOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="sortOpen" @click.outside="sortOpen = false" x-transition.opacity style="display: none;" class="absolute right-0 mt-1.5 w-48 bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-gray-100 z-30 p-1.5 max-h-64 overflow-y-auto">
                            @php
                                $sortOptions = [
                                    'name_asc' => 'A to Z',
                                    'name_desc' => 'Z to A',
                                    'date_desc' => 'Newest',
                                    'date_asc' => 'Oldest',
                                    'size_desc' => 'Size (Largest)',
                                    'size_asc' => 'Size (Smallest)',
                                ];
                            @endphp
                            @foreach($sortOptions as $val => $label)
                            <div wire:click="$set('sortBy', '{{ $val }}'); sortOpen = false;" class="px-3 py-2.5 text-[13px] font-medium cursor-pointer transition-colors flex items-center justify-between rounded-lg {{ $sortBy === $val ? 'bg-[#fff6f0] text-[#d97743]' : 'text-gray-600 hover:bg-gray-50' }}">
                                <span class="truncate">{{ $label }}</span>
                                @if($sortBy === $val)
                                <svg class="w-4 h-4 text-[#d97743] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- List/Grid Toggle -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-1 flex shadow-sm">
                        <button wire:click="$set('viewMode', 'list')" class="p-1.5 rounded-lg transition-all {{ $viewMode === 'list' ? 'bg-white text-orange-500 shadow-sm' : 'text-gray-400 hover:text-gray-600' }}">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                        <button wire:click="$set('viewMode', 'grid')" class="p-1.5 rounded-lg transition-all {{ $viewMode === 'grid' ? 'bg-white text-orange-500 shadow-sm' : 'text-gray-400 hover:text-gray-600' }}">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        </button>
                    </div>
                    @endif

                    @if($activeTab === 'trash' && ($documents->count() > 0 || $folders->count() > 0))
                        <button wire:click="confirmEmptyTrash" class="px-4 py-2 bg-red-500 text-white rounded-xl text-sm font-bold hover:bg-red-600 transition-colors shadow-sm">Empty Trash</button>
                    @endif
                    

                </div>
            </div>
            </div>

            @if($documents->count() > 0 || $folders->count() > 0)
            @if($viewMode === 'list')
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm">
                <div class="overflow-x-auto lg:overflow-visible pb-32 lg:pb-4">
                    <table class="w-full text-left border-collapse min-w-[700px]">
                        <thead>
                            <tr class="bg-white border-b border-gray-100">
                                <th class="p-4 pl-6 w-12">
                                    <input type="checkbox" class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500 cursor-pointer" @click="toggleAll()" :checked="allSelected">
                                </th>
                                <th class="p-4 text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Name</th>
                                <th class="p-4 text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Date modified</th>
                                <th class="p-4 text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Size</th>
                                <th class="p-4 text-[11px] font-extrabold text-gray-400 uppercase tracking-wider">Owner</th>
                                <th class="p-4 pr-6 text-[11px] font-extrabold text-gray-400 uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <!-- Render Folders -->
                            @foreach($folders as $folder)
                            <tr class="hover:bg-gray-50/80 transition-colors duration-200 group cursor-pointer" wire:click="enterFolder({{ $folder->id }})">
                                <td class="p-4 pl-6 w-12" @click.stop>
                                    <input type="checkbox" value="folder:{{ $folder->id }}" class="bulk-checkbox w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500 cursor-pointer" x-model="selectedItems">
                                </td>
                                <td class="p-4 flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center shrink-0 border border-orange-100">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20 5h-9.586L8.707 3.293A.997.997 0 0 0 8 3H4c-1.103 0-2 .897-2 2v14c0 1.103.897 2 2 2h16c1.103 0 2-.897 2-2V7c0-1.103-.897-2-2-2z"/></svg>
                                    </div>
                                    <span class="text-[13px] font-bold text-gray-800 truncate max-w-[250px] group-hover:text-orange-600 transition-colors" title="{{ $folder->name }}">
                                        {{ $folder->name }}
                                    </span>
                                </td>
                                <td class="p-4 text-[13px] font-semibold text-gray-500">{{ $folder->created_at->format('M d, Y') }}</td>
                                <td class="p-4 text-[13px] font-bold text-gray-400">--</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-orange-500 flex items-center justify-center text-[10px] font-bold text-white shadow-sm shrink-0">
                                            {{ strtoupper(substr($folder->client?->name ?? $folder->project?->name ?? Auth::user()->name ?? 'U', 0, 2)) }}
                                        </div>
                                        <span class="text-[12px] font-bold text-gray-700 truncate max-w-[120px]">
                                            {{ $folder->client?->company_name ?? $folder->client?->name ?? $folder->project?->name ?? Auth::user()->name ?? 'Unknown' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 pr-6 text-right relative" x-data="{ menuOpen: false }">
                                    <button @click.stop="menuOpen = !menuOpen" @click.outside="menuOpen = false" class="text-gray-400 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100 transition-all focus:outline-none">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                    </button>
                                    <div x-show="menuOpen" style="display: none;" x-transition.opacity.duration.200ms class="absolute right-12 top-8 w-44 bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-gray-100 z-30 p-1.5 text-left overflow-hidden">
                                        @if($activeTab === 'trash')
                                            <button wire:click.stop="restoreItem({{ $folder->id }}, 'folder')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-green-600 hover:bg-green-50 transition-colors rounded-lg">Restore</button>
                                            <div class="h-px bg-gray-50 my-1"></div>
                                            <button wire:click.stop="confirmDelete({{ $folder->id }}, 'folder')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-red-500 hover:bg-red-50 transition-colors rounded-lg">Permanently Delete</button>
                                        @else
                                            <button wire:click.stop="startRename({{ $folder->id }}, 'folder', '{{ addslashes($folder->name) }}')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Rename</button>
                                            <button wire:click.stop="startMove({{ $folder->id }}, 'folder')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Move</button>
                                            <div class="h-px bg-gray-50 my-1"></div>
                                            <button wire:click.stop="confirmDelete({{ $folder->id }}, 'folder')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-red-500 hover:bg-red-50 transition-colors rounded-lg">Delete</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach

                            <!-- Render Documents -->
                            @foreach($documents as $doc)
                            <tr class="hover:bg-gray-50/80 transition-colors duration-200 group">
                                <td class="p-4 pl-6 w-12" @click.stop>
                                    <input type="checkbox" value="document:{{ $doc->id }}" class="bulk-checkbox w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500 cursor-pointer" x-model="selectedItems">
                                </td>
                                <td class="p-4 flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border border-gray-100
                                        @if(str_starts_with($doc->mime_type, 'image/')) bg-green-50 text-green-500
                                        @elseif(str_starts_with($doc->mime_type, 'video/')) bg-red-50 text-red-500
                                        @elseif(str_contains($doc->mime_type, 'pdf') || str_contains($doc->mime_type, 'word') || str_contains($doc->mime_type, 'text') || str_contains($doc->mime_type, 'excel')) bg-blue-50 text-blue-500
                                        @else bg-gray-50 text-gray-500 @endif">
                                        @if(str_starts_with($doc->mime_type, 'image/'))
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                                        @elseif(str_starts_with($doc->mime_type, 'video/'))
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                                        @elseif(str_contains($doc->mime_type, 'pdf') || str_contains($doc->mime_type, 'word') || str_contains($doc->mime_type, 'text') || str_contains($doc->mime_type, 'excel'))
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm6 1.5L18.5 10H12V3.5z"/></svg>
                                        @else
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm6 1.5L18.5 10H12V3.5z"/></svg>
                                        @endif
                                    </div>
                                    <span class="text-[13px] font-bold text-gray-800 truncate max-w-[250px] group-hover:text-orange-600 transition-colors cursor-pointer" title="{{ $doc->original_name }}" wire:click="previewItem({{ $doc->id }})">
                                        @if($doc->is_starred)
                                        <svg class="w-3.5 h-3.5 text-yellow-400 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        @endif
                                        {{ $doc->original_name }}
                                    </span>
                                </td>
                                <td class="p-4 text-[13px] font-semibold text-gray-500">{{ $doc->created_at->format('M d, Y') }}</td>
                                <td class="p-4 text-[13px] font-bold text-gray-600">{{ formatBytes($doc->file_size) }}</td>
                                <td class="p-4">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full bg-orange-500 flex items-center justify-center text-[10px] font-bold text-white shadow-sm shrink-0" title="{{ $doc->user?->name ?? 'Unknown' }}">
                                            {{ strtoupper(substr($doc->user?->name ?? 'U', 0, 2)) }}
                                        </div>
                                        <span class="text-[12px] font-bold text-gray-700 truncate max-w-[120px]">
                                            {{ $doc->user?->name ?? 'Unknown' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-4 pr-6 text-right relative" x-data="{ menuOpen: false }">
                                    <button @click.stop="menuOpen = !menuOpen" @click.outside="menuOpen = false" class="text-gray-400 hover:text-gray-800 p-2 rounded-lg hover:bg-gray-100 transition-all focus:outline-none">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                                    </button>
                                    
                                    <div x-show="menuOpen" style="display: none;" x-transition.opacity.duration.200ms class="absolute right-12 top-8 w-44 bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-gray-100 z-30 p-1.5 text-left overflow-hidden">
                                        @if($activeTab === 'trash')
                                            <button wire:click.stop="restoreItem({{ $doc->id }}, 'document')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-green-600 hover:bg-green-50 transition-colors rounded-lg">Restore</button>
                                            <div class="h-px bg-gray-50 my-1"></div>
                                            <button wire:click.stop="confirmDelete({{ $doc->id }}, 'document')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-red-500 hover:bg-red-50 transition-colors rounded-lg">Permanently Delete</button>
                                        @else
                                            <button wire:click.stop="previewItem({{ $doc->id }})" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Preview</button>
                                            <button wire:click.stop="downloadFile({{ $doc->id }})" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Download</button>
                                            <button wire:click.stop="toggleStar({{ $doc->id }})" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">{{ $doc->is_starred ? 'Unstar' : 'Star' }}</button>
                                            <button wire:click.stop="startRename({{ $doc->id }}, 'document', '{{ addslashes($doc->original_name) }}')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Rename</button>
                                            <button wire:click.stop="startMove({{ $doc->id }}, 'document')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Move</button>
                                            <button wire:click.stop="startShare({{ $doc->id }}, 'document')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Share</button>
                                            <div class="h-px bg-gray-50 my-1"></div>
                                            <button wire:click.stop="confirmDelete({{ $doc->id }}, 'document')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-red-500 hover:bg-red-50 transition-colors rounded-lg">Delete</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @else
            <!-- Grid View -->
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 pb-32">
                <!-- Folders -->
                @foreach($folders as $folder)
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all p-3 cursor-pointer group relative" wire:click="enterFolder({{ $folder->id }})">
                    <div class="absolute top-4 left-4 z-20" @click.stop>
                        <input type="checkbox" value="folder:{{ $folder->id }}" class="bulk-checkbox w-4 h-4 text-orange-500 border-white rounded shadow-sm focus:ring-orange-500 cursor-pointer" x-model="selectedItems">
                    </div>
                    <div class="aspect-[4/3] bg-orange-50 rounded-xl mb-3 flex items-center justify-center group-hover:scale-[1.02] transition-transform">
                        <svg class="w-16 h-16 text-orange-400" fill="currentColor" viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                    </div>
                    <div class="flex items-start justify-between gap-2 px-1">
                        <div class="flex-1 overflow-hidden">
                            <h4 class="text-sm font-bold text-gray-800 truncate" title="{{ $folder->name }}">{{ $folder->name }}</h4>
                            <p class="text-xs font-bold text-gray-400 mt-0.5">{{ $folder->documents_count ?? 0 }} items</p>
                        </div>
                        
                        <!-- Actions Dropdown -->
                        <div class="relative" x-data="{ menuOpen: false }" @click.stop>
                            <button @click.stop="menuOpen = !menuOpen" @click.outside="menuOpen = false" class="text-gray-400 hover:text-gray-800 p-1.5 rounded-lg hover:bg-gray-100 transition-all focus:outline-none">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                            </button>
                            <div x-show="menuOpen" style="display: none;" x-transition.opacity.duration.200ms class="absolute right-0 top-8 w-44 bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-gray-100 z-30 p-1.5 text-left overflow-hidden">
                                @if($activeTab === 'trash')
                                    <button wire:click.stop="restoreItem({{ $folder->id }}, 'folder')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-green-600 hover:bg-green-50 transition-colors rounded-lg">Restore</button>
                                    <div class="h-px bg-gray-50 my-1"></div>
                                    <button wire:click.stop="confirmDelete({{ $folder->id }}, 'folder')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-red-500 hover:bg-red-50 transition-colors rounded-lg">Permanently Delete</button>
                                @else
                                    <button wire:click.stop="startRename({{ $folder->id }}, 'folder', '{{ addslashes($folder->name) }}')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Rename</button>
                                    <button wire:click.stop="startMove({{ $folder->id }}, 'folder')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Move</button>
                                    <div class="h-px bg-gray-50 my-1"></div>
                                    <button wire:click.stop="confirmDelete({{ $folder->id }}, 'folder')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-red-500 hover:bg-red-50 transition-colors rounded-lg">Delete</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

                <!-- Documents -->
                @foreach($documents as $doc)
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-md transition-all p-3 cursor-pointer group relative" wire:click="previewItem({{ $doc->id }})">
                    <div class="absolute top-4 left-4 z-20" @click.stop>
                        <input type="checkbox" value="document:{{ $doc->id }}" class="bulk-checkbox w-4 h-4 text-orange-500 border-white rounded shadow-sm focus:ring-orange-500 cursor-pointer" x-model="selectedItems">
                    </div>
                    <div class="aspect-[4/3] bg-gray-50 rounded-xl mb-3 overflow-hidden flex items-center justify-center relative">
                        @if($doc->file_type === 'image')
                            <img src="{{ Storage::disk($doc->disk)->url($doc->file_path) }}" alt="{{ $doc->original_name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        @elseif($doc->file_type === 'video')
                            <div class="w-full h-full bg-blue-50 flex flex-col items-center justify-center">
                                <svg class="w-12 h-12 text-blue-300 mb-2" fill="currentColor" viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                            </div>
                        @elseif($doc->file_type === 'audio')
                            <div class="w-full h-full bg-yellow-50 flex flex-col items-center justify-center">
                                <svg class="w-12 h-12 text-yellow-300 mb-2" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                            </div>
                        @else
                            <div class="w-full h-full bg-gray-100 flex flex-col items-center justify-center">
                                <svg class="w-12 h-12 text-gray-300 mb-2" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                            </div>
                        @endif
                        
                        @if($doc->is_starred)
                            <div class="absolute top-2 left-2 bg-white/90 backdrop-blur rounded-full p-1 shadow-sm">
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            </div>
                        @endif
                    </div>
                    <div class="flex items-start justify-between gap-2 px-1">
                        <div class="flex-1 overflow-hidden">
                            <h4 class="text-sm font-bold text-gray-800 truncate" title="{{ $doc->original_name }}">{{ $doc->original_name }}</h4>
                            <div class="flex items-center justify-between mt-1">
                                <p class="text-[11px] font-bold text-gray-400">{{ formatBytes($doc->file_size) }}</p>
                                <p class="text-[10px] font-black text-gray-300 uppercase tracking-wider">{{ $doc->extension }}</p>
                            </div>
                        </div>
                        
                        <!-- Actions Dropdown -->
                        <div class="relative" x-data="{ menuOpen: false }" @click.stop>
                            <button @click.stop="menuOpen = !menuOpen" @click.outside="menuOpen = false" class="text-gray-400 hover:text-gray-800 p-1.5 rounded-lg hover:bg-gray-100 transition-all focus:outline-none">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg>
                            </button>
                            <div x-show="menuOpen" style="display: none;" x-transition.opacity.duration.200ms class="absolute right-0 top-8 w-44 bg-white rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-gray-100 z-30 p-1.5 text-left overflow-hidden">
                                @if($activeTab === 'trash')
                                    <button wire:click.stop="restoreItem({{ $doc->id }}, 'document')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-green-600 hover:bg-green-50 transition-colors rounded-lg">Restore</button>
                                    <div class="h-px bg-gray-50 my-1"></div>
                                    <button wire:click.stop="confirmDelete({{ $doc->id }}, 'document')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-red-500 hover:bg-red-50 transition-colors rounded-lg">Permanently Delete</button>
                                @else
                                    <button wire:click.stop="previewItem({{ $doc->id }})" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Preview</button>
                                    <button wire:click.stop="downloadFile({{ $doc->id }})" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Download</button>
                                    <button wire:click.stop="toggleStar({{ $doc->id }})" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">{{ $doc->is_starred ? 'Unstar' : 'Star' }}</button>
                                    <button wire:click.stop="startRename({{ $doc->id }}, 'document', '{{ addslashes($doc->original_name) }}')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Rename</button>
                                    <button wire:click.stop="startMove({{ $doc->id }}, 'document')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Move</button>
                                    <button wire:click.stop="startShare({{ $doc->id }}, 'document')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors rounded-lg">Share</button>
                                    <div class="h-px bg-gray-50 my-1"></div>
                                    <button wire:click.stop="confirmDelete({{ $doc->id }}, 'document')" class="w-full text-left px-3 py-2.5 text-[13px] font-medium text-red-500 hover:bg-red-50 transition-colors rounded-lg">Delete</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
            @else
                @if(empty($search))
                    <div class="flex flex-col items-center justify-center py-20 px-4 border-2 border-dashed border-gray-200 mt-4 rounded-3xl bg-gray-50/50 transition-colors hover:border-orange-200 hover:bg-orange-50/30">
                        <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center shadow-sm mb-4">
                            <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19a2 2 0 01-2-2V7a2 2 0 012-2h4l2 2h4a2 2 0 012 2v1M5 19h14a2 2 0 002-2v-5a2 2 0 00-2-2H9a2 2 0 00-2 2v5a2 2 0 01-2 2z"/></svg>
                        </div>
                        @if($activeTab === 'shared')
                            <h3 class="text-base font-bold text-gray-800 mb-2">No Shared Files</h3>
                            <p class="text-sm font-medium text-gray-400 mb-6 text-center">Files that are shared with you will appear here.</p>
                        @elseif($activeTab === 'starred')
                            <h3 class="text-base font-bold text-gray-800 mb-2">No Starred Files</h3>
                            <p class="text-sm font-medium text-gray-400 mb-6 text-center">Star your important files to find them easily.</p>
                        @elseif($activeTab === 'trash')
                            <h3 class="text-base font-bold text-gray-800 mb-2">Trash is Empty</h3>
                            <p class="text-sm font-medium text-gray-400 mb-6 text-center">Deleted files and folders will appear here.</p>
                        @else
                            <h3 class="text-base font-bold text-gray-800 mb-2">Folder is Empty</h3>
                            <p class="text-sm font-medium text-gray-400 mb-6 text-center">Create a new folder or drag and drop files directly here.</p>
                            <button wire:click="$dispatch('open-modal', 'new-folder-modal')" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-50 hover:border-gray-300 shadow-sm transition-all">Create Folder</button>
                        @endif
                    </div>
                @else
                    <div class="text-center py-20 bg-gray-50/50 rounded-3xl border border-gray-100 mt-4">
                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center shadow-sm mb-4 mx-auto">
                            <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <p class="text-gray-500 text-sm font-bold">No files found matching "{{ $search }}".</p>
                    </div>
                @endif
            @endif
            @endif
        </div>

        <!-- RIGHT SIDEBAR (Chart & Stats) -->
        <div class="w-full lg:w-80 bg-[#fcfcfd] border-l border-gray-100/80 flex flex-col shrink-0 p-8 overflow-y-auto overflow-x-hidden custom-scrollbar relative z-10">
            <h3 class="text-[14px] font-black text-gray-800 mb-8 text-center uppercase tracking-wider">Storage Activity</h3>
            
            <!-- Storage Doughnut Chart (SVG) -->
            <div class="relative w-44 h-44 mx-auto mb-6 flex items-center justify-center">
                <svg class="w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                    <!-- Background Circle -->
                    <path class="text-gray-100" stroke-width="3" stroke="currentColor" fill="none"
                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    <!-- Progress Circle -->
                    @if($stats['used_percentage'] > 0)
                    <path class="text-orange-500" stroke-dasharray="{{ $stats['used_percentage'] }}, 100" stroke-width="3" stroke-linecap="round" stroke="currentColor" fill="none"
                          d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    @endif
                </svg>
                <div class="absolute inset-0 flex items-center justify-center flex-col">
                    <span class="text-3xl font-black text-gray-900">{{ $stats['used_percentage'] }}<span class="text-sm text-gray-400">%</span></span>
                </div>
            </div>
            
            <p class="text-[12px] font-bold text-center text-gray-400 mb-10">{{ formatBytes($stats['total_size']) }} of total {{ formatBytes($stats['max_storage']) }} used</p>
            
            <h3 class="text-[13px] font-black text-gray-800 mb-6">Storage Details</h3>
            
            <div class="space-y-6 flex-1">
                <!-- Images -->
                <div>
                    <div class="flex items-center justify-between text-[12px] mb-2">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                            </div>
                            <div>
                                <span class="font-bold text-gray-800 block">Images</span>
                                <span class="text-[10px] font-bold text-gray-400">{{ $stats['images']['count'] }} Files</span>
                            </div>
                        </div>
                        <span class="font-black text-gray-900">{{ formatBytes($stats['images']['size']) }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-green-500 h-1.5 rounded-full" style="width: {{ $stats['total_size'] > 0 ? ($stats['images']['size'] / $stats['total_size']) * 100 : 0 }}%"></div>
                    </div>
                </div>
                
                <!-- Video -->
                <div>
                    <div class="flex items-center justify-between text-[12px] mb-2">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-red-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                            </div>
                            <div>
                                <span class="font-bold text-gray-800 block">Video</span>
                                <span class="text-[10px] font-bold text-gray-400">{{ $stats['videos']['count'] }} Files</span>
                            </div>
                        </div>
                        <span class="font-black text-gray-900">{{ formatBytes($stats['videos']['size']) }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-red-500 h-1.5 rounded-full" style="width: {{ $stats['total_size'] > 0 ? ($stats['videos']['size'] / $stats['total_size']) * 100 : 0 }}%"></div>
                    </div>
                </div>
                
                <!-- Audio/Music -->
                <div>
                    <div class="flex items-center justify-between text-[12px] mb-2">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-yellow-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 24 24"><path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/></svg>
                            </div>
                            <div>
                                <span class="font-bold text-gray-800 block">Audio</span>
                                <span class="text-[10px] font-bold text-gray-400">{{ $stats['audio']['count'] }} Files</span>
                            </div>
                        </div>
                        <span class="font-black text-gray-900">{{ formatBytes($stats['audio']['size']) }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-yellow-500 h-1.5 rounded-full" style="width: {{ $stats['total_size'] > 0 ? ($stats['audio']['size'] / $stats['total_size']) * 100 : 0 }}%"></div>
                    </div>
                </div>

                <!-- Documents -->
                <div>
                    <div class="flex items-center justify-between text-[12px] mb-2">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm6 1.5L18.5 10H12V3.5z"/></svg>
                            </div>
                            <div>
                                <span class="font-bold text-gray-800 block">Document</span>
                                <span class="text-[10px] font-bold text-gray-400">{{ $stats['docs']['count'] }} Files</span>
                            </div>
                        </div>
                        <span class="font-black text-gray-900">{{ formatBytes($stats['docs']['size']) }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $stats['total_size'] > 0 ? ($stats['docs']['size'] / $stats['total_size']) * 100 : 0 }}%"></div>
                    </div>
                </div>
                
                <!-- Others -->
                <div>
                    <div class="flex items-center justify-between text-[12px] mb-2">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-gray-500" fill="currentColor" viewBox="0 0 24 24"><path d="M6 2a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6H6zm6 1.5L18.5 10H12V3.5z"/></svg>
                            </div>
                            <div>
                                <span class="font-bold text-gray-800 block">Others</span>
                                <span class="text-[10px] font-bold text-gray-400">{{ $stats['others']['count'] }} Files</span>
                            </div>
                        </div>
                        <span class="font-black text-gray-900">{{ formatBytes($stats['others']['size']) }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-gray-400 h-1.5 rounded-full" style="width: {{ $stats['total_size'] > 0 ? ($stats['others']['size'] / $stats['total_size']) * 100 : 0 }}%"></div>
                    </div>
                </div>
            </div>
            
    <!-- Banner Removed -->
        </div>
    </div>

    <!-- Modals -->
    <x-modal name="new-folder-modal" focusable maxWidth="sm">
        <div class="p-6">
            <h2 class="text-xl font-black text-gray-900 mb-4">Create New Folder</h2>
            <div class="mb-4">
                <label class="block text-[13px] font-bold text-gray-700 mb-2">Folder Name</label>
                <input type="text" wire:model.defer="newFolderName" @keydown.enter="$wire.createFolder()" class="w-full border-gray-200 focus:border-orange-500 focus:ring-orange-500 rounded-xl shadow-sm text-sm py-2.5 px-3" placeholder="Enter folder name">
                @error('newFolderName') <span class="text-red-500 text-xs font-semibold mt-2 block">{{ $message }}</span> @enderror
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" x-on:click="$dispatch('close')" class="px-5 py-2.5 text-sm font-bold text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">Cancel</button>
                <button type="button" wire:click="createFolder" class="px-5 py-2.5 text-sm font-bold text-white bg-orange-500 rounded-xl hover:bg-orange-600 shadow-sm shadow-orange-500/30 transition-colors">Create Folder</button>
            </div>
        </div>
    </x-modal>

    <x-modal name="rename-modal" focusable maxWidth="sm">
        <div class="p-6">
            <h2 class="text-xl font-black text-gray-900 mb-4">Rename Item</h2>
            <div class="mb-6">
                <label class="block text-[13px] font-bold text-gray-700 mb-2">New Name</label>
                <input type="text" wire:model.defer="renameName" @keydown.enter="$wire.saveRename()" class="w-full border-gray-200 focus:border-orange-500 focus:ring-orange-500 rounded-xl shadow-sm text-sm py-2.5 px-3">
                @error('renameName') <span class="text-red-500 text-xs font-semibold mt-2 block">{{ $message }}</span> @enderror
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" x-on:click="$dispatch('close')" class="px-5 py-2.5 text-sm font-bold text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">Cancel</button>
                <button type="button" wire:click="saveRename" class="px-5 py-2.5 text-sm font-bold text-white bg-orange-500 rounded-xl hover:bg-orange-600 shadow-sm shadow-orange-500/30 transition-colors">Save Changes</button>
            </div>
        </div>
    </x-modal>

    <x-modal name="move-modal" focusable maxWidth="sm">
        <div class="p-6">
            <h2 class="text-xl font-black text-gray-900 mb-4">Move Item</h2>
            <div class="mb-6">
                <label class="block text-[13px] font-bold text-gray-700 mb-2">Destination Folder</label>
                <x-custom-select wire:model.defer="moveDestinationId" placeholder="Root / My Files" class="w-full mt-0 z-50"
                    :options="collect([
                        ['id' => '', 'name' => 'Root / My Files']
                    ])->concat($allFolders->map(fn($f) => ['id' => $f->id, 'name' => $f->name]))->toArray()" />
                @error('moveDestinationId') <span class="text-red-500 text-xs font-semibold mt-2 block">{{ $message }}</span> @enderror
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" x-on:click="$dispatch('close')" class="px-5 py-2.5 text-sm font-bold text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">Cancel</button>
                <button type="button" wire:click="executeMove" class="px-5 py-2.5 text-sm font-bold text-white bg-orange-500 rounded-xl hover:bg-orange-600 shadow-sm shadow-orange-500/30 transition-colors">Move</button>
            </div>
        </div>
    </x-modal>

    <x-modal name="share-modal" focusable maxWidth="sm">
        <div class="p-6">
            <h2 class="text-xl font-black text-gray-900 mb-4">Share File</h2>
            <div class="mb-6">
                <p class="text-[13px] font-medium text-gray-600 mb-4">Allow team members to view and access this file.</p>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" x-on:click="$dispatch('close')" class="px-5 py-2.5 text-sm font-bold text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">Cancel</button>
                <button type="button" wire:click="executeShare" class="px-5 py-2.5 text-sm font-bold text-white bg-orange-500 rounded-xl hover:bg-orange-600 shadow-sm shadow-orange-500/30 transition-colors">Share</button>
            </div>
        </div>
    </x-modal>

    <x-modal name="preview-modal" focusable maxWidth="4xl">
        <div class="p-4 bg-white rounded-2xl shadow-2xl">
            <div class="flex justify-between items-center mb-4 px-2">
                <h2 class="text-lg font-bold text-gray-900 truncate pr-4">{{ $previewFileName }}</h2>
                <button x-on:click="$dispatch('close')" class="text-gray-400 hover:text-gray-600 p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="rounded-xl overflow-hidden flex items-center justify-center bg-gray-50/50 min-h-[400px]">
                @if($previewFileUrl)
                    @if($previewFileType === 'image')
                        <img src="{{ $previewFileUrl }}" alt="Preview" class="w-full h-auto max-h-[75vh] object-contain rounded-xl">
                    @elseif($previewFileType === 'video')
                        <video src="{{ $previewFileUrl }}" controls class="w-full max-h-[75vh] rounded-xl"></video>
                    @elseif($previewFileType === 'audio')
                        <audio src="{{ $previewFileUrl }}" controls class="w-full"></audio>
                    @elseif($previewFileType === 'document')
                        <iframe src="{{ $previewFileUrl }}" class="w-full h-[75vh] rounded-xl" frameborder="0"></iframe>
                    @else
                        <div class="text-center p-10">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <p class="text-gray-500 font-bold mb-2">Preview not available for this file type.</p>
                            <a href="{{ $previewFileUrl }}" target="_blank" class="text-orange-500 hover:underline text-sm font-semibold">Download instead</a>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </x-modal>
    <x-modal name="delete-modal" focusable maxWidth="sm">
        <div class="p-6 text-center">
            <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4 border-4 border-red-100 shadow-sm">
                <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <h2 class="text-xl font-black text-gray-900 mb-2" x-text="$wire.deleteModalTitle">Delete Item</h2>
            <p class="text-[13px] font-medium text-gray-500 mb-6" x-text="$wire.deleteModalDescription">Are you sure you want to delete this?</p>
            <div class="flex justify-center gap-3">
                <button type="button" x-on:click="$dispatch('close')" class="px-5 py-2.5 text-sm font-bold text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors w-full">Cancel</button>
                <button type="button" wire:click="executeDelete" class="px-5 py-2.5 text-sm font-bold text-white bg-red-500 rounded-xl hover:bg-red-600 shadow-sm shadow-red-500/30 transition-colors w-full">Yes, Delete</button>
            </div>
        </div>
    </x-modal>

    <x-modal name="bulk-delete-modal" focusable maxWidth="sm">
        <div class="p-6 text-center">
            <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4 border-4 border-red-100 shadow-sm">
                <svg class="w-8 h-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <h2 class="text-xl font-black text-gray-900 mb-2" x-text="$wire.bulkDeleteModalTitle">Delete Items</h2>
            <p class="text-[13px] font-medium text-gray-500 mb-6" x-text="$wire.bulkDeleteModalDescription">Are you sure you want to delete these items?</p>
            <div class="flex justify-center gap-3">
                <button type="button" x-on:click="$dispatch('close')" class="px-5 py-2.5 text-sm font-bold text-gray-600 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors w-full">Cancel</button>
                <button type="button" wire:click="executeBulkDelete" class="px-5 py-2.5 text-sm font-bold text-white bg-red-500 rounded-xl hover:bg-red-600 shadow-sm shadow-red-500/30 transition-colors w-full">Yes, Delete</button>
            </div>
        </div>
    </x-modal>
</div>
