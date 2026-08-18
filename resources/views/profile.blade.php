<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Column 1 -->
                <div class="space-y-6">
                    <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 border border-gray-150 rounded-2xl shadow-sm">
                        <livewire:profile.update-profile-information-form />
                    </div>
                </div>

                <!-- Column 2 -->
                <div class="space-y-6">
                    <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 border border-gray-150 rounded-2xl shadow-sm">
                        <livewire:profile.update-password-form />
                    </div>

                    <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 border border-gray-150 rounded-2xl shadow-sm">
                        <livewire:profile.delete-user-form />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
