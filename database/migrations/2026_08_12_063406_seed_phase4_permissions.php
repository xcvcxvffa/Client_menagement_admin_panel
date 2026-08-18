<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view special_days', 'create special_days', 'edit special_days', 'delete special_days', 'manage special_days',
            'view campaigns', 'create campaigns', 'edit campaigns', 'delete campaigns', 'manage campaigns',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
        }

        // We can attach to Business Owner if they exist globally, but usually permissions 
        // are attached to roles per team. The easiest way is to let the role seeder or the UI handle it, 
        // or just add them to the 'Business Owner' role.
        $role = \Spatie\Permission\Models\Role::where('name', 'Business Owner')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'view special_days', 'create special_days', 'edit special_days', 'delete special_days', 'manage special_days',
            'view campaigns', 'create campaigns', 'edit campaigns', 'delete campaigns', 'manage campaigns',
        ];

        \Spatie\Permission\Models\Permission::whereIn('name', $permissions)->delete();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
