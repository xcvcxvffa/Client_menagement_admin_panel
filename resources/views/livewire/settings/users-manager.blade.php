<?php

use Livewire\Volt\Component;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public $search = '';
    public $roles = [];
    
    public $showModal = false;
    public $editingUserId = null;
    public $selectedRoles = [];
    public $userName = '';

    public function mount()
    {
        $this->loadRoles();
    }

    public function loadRoles()
    {
        $this->roles = Role::whereNull('business_id')
            ->orWhere('business_id', session('active_business_id'))
            ->get();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function editRoles($id)
    {
        $user = User::findOrFail($id);
        $this->editingUserId = $id;
        $this->userName = $user->name;
        
        setPermissionsTeamId(session('active_business_id'));
        $this->selectedRoles = $user->roles->pluck('name')->toArray();
        $this->showModal = true;
    }

    public function saveRoles()
    {
        $user = User::findOrFail($this->editingUserId);
        
        if ($user->hasRole('Super Admin') && !auth()->user()->hasRole('Super Admin')) {
             $this->dispatch('notify', message: 'You cannot modify Super Admin roles.', type: 'error');
             return;
        }

        setPermissionsTeamId(session('active_business_id'));
        $user->syncRoles($this->selectedRoles);
        
        activity()
           ->causedBy(auth()->user())
           ->performedOn($user)
           ->log("Assigned roles to user '{$user->name}'");
           
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->dispatch('notify', message: 'User roles updated successfully.', type: 'success');
        $this->showModal = false;
    }

    public function with()
    {
        // For multi-tenant we could limit to users in the current business
        // Since we don't have a direct scope set up here we just load all or those with team_member relation
        // We'll just load all for now as requested.
        return [
            'users' => User::where('name', 'like', "%{$this->search}%")
                            ->orWhere('email', 'like', "%{$this->search}%")
                            ->with('roles')
                            ->paginate(10)
        ];
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">User Roles Management</h2>
            <p class="text-sm text-gray-500 mt-1">Assign roles and permissions to system users.</p>
        </div>
        <div class="relative w-full sm:w-64">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search users..."
                   class="w-full pl-10 pr-4 py-2 border border-gray-250 bg-white rounded-xl text-sm focus:ring-orange-500 focus:border-orange-500" />
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </div>
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

    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-150 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-150 dark:border-gray-700 text-left text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4">Email</th>
                        <th class="px-6 py-4">Assigned Roles</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-150 dark:divide-gray-700 text-sm text-gray-700 dark:text-gray-300">
                    @foreach($users as $user)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20 transition-colors">
                            <td class="px-6 py-4 flex items-center">
                                @if($user->avatar)
                                    <img src="{{ Storage::url($user->avatar) }}" alt="" class="w-8 h-8 rounded-full object-cover mr-3">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold mr-3 uppercase">
                                        {{ substr($user->name, 0, 1) }}
                                    </div>
                                @endif
                                <span class="font-medium text-gray-900">{{ $user->name }}</span>
                            </td>
                            <td class="px-6 py-4">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($user->roles as $role)
                                        <span class="px-2.5 py-1 rounded-md text-xs font-medium 
                                            {{ $role->name === 'Super Admin' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-700' }}">
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-gray-400 italic text-xs">No roles</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button wire:click="editRoles({{ $user->id }})" class="text-orange-500 hover:text-orange-700 font-medium transition-colors">
                                    Manage Roles
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
            <div class="px-6 py-4 border-t border-gray-150">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- Assign Role Modal -->
    <div x-show="$wire.showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-900/50 backdrop-blur-sm" wire:click="$set('showModal', false)"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="inline-block w-full max-w-lg text-left align-bottom transition-all transform bg-white rounded-2xl shadow-xl sm:my-8 sm:align-middle overflow-hidden">
                <form wire:submit.prevent="saveRoles">
                    <div class="px-6 py-5 border-b border-gray-150 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-gray-900">Assign Roles to {{ $userName }}</h3>
                        <button type="button" wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="p-6">
                        <label class="block text-sm font-bold text-gray-700 mb-3">Available Roles</label>
                        <div class="space-y-3 max-h-64 overflow-y-auto pr-2">
                            @foreach($roles as $role)
                                <label class="flex items-start p-3 border border-gray-150 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" wire:model="selectedRoles" value="{{ $role->name }}"
                                               class="w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <span class="font-medium text-gray-900">{{ $role->name }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-150 flex justify-end gap-3 rounded-b-2xl">
                        <button type="button" wire:click="$set('showModal', false)" class="px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-xl text-sm font-bold shadow-sm">
                            Save Roles
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
