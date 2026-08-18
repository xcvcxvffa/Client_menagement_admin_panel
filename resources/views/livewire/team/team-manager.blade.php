<?php

use App\Models\User;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\TeamMemberWelcomeMail;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{state, with, on, mount};

state([
    'showModal' => false,
    'name' => '',
    'email' => '',
    'role' => '',
    'salary' => '',
    'notes' => '',
]);

mount(function () {
    \Illuminate\Support\Facades\Gate::authorize('view team');
});

with(function () {
    return [
        'teamMembers' => TeamMember::with('user')
            ->where('business_id', Auth::user()->current_business_id)
            ->get(),
        'availableRoles' => \Spatie\Permission\Models\Role::where(function ($q) {
                                $q->whereNull('business_id')
                                  ->orWhere('business_id', Auth::user()->current_business_id);
                            })->get(),
    ];
});

$addMember = function () {
    \Illuminate\Support\Facades\Gate::authorize('create team');

    $this->validate([
        'email' => 'required|email|max:255',
        'role' => ['required', 'string'],
        'name' => 'nullable|string|max:255',
        'salary' => 'nullable|numeric|min:0',
        'notes' => 'nullable|string|max:1000',
    ]);

    $businessId = Auth::user()->current_business_id;

    // Default name to email prefix if not provided
    $displayName = trim($this->name);
    if (empty($displayName)) {
        $displayName = explode('@', $this->email)[0];
    }

    // Check if user already exists globally
    $user = User::where('email', $this->email)->first();
    $isNewUser = false;
    
    // Auto-generate secure password
    $generatedPassword = Str::random(10);

    // Check if already in this business
    if ($user) {
        $existingMember = TeamMember::where('business_id', $businessId)->where('user_id', $user->id)->first();
        if ($existingMember) {
            $this->addError('email', 'This user is already a member of your team.');
            return;
        }
        
        // Force update the password so the newly generated password emailed to them works
        $user->update([
            'password' => Hash::make($generatedPassword)
        ]);
    } else {
        // Create new user with generated password
        $user = User::create([
            'name' => $displayName,
            'email' => $this->email,
            'password' => Hash::make($generatedPassword),
            'current_business_id' => $businessId,
        ]);
        $isNewUser = true;
    }

    // Add to business
    TeamMember::create([
        'business_id' => $businessId,
        'user_id' => $user->id,
        'role' => $this->role,
        'monthly_salary' => $this->salary ?: null,
        'notes' => $this->notes ?: null,
    ]);

    // Assign Spatie Role
    setPermissionsTeamId($businessId);
    $user->assignRole($this->role);
    
    // Ensure the user has this business in their current_business_id if they didn't have one
    if (!$user->current_business_id) {
        $user->update(['current_business_id' => $businessId]);
    }

    // Send Welcome Email
    try {
        Mail::to($user->email)->send(new TeamMemberWelcomeMail($user, $generatedPassword, Auth::user()->name));
    } catch (\Exception $e) {
        // Log error or ignore if SMTP is not configured
        \Log::error('Failed to send team member welcome email: ' . $e->getMessage());
    }

    $this->reset(['showModal', 'name', 'email', 'role', 'salary', 'notes']);
    
    $this->dispatch('notify', message: 'Team member added successfully! An invitation email has been sent to them.', type: 'success');
};

$updateRole = function ($teamMemberId, $newRole) {
    \Illuminate\Support\Facades\Gate::authorize('edit team');

    if (empty($newRole)) return;
    
    $member = TeamMember::where('business_id', Auth::user()->current_business_id)->findOrFail($teamMemberId);
    
    if ($member->user_id === Auth::id() && $newRole !== 'Admin') {
        setPermissionsTeamId(Auth::user()->current_business_id);
        $adminUsers = User::role('Admin')->where('current_business_id', Auth::user()->current_business_id)->count();
        if ($adminUsers <= 1) {
            $this->dispatch('notify', message: 'You cannot change your role because you are the only Admin.', type: 'error');
            return;
        }
    }

    $member->update(['role' => $newRole]);

    setPermissionsTeamId(Auth::user()->current_business_id);
    $member->user->syncRoles([$newRole]);

    $this->dispatch('notify', message: 'Role updated successfully.', type: 'success');
};

$removeMember = function ($teamMemberId) {
    \Illuminate\Support\Facades\Gate::authorize('delete team');

    $member = TeamMember::where('business_id', Auth::user()->current_business_id)->findOrFail($teamMemberId);
    
    if ($member->user_id === Auth::id()) {
        $this->dispatch('notify', message: 'You cannot remove yourself from the business here.', type: 'error');
        return;
    }
    
    $member->delete();
    $this->dispatch('notify', message: 'Team member removed successfully.', type: 'success');
};

?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <div>
            <h2 class="text-[22px] font-black text-gray-900 dark:text-white tracking-tight">Team Management</h2>
            <p class="text-[13px] text-gray-500 mt-1">Manage your agency team members and their roles</p>
        </div>
        @can('create team')
        <button wire:click="$set('showModal', true)" class="px-4 py-2.5 bg-[#ea580c] hover:bg-orange-600 text-white rounded-xl text-sm font-bold shadow-sm transition-colors flex items-center">
            <svg class="w-[18px] h-[18px] mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            </svg>
            Add Team Member
        </button>
        @endcan
    </div>

    @if (session()->has('message'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/40 text-sm flex items-center shadow-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            <span class="font-medium">{{ session('message') }}</span>
        </div>
    @endif
    
    @if (session()->has('error'))
        <div class="mb-6 p-4 rounded-xl bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400 border border-rose-100 dark:border-rose-900/40 text-sm flex items-center shadow-sm" x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)">
            <span class="font-medium">{{ session('error') }}</span>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 rounded-[14px] border border-gray-150 dark:border-gray-750 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#fafafa] dark:bg-gray-800/40 border-b border-gray-100 dark:border-gray-750">
                        <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-400">Member</th>
                        <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-400">Email</th>
                        <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-400">Role</th>
                        <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-400">Joined</th>
                        <th class="py-4 px-6 text-[11px] font-bold uppercase tracking-wider text-gray-400 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-750">
                    @foreach($teamMembers as $member)
                        <tr class="{{ $member->user_id === Auth::id() ? 'bg-[#fffaf5]' : 'hover:bg-gray-50' }} dark:hover:bg-gray-800/50 transition-colors">
                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-3">
                                    <div class="w-[34px] h-[34px] rounded-full bg-[#ea580c] flex items-center justify-center text-white font-bold text-sm shadow-sm flex-shrink-0">
                                        {{ strtoupper(substr($member->user->name, 0, 1)) }}
                                    </div>
                                    <div class="font-semibold text-[13px] text-gray-900 dark:text-white flex items-center">
                                        {{ $member->user->name }}
                                        @if($member->user_id === Auth::id())
                                            <span class="text-[#ea580c] text-[11px] font-medium ml-1.5">(You)</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="py-4 px-6">
                                <span class="text-[13px] text-gray-500 font-medium">{{ $member->user->email }}</span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="inline-block px-3 py-[3px] bg-[#f3e8ff] text-[#7e22ce] text-[11px] font-bold rounded-full leading-none">
                                    {{ $member->role }}
                                </span>
                            </td>
                            <td class="py-4 px-6">
                                <span class="text-[13px] text-gray-400 font-medium">{{ $member->created_at->format('d/m/Y') }}</span>
                            </td>
                            <td class="py-4 px-6 text-right">
                                @if($member->user_id !== Auth::id())
                                    @can('delete team')
                                    <x-confirm-action action="removeMember({{ $member->id }})" title="Remove Member" message="Are you sure you want to remove this user from the business?" buttonText="Remove">
                                        <x-slot:trigger>
                                            <button type="button" class="text-gray-400 hover:text-rose-500 font-medium transition-colors">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </x-slot:trigger>
                                    </x-confirm-action>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Member Modal -->
    <div x-show="$wire.showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="$wire.showModal" class="fixed inset-0 transition-opacity bg-gray-900/50 backdrop-blur-sm" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="$wire.showModal" @click.away="$wire.showModal = false" class="inline-block w-full max-w-md overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-850 rounded-2xl shadow-xl sm:my-8 sm:align-middle border border-gray-150 dark:border-gray-750">
                <form wire:submit.prevent="addMember">
                    <div class="px-6 py-5 flex items-center justify-between">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">Add Team Member</h3>
                        <button type="button" wire:click="$set('showModal', false)" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    
                    <div class="px-6 pb-6 space-y-5">
                        
                        <!-- Email -->
                        <div>
                            <label class="flex items-center text-[13px] font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                Email *
                            </label>
                            <input type="email" wire:model="email" required placeholder="member@example.com"
                                   class="w-full px-4 py-2.5 border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-[#ea580c] focus:border-[#ea580c] dark:text-white" />
                            @error('email') <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Auto Password Info Alert -->
                        <div class="bg-[#f0f7ff] dark:bg-blue-900/20 border border-[#dbeafe] dark:border-blue-800/40 rounded-xl p-4">
                            <p class="text-[12px] text-blue-700 dark:text-blue-400 leading-relaxed">
                                A secure password will be generated automatically and emailed to the new member. Save it then and share it through a secure channel.
                            </p>
                        </div>

                        <!-- Role -->
                        <div>
                            <label class="flex items-center text-[13px] font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                Role *
                            </label>
                            <x-custom-select wire:model="role" placeholder="Select Role"
                                :options="collect($availableRoles)->map(fn($r) => ['id' => $r->name, 'name' => $r->name])->toArray()" />
                        </div>

                        <!-- Display Name -->
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 dark:text-gray-300 mb-2">Display Name (Optional)</label>
                            <input type="text" wire:model="name" placeholder="John Doe"
                                   class="w-full px-4 py-2.5 border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-[#ea580c] focus:border-[#ea580c] dark:text-white" />
                        </div>

                        <!-- Monthly Salary -->
                        <div>
                            <label class="block text-[13px] font-semibold text-gray-700 dark:text-gray-300 mb-2">Monthly Salary (Optional)</label>
                            <input type="number" step="0.01" wire:model="salary" placeholder="5000.00"
                                   class="w-full px-4 py-2.5 border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-[#ea580c] focus:border-[#ea580c] dark:text-white" />
                            <p class="text-[11px] text-gray-400 mt-1.5">For team members on monthly salary instead of project-based pay</p>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="flex items-center text-[13px] font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                Notes (Optional)
                            </label>
                            <textarea wire:model="notes" rows="3" placeholder="Any additional information..."
                                      class="w-full px-4 py-2.5 border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-xl text-sm focus:ring-[#ea580c] focus:border-[#ea580c] dark:text-white resize-none"></textarea>
                        </div>
                    </div>

                    <div class="px-6 py-4 flex justify-between gap-3">
                        <button type="button" wire:click="$set('showModal', false)" class="w-full py-2.5 bg-white border border-gray-250 rounded-xl text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="w-full py-2.5 bg-[#ea580c] hover:bg-orange-600 text-white rounded-xl text-sm font-bold shadow-sm transition-colors">
                            Add Member
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
