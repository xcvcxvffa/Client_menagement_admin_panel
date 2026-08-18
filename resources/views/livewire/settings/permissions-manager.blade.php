<?php

use Livewire\Volt\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

new class extends Component {
    public $permissions = [];
    public $groupedPermissions = [];
    
    public $showModal = false;
    public $isEditing = false;
    public $editingPermissionId = null;
    
    public $name = '';

    public function mount()
    {
        Gate::authorize('view permissions');
        $this->loadPermissions();
    }

    public function loadPermissions()
    {
        $this->permissions = Permission::orderBy('name')->get();
        $this->groupedPermissions = [];
        
        foreach ($this->permissions as $perm) {
            // My permissions are named like "view clients", "edit business settings".
            // So we take the first word as the action, and the rest as the module.
            $parts = explode(' ', $perm->name);
            $action = array_shift($parts); // remove view/edit/etc.
            $module = !empty($parts) ? implode(' ', $parts) : 'general';
            
            if (!isset($this->groupedPermissions[$module])) {
                $this->groupedPermissions[$module] = [];
            }
            $this->groupedPermissions[$module][] = $perm;
        }
    }

    public function createPermission()
    {
        Gate::authorize('create permissions');
        $this->resetForm();
        $this->showModal = true;
    }

    public function editPermission($id)
    {
        Gate::authorize('edit permissions');
        $this->resetForm();
        $permission = Permission::findOrFail($id);
        
        $this->isEditing = true;
        $this->editingPermissionId = $permission->id;
        $this->name = $permission->name;
        $this->showModal = true;
    }

    public function savePermission()
    {
        if ($this->isEditing) {
            Gate::authorize('edit permissions');
        } else {
            Gate::authorize('create permissions');
        }
        
        $this->validate([
            'name' => 'required|string|max:255|unique:permissions,name,' . ($this->isEditing ? $this->editingPermissionId : 'NULL'),
        ]);

        if ($this->isEditing) {
            $permission = Permission::findOrFail($this->editingPermissionId);
            $permission->update(['name' => strtolower(str_replace(' ', '_', $this->name))]);
            
            activity()
               ->causedBy(auth()->user())
               ->performedOn($permission)
               ->log("Permission '{$permission->name}' updated");
               
            $this->dispatch('notify', message: 'Permission updated successfully.', type: 'success');
        } else {
            $permission = Permission::create([
                'name' => strtolower(str_replace(' ', '_', $this->name)),
                'guard_name' => 'web'
            ]);
            
            // Auto assign to super admin
            $superAdmin = Role::where('name', 'Super Admin')->first();
            if ($superAdmin) {
                $superAdmin->givePermissionTo($permission);
            }
            
            activity()
               ->causedBy(auth()->user())
               ->performedOn($permission)
               ->log("Permission '{$permission->name}' created");
               
            $this->dispatch('notify', message: 'Permission created successfully.', type: 'success');
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->showModal = false;
        $this->loadPermissions();
    }

    public function deletePermission($id)
    {
        Gate::authorize('delete permissions');
        $permission = Permission::findOrFail($id);
        $permissionName = $permission->name;
        $permission->delete();
        
        activity()
           ->causedBy(auth()->user())
           ->log("Permission '{$permissionName}' deleted");
           
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $this->dispatch('notify', message: 'Permission deleted successfully.', type: 'success');
        $this->loadPermissions();
    }

    public function resetForm()
    {
        $this->isEditing = false;
        $this->editingPermissionId = null;
        $this->name = '';
        $this->resetErrorBag();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-[22px] font-black text-gray-900 dark:text-white tracking-tight">Permissions</h2>
            <p class="text-[13px] text-gray-500 mt-1">Manage system permissions for access control</p>
        </div>
        @can('create permissions')
        <button wire:click="createPermission" class="px-4 py-2.5 bg-[#ea580c] hover:bg-orange-600 text-white rounded-xl text-sm font-bold shadow-sm transition-colors flex items-center">
            <svg class="w-[18px] h-[18px] mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Create Permission
        </button>
        @endcan
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100 text-sm flex items-center shadow-sm">
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif

    <div class="space-y-6">
        @foreach($groupedPermissions as $module => $perms)
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-150 shadow-sm overflow-hidden">
                <div class="bg-gray-50 dark:bg-gray-700/50 px-6 py-4 border-b border-gray-150 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white capitalize flex items-center">
                        <span class="w-2 h-2 rounded-full bg-orange-500 mr-3"></span>
                        {{ $module }} Module
                    </h3>
                    <span class="text-sm font-medium text-gray-500 bg-white px-3 py-1 rounded-full shadow-sm">{{ count($perms) }} permissions</span>
                </div>
                
                <div class="p-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($perms as $permission)
                        @php 
                            $actionParts = explode(' ', $permission->name);
                            $action = $actionParts[0] ?? $permission->name;
                        @endphp
                        <div class="flex items-center justify-between p-3 border border-gray-100 rounded-xl hover:border-orange-200 hover:bg-orange-50/30 transition-all group">
                            <span class="text-sm font-semibold text-gray-700 capitalize">{{ str_replace('_', ' ', $action) }}</span>
                            <div class="flex items-center justify-end space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                @can('edit permissions')
                                <button wire:click="editPermission({{ $permission->id }})" class="p-1 text-gray-400 hover:text-indigo-600 rounded transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                </button>
                                @endcan
                                @can('delete permissions')
                                <x-confirm-action action="deletePermission({{ $permission->id }})" title="Delete Permission" message="Are you sure you want to delete this permission?" buttonText="Delete">
                                    <x-slot:trigger>
                                        <button type="button" class="p-1 text-gray-400 hover:text-rose-600 rounded transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </x-slot:trigger>
                                </x-confirm-action>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <!-- Permission Modal -->
    <div x-show="$wire.showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-900/50 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="inline-block w-full max-w-lg text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle overflow-hidden">
                <form wire:submit.prevent="savePermission">
                    <div class="px-6 py-5 border-b border-gray-150 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">{{ $isEditing ? 'Edit Permission' : 'Create Permission' }}</h3>
                        <button type="button" wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="p-6">
                        <div class="mb-2">
                            <label class="block text-sm font-bold text-gray-700 mb-2">Permission Name</label>
                            <input type="text" wire:model="name" required placeholder="e.g. users.create"
                                   class="w-full px-4 py-3 border border-gray-250 bg-white rounded-xl text-sm focus:ring-orange-500 focus:border-orange-500" />
                            <p class="text-xs text-gray-500 mt-2">Use dot notation for modules (e.g., <span class="font-bold">module.action</span>)</p>
                            @error('name') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-150 flex justify-end gap-3 rounded-b-2xl">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-sm font-bold shadow-sm">
                            {{ $isEditing ? 'Save Changes' : 'Create Permission' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
