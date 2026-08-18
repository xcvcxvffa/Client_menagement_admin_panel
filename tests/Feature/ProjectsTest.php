<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Project;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
    }

    public function test_guests_are_redirected_from_projects_index(): void
    {
        $response = $this->get(route('projects.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_users_can_view_projects_index_page(): void
    {
        $business = Business::create([
            'name' => 'Acme Test Agency',
            'slug' => 'acme-test',
        ]);

        $user = User::factory()->create([
            'current_business_id' => $business->id,
        ]);

        TeamMember::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
            'role_id' => \Spatie\Permission\Models\Role::where('name', 'Owner')->first()->id,
            'model_type' => User::class,
            'model_id' => $user->id,
            'business_id' => $business->id
        ]);

        $this->actingAs($user);

        $response = $this->get(route('projects.index'));
        $response->assertOk()
            ->assertSeeVolt('projects.list-projects');
    }

    public function test_users_can_move_project_to_trash(): void
    {
        $business = Business::create([
            'name' => 'Acme Test Agency',
            'slug' => 'acme-test',
        ]);

        $user = User::factory()->create([
            'current_business_id' => $business->id,
        ]);

        TeamMember::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
            'role_id' => \Spatie\Permission\Models\Role::where('name', 'Owner')->first()->id,
            'model_type' => User::class,
            'model_id' => $user->id,
            'business_id' => $business->id
        ]);

        $client = Client::create([
            'business_id' => $business->id,
            'name' => 'Client Dave',
            'email' => 'dave@client.com',
            'status' => 'active',
        ]);

        $project = Project::create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'name' => 'Test Project',
            'status' => 'planning',
        ]);

        $this->actingAs($user);
        session(['active_business_id' => $business->id]);

        $component = Volt::test('projects.list-projects')
            ->set('selectedProjectId', $project->id)
            ->call('confirmDelete', $project->id);

        $component->assertSet('showConfirmModal', true);
        $component->assertSet('confirmModalAction', 'delete');
        $component->assertSet('confirmModalProjectId', $project->id);

        $component->call('executeConfirmAction');

        $component->assertSet('showConfirmModal', false);
        $component->assertSet('selectedProjectId', null);
        $component->assertSet('isDrawerOpen', false);

        $this->assertSoftDeleted('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_users_can_restore_project_from_trash(): void
    {
        $business = Business::create([
            'name' => 'Acme Test Agency',
            'slug' => 'acme-test',
        ]);

        $user = User::factory()->create([
            'current_business_id' => $business->id,
        ]);

        TeamMember::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
            'role_id' => \Spatie\Permission\Models\Role::where('name', 'Owner')->first()->id,
            'model_type' => User::class,
            'model_id' => $user->id,
            'business_id' => $business->id
        ]);

        $client = Client::create([
            'business_id' => $business->id,
            'name' => 'Client Dave',
            'email' => 'dave@client.com',
            'status' => 'active',
        ]);

        $project = Project::create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'name' => 'Test Project',
            'status' => 'planning',
        ]);
        $project->delete(); // Soft delete it first

        $this->actingAs($user);
        session(['active_business_id' => $business->id]);

        $component = Volt::test('projects.list-projects')
            ->call('confirmRestore', $project->id);

        $component->assertSet('showConfirmModal', true);
        $component->assertSet('confirmModalAction', 'restore');
        $component->assertSet('confirmModalProjectId', $project->id);

        $component->call('executeConfirmAction');

        $component->assertSet('showConfirmModal', false);
        $this->assertNotSoftDeleted('projects', [
            'id' => $project->id,
        ]);
    }

    public function test_users_can_permanently_delete_project_from_trash(): void
    {
        $business = Business::create([
            'name' => 'Acme Test Agency',
            'slug' => 'acme-test',
        ]);

        $user = User::factory()->create([
            'current_business_id' => $business->id,
        ]);

        TeamMember::create([
            'business_id' => $business->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert([
            'role_id' => \Spatie\Permission\Models\Role::where('name', 'Owner')->first()->id,
            'model_type' => User::class,
            'model_id' => $user->id,
            'business_id' => $business->id
        ]);

        $client = Client::create([
            'business_id' => $business->id,
            'name' => 'Client Dave',
            'email' => 'dave@client.com',
            'status' => 'active',
        ]);

        $project = Project::create([
            'business_id' => $business->id,
            'client_id' => $client->id,
            'name' => 'Test Project',
            'status' => 'planning',
        ]);
        $project->delete(); // Soft delete it first

        $this->actingAs($user);
        session(['active_business_id' => $business->id]);

        $component = Volt::test('projects.list-projects')
            ->call('confirmForceDelete', $project->id);

        $component->assertSet('showConfirmModal', true);
        $component->assertSet('confirmModalAction', 'forceDelete');
        $component->assertSet('confirmModalProjectId', $project->id);

        $component->call('executeConfirmAction');

        $component->assertSet('showConfirmModal', false);
        $this->assertDatabaseMissing('projects', [
            'id' => $project->id,
        ]);
    }
}
