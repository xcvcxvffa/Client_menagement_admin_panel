<?php

use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public $roles = [];
    public $permissions = [];
    public $groupedPermissions = [];
    
    public $showModal = false;
    public $isEditing = false;
    public $editingRoleId = null;
    
    public $name = '';
    public $selectedPermissions = [];

    public function mount()
    {
        Gate::authorize('view roles');
        $this->loadRoles();
        $this->permissions = Permission::orderBy('name')->get();
    }

    public function loadRoles()
    {
        $this->roles = Role::where('business_id', session('active_business_id'))->with('permissions')->get();
    }

    public function createRole()
    {
        Gate::authorize('create roles');
        $this->resetForm();
        $this->showModal = true;
    }

    public function editRole($id)
    {
        Gate::authorize('edit roles');
        $this->resetForm();
        $role = Role::where('business_id', session('active_business_id'))->findOrFail($id);
        
        if ($role->name === 'Admin') {
            $this->dispatch('notify', message: 'The default Admin role cannot be edited directly.', type: 'error');
            return;
        }

        $this->isEditing = true;
        $this->editingRoleId = $role->id;
        $this->name = $role->name;
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();
        $this->showModal = true;
    }

    public function saveRole()
    {
        if ($this->isEditing) {
            Gate::authorize('edit roles');
        } else {
            Gate::authorize('create roles');
        }
        
        $this->validate([
            'name' => 'required|string|max:255',
        ]);

        if ($this->name === 'Admin' && !$this->isEditing) {
            $this->addError('name', 'You cannot create another Admin role.');
            return;
        }

        setPermissionsTeamId(session('active_business_id'));

        if ($this->isEditing) {
            $role = Role::where('business_id', session('active_business_id'))->findOrFail($this->editingRoleId);
            $role->update(['name' => $this->name]);
            $role->syncPermissions($this->selectedPermissions);
            $this->dispatch('notify', message: 'Role updated successfully.', type: 'success');
        } else {
            $role = Role::create([
                'name' => $this->name,
                'business_id' => session('active_business_id'),
                'guard_name' => 'web'
            ]);
            $role->syncPermissions($this->selectedPermissions);
            $this->dispatch('notify', message: 'Role created successfully.', type: 'success');
        }

        $this->showModal = false;
        $this->loadRoles();
    }

    public function deleteRole($id)
    {
        Gate::authorize('delete roles');
        $role = Role::where('business_id', session('active_business_id'))->findOrFail($id);
        
        if ($role->name === 'Admin') {
            $this->dispatch('notify', message: 'The Admin role cannot be deleted.', type: 'error');
            return;
        }

        $role->delete();
        $this->dispatch('notify', message: 'Role deleted successfully.', type: 'success');
        $this->loadRoles();
    }

    public function toggleAllPermissions($checked)
    {
        if ($checked) {
            $this->selectedPermissions = collect($this->permissions)->pluck('name')->toArray();
        } else {
            $this->selectedPermissions = [];
        }
    }

    public function toggleModulePermissions($module, $checked)
    {
        $modulePerms = [];
        foreach ($this->permissions as $perm) {
            $name = $perm['name'] ?? $perm->name;
            $parts = explode(' ', $name);
            array_shift($parts);
            $mod = !empty($parts) ? implode(' ', $parts) : 'general';
            $mod = ucwords(str_replace('_', ' ', $mod));
            
            if ($mod === $module) {
                $modulePerms[] = $name;
            }
        }
        
        if ($checked) {
            $this->selectedPermissions = array_values(array_unique(array_merge($this->selectedPermissions, $modulePerms)));
        } else {
            $this->selectedPermissions = array_values(array_diff($this->selectedPermissions, $modulePerms));
        }
    }

    public function resetForm()
    {
        $this->isEditing = false;
        $this->editingRoleId = null;
        $this->name = '';
        $this->selectedPermissions = [];
        $this->resetErrorBag();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-[22px] font-black text-gray-900 dark:text-white tracking-tight">Roles</h2>
            <p class="text-[13px] text-gray-500 mt-1">Manage system roles and their permissions</p>
        </div>
        @can('create roles')
        <button wire:click="createRole" class="px-4 py-2.5 bg-[#ea580c] hover:bg-orange-600 text-white rounded-xl text-sm font-bold shadow-sm transition-colors flex items-center">
            <svg class="w-[18px] h-[18px] mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Create Role
        </button>
        @endcan
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 text-sm flex items-center shadow-sm">
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-50 text-rose-600 border border-rose-100 text-sm flex items-center shadow-sm">
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($roles as $role)
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-150 shadow-sm p-6 flex flex-col h-full">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $role->name }}</h3>
                        <p class="text-xs text-gray-500 mt-1">{{ $role->permissions->count() }} permissions</p>
                    </div>
                    @if($role->name !== 'Admin' && $role->name !== 'Owner')
                        <div class="flex items-center justify-end space-x-2">
                            @can('edit roles')
                            <button wire:click="editRole({{ $role->id }})" class="p-1 text-gray-400 hover:text-indigo-600 rounded transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            </button>
                            @endcan
                            @can('delete roles')
                            <x-confirm-action action="deleteRole({{ $role->id }})" title="Delete Role" message="Are you sure you want to delete this role?" buttonText="Delete">
                                <x-slot:trigger>
                                    <button type="button" class="p-1 text-gray-400 hover:text-rose-600 rounded transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </x-slot:trigger>
                            </x-confirm-action>
                            @endcan
                        </div>
                    @endif
                </div>

                <div class="flex-1">
                    <div class="flex flex-wrap gap-2 mt-4">
                        @if($role->name === 'Admin')
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-700 rounded-lg text-xs font-medium">All Permissions (Full Access)</span>
                        @else
                            @forelse($role->permissions->take(5) as $perm)
                                <span class="px-2.5 py-1 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium">{{ ucwords($perm->name) }}</span>
                            @empty
                                <span class="text-sm text-gray-400 italic">No permissions assigned</span>
                            @endforelse
                            @if($role->permissions->count() > 5)
                                <span class="px-2.5 py-1 bg-gray-50 text-gray-500 rounded-lg text-xs font-medium">+{{ $role->permissions->count() - 5 }} more</span>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Role Modal -->
    <div x-show="$wire.showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-900/50 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="inline-block w-full max-w-2xl text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle overflow-hidden">
                <form wire:submit.prevent="saveRole">
                    <div class="px-6 py-5 border-b border-gray-150 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">{{ $isEditing ? 'Edit Role' : 'Create Role' }}</h3>
                        <button type="button" wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="p-6">
                        <div class="mb-6">
                            <label class="block text-sm font-bold text-gray-700 mb-2">Role Name</label>
                            <input type="text" wire:model="name" required placeholder="e.g. Sales Manager"
                                   class="w-full px-4 py-3 border border-gray-250 bg-white rounded-xl text-sm focus:ring-orange-500 focus:border-orange-500" />
                            @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-3">Permissions</label>
                            
                            @php
                                $groupedPermissions = [];
                                foreach ($permissions as $perm) {
                                    $name = $perm['name'] ?? $perm->name;
                                    $parts = explode(' ', $name);
                                    array_shift($parts);
                                    $module = !empty($parts) ? implode(' ', $parts) : 'general';
                                    $module = ucwords(str_replace('_', ' ', $module));
                                    $groupedPermissions[$module][] = $perm;
                                }
                            @endphp

                            <div x-data="{ search: '' }">
                                <div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <label class="inline-flex items-center cursor-pointer group">
                                        <input type="checkbox" 
                                               wire:click="toggleAllPermissions($event.target.checked)"
                                               {{ count($selectedPermissions) === count($permissions) && count($permissions) > 0 ? 'checked' : '' }}
                                               class="w-5 h-5 text-orange-500 border-gray-300 rounded focus:ring-orange-500">
                                        <span class="ml-2 text-sm font-black text-gray-900 group-hover:text-orange-600 transition-colors">Select All Permissions</span>
                                    </label>

                                    <div class="relative w-full sm:w-64">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <input x-model="search" type="text" class="bg-white border border-gray-250 text-gray-900 text-sm rounded-xl focus:ring-orange-500 focus:border-orange-500 block w-full pl-9 p-2.5" placeholder="Search permissions...">
                                    </div>
                                </div>

                                <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                                @foreach($groupedPermissions as $module => $modulePerms)
                                    @php
                                        $modulePermNames = collect($modulePerms)->map(fn($p) => $p['name'] ?? $p->name)->toArray();
                                        $isModuleSelected = count(array_intersect($selectedPermissions, $modulePermNames)) === count($modulePermNames);
                                        $searchString = strtolower($module . ' ' . implode(' ', $modulePermNames));
                                    @endphp
                                    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 transition-all hover:border-orange-200"
                                         x-data="{ searchString: '{{ $searchString }}' }"
                                         x-show="search === '' || searchString.includes(search.toLowerCase())">
                                        <div class="mb-3 pb-3 border-b border-gray-200 flex items-center">
                                            <label class="inline-flex items-center cursor-pointer group">
                                                <input type="checkbox" 
                                                       wire:click="toggleModulePermissions('{{ $module }}', $event.target.checked)"
                                                       {{ $isModuleSelected ? 'checked' : '' }}
                                                       class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500">
                                                <span class="ml-2 text-sm font-bold text-gray-800 group-hover:text-orange-600 transition-colors">{{ $module }}</span>
                                            </label>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            @foreach($modulePerms as $permission)
                                                <label class="flex items-start cursor-pointer group">
                                                    <div class="flex items-center h-5">
                                                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission['name'] ?? $permission->name }}"
                                                               class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500">
                                                    </div>
                                                    <div class="ml-2 text-sm">
                                                        <span class="font-medium text-gray-700 group-hover:text-gray-900 transition-colors">{{ ucwords(str_replace(['_', '.'], ' ', $permission['name'] ?? $permission->name)) }}</span>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-150 flex justify-end gap-3 rounded-b-2xl">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-sm font-bold shadow-sm">
                            {{ $isEditing ? 'Save Changes' : 'Create Role' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
