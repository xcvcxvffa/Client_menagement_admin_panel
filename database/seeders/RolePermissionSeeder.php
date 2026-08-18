<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $modules = [
            'dashboard', 'users', 'roles', 'permissions', 'pages', 'blogs', 
            'categories', 'products', 'media', 'downloads', 'services', 
            'projects', 'testimonials', 'clients', 'inquiry', 'seo', 
            'settings', 'reports', 'profile', 'activity_logs'
        ];

        $actions = [
            'view', 'create', 'edit', 'delete', 'status', 'export', 'import', 'restore', 'force_delete'
        ];

        $allPermissions = [];
        
        // Create Permissions
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                // E.g. products.view
                $permissionName = "{$module}.{$action}";
                $allPermissions[] = $permissionName;
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
            }
        }

        // Create Default Roles (Global roles without business_id, or they can be used as templates)
        $roles = [
            'Super Admin',
            'Admin',
            'Manager',
            'Editor',
            'Staff'
        ];

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate([
                'name' => $roleName, 
                'guard_name' => 'web',
                'business_id' => null // Assuming nullable for global roles
            ]);

            // Super Admin, Admin, and Owner gets all permissions for testing purposes
            if (in_array($roleName, ['Super Admin', 'Admin', 'Owner'])) {
                $role->syncPermissions($allPermissions);
            }
        }
        
        // Also ensure Owner role from DatabaseSeeder gets permissions
        $ownerRole = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web', 'business_id' => null]);
        $ownerRole->syncPermissions($allPermissions);
    }
}
