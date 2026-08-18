<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

use function Livewire\Volt\layout;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;

layout('layouts.guest');

state([
    'name' => '',
    'email' => '',
    'password' => '',
    'password_confirmation' => ''
]);

rules([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
    'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
]);

$register = function () {
    $validated = $this->validate();

    $validated['password'] = Hash::make($validated['password']);

    $user = User::create($validated);

    $business = \App\Models\Business::create([
        'name' => explode(' ', $user->name)[0] . "'s Business",
        'slug' => \Illuminate\Support\Str::slug($user->name . ' Business ' . uniqid()),
        'currency' => 'USD',
        'branding_color' => '#6366f1',
    ]);

    $user->update(['current_business_id' => $business->id]);

    \App\Models\TeamMember::create([
        'user_id' => $user->id,
        'business_id' => $business->id,
        'role' => 'owner'
    ]);
    
    // Assign Owner role to the creator
    setPermissionsTeamId($business->id);
    $user->assignRole('Owner');

    event(new Registered($user));

    Auth::login($user);

    $this->redirect(route('dashboard', absolute: false), navigate: true);
};

?>

<div class="flex flex-col items-center w-full">
    <!-- Header Section -->
    <div class="mb-8 text-center flex flex-col items-center">
        <div class="bg-yellow-200/80 text-yellow-800 text-xs font-bold px-4 py-1.5 rounded-full mb-4 inline-block">
            Create Account
        </div>
        <h1 class="text-3xl font-black text-gray-900 tracking-tight">Join Us!</h1>
    </div>

    <form wire:submit="register" class="w-full space-y-4">
        <!-- Name -->
        <div>
            <label for="name" class="block text-sm font-bold text-gray-800 mb-1.5">Full Name</label>
            <input wire:model="name" id="name" type="text" name="name" required autofocus autocomplete="name" 
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-bold text-gray-800 mb-1.5">Email Address</label>
            <input wire:model="email" id="email" type="email" name="email" required autocomplete="username" 
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-bold text-gray-800 mb-1.5">Password</label>
            <input wire:model="password" id="password" type="password" name="password" required autocomplete="new-password" 
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="block text-sm font-bold text-gray-800 mb-1.5">Confirm Password</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" 
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <!-- Submit Button -->
        <div class="pt-3">
            <button type="submit" class="w-full flex justify-center items-center py-3.5 px-4 bg-[#111111] hover:bg-black text-white rounded-xl text-sm font-bold shadow-lg shadow-black/10 transition-all hover:-translate-y-0.5">
                Create an Account
            </button>
        </div>
        
        <!-- Link to Login -->
        <div class="text-center pt-4">
            <span class="text-sm text-gray-500">Already registered?</span>
            <a href="{{ route('login') }}" wire:navigate class="text-sm font-bold text-gray-900 hover:text-orange-600 transition-colors ml-1">Log in</a>
        </div>
    </form>
</div>
