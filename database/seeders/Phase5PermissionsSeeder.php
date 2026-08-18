<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Phase5PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Define Phase 5 permissions
        $permissions = [
            'view content',
            'create content',
            'edit content',
            'delete content',
            'assign content',
            'review content',
            'request_changes content',
            'approve content',
            'schedule content',
            'publish content',

            'view content_types',
            'manage content_types',

            'view platforms',
            'manage platforms',

            'view content_approvals',
            'manage content_approvals',
        ];

        // Create missing permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign all to Business Owner
        $ownerRole = Role::where('name', 'Business Owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo($permissions);
        }
        
        // Also seed initial platforms and content types
        $this->seedInitialData();
    }
    
    private function seedInitialData(): void
    {
        $types = [
            ['name' => 'Social Post', 'slug' => 'social-post', 'description' => 'Standard social media post'],
            ['name' => 'Carousel', 'slug' => 'carousel', 'description' => 'Multi-image carousel post'],
            ['name' => 'Reel', 'slug' => 'reel', 'description' => 'Short vertical video'],
            ['name' => 'Story', 'slug' => 'story', 'description' => 'Temporary 24-hour post'],
            ['name' => 'Video', 'slug' => 'video', 'description' => 'Long-form video content'],
            ['name' => 'Blog', 'slug' => 'blog', 'description' => 'Website article or blog post'],
        ];
        
        foreach ($types as $type) {
            \App\Models\ContentType::firstOrCreate(
                ['slug' => $type['slug'], 'business_id' => null],
                ['name' => $type['name'], 'description' => $type['description']]
            );
        }
        
        $platforms = [
            ['name' => 'Instagram', 'slug' => 'instagram'],
            ['name' => 'Facebook', 'slug' => 'facebook'],
            ['name' => 'LinkedIn', 'slug' => 'linkedin'],
            ['name' => 'YouTube', 'slug' => 'youtube'],
            ['name' => 'X (Twitter)', 'slug' => 'x'],
            ['name' => 'Website', 'slug' => 'website'],
            ['name' => 'Email', 'slug' => 'email'],
        ];
        
        foreach ($platforms as $platform) {
            \App\Models\Platform::firstOrCreate(
                ['slug' => $platform['slug'], 'business_id' => null],
                ['name' => $platform['name']]
            );
        }
    }
}
