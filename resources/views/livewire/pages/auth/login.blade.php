<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;

use function Livewire\Volt\form;
use function Livewire\Volt\layout;

layout('layouts.guest');

form(LoginForm::class);

$login = function () {
    $this->validate();

    $this->form->authenticate();

    Session::regenerate();

    $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
};

?>

<div class="flex flex-col items-center w-full">
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <!-- Header Section -->
    <div class="mb-8 text-center flex flex-col items-center">
        <div class="bg-yellow-200/80 text-yellow-800 text-xs font-bold px-4 py-1.5 rounded-full mb-4 inline-block">
            Secure Login
        </div>
        <h1 class="text-3xl font-black text-gray-900 tracking-tight">Welcome Back!</h1>
    </div>

    <form wire:submit="login" class="w-full space-y-5">
        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-bold text-gray-800 mb-1.5">Email Address</label>
            <input wire:model="form.email" id="email" type="email" name="email" required autofocus autocomplete="username" 
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-bold text-gray-800 mb-1.5">Password</label>
            <input wire:model="form.password" id="password" type="password" name="password" required autocomplete="current-password" 
                   class="w-full px-4 py-3 bg-white border border-gray-200 rounded-xl text-sm shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500 transition-all" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="flex items-center justify-between pt-1">
            <label for="remember" class="flex items-center gap-2 cursor-pointer group">
                <div class="relative flex items-center">
                    <input wire:model="form.remember" id="remember" type="checkbox" name="remember" class="peer sr-only" />
                    <div class="w-5 h-5 bg-white border border-gray-200 rounded shadow-sm peer-checked:bg-purple-500 peer-checked:border-purple-500 transition-colors"></div>
                    <svg class="absolute w-3 h-3 text-white left-1 top-1 opacity-0 peer-checked:opacity-100 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </div>
                <span class="text-sm font-medium text-gray-500 group-hover:text-gray-700 transition-colors">Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-bold text-gray-800 hover:text-purple-600 transition-colors" href="{{ route('password.request') }}" wire:navigate>
                    Forgot password?
                </a>
            @endif
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
            <button type="submit" class="w-full flex justify-center items-center py-3.5 px-4 bg-[#111111] hover:bg-black text-white rounded-xl text-sm font-bold shadow-lg shadow-black/10 transition-all hover:-translate-y-0.5">
                Log In to Account
            </button>
        </div>
        

    </form>
</div>
