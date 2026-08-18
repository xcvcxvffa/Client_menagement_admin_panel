<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\Lead;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LeadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    }

    public function test_guests_are_redirected_from_leads_index(): void
    {
        $response = $this->get(route('leads.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_users_can_view_leads_page(): void
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

        $this->actingAs($user);

        $response = $this->get(route('leads.index'));
        $response->assertOk()
            ->assertSeeVolt('leads.pipeline');
    }

    public function test_users_can_create_a_lead(): void
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

        $this->actingAs($user);
        session(['active_business_id' => $business->id]);

        $component = Volt::test('leads.pipeline')
            ->set('name', 'Prospect Dave')
            ->set('email', 'dave@prospect.com')
            ->set('company_name', 'Dave Corp')
            ->set('value', 45000)
            ->set('status', 'NEW')
            ->set('notes', 'Looking for styling help.');

        $component->call('saveLead');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('leads', [
            'business_id' => $business->id,
            'name' => 'Prospect Dave',
            'email' => 'dave@prospect.com',
            'value' => 45000,
            'status' => 'NEW',
        ]);
    }

    public function test_users_can_update_lead_status(): void
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

        $this->actingAs($user);
        session(['active_business_id' => $business->id]);

        $lead = Lead::create([
            'business_id' => $business->id,
            'name' => 'John Lead',
            'email' => 'john@lead.com',
            'value' => 20000,
            'status' => 'NEW',
        ]);

        $component = Volt::test('leads.pipeline');
        $component->call('updateLeadStatus', $lead->id, 'CONTACTED');

        $component->assertHasNoErrors();
        $this->assertEquals('CONTACTED', $lead->refresh()->status);
    }

    public function test_users_can_convert_lead_to_client(): void
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

        $this->actingAs($user);
        session(['active_business_id' => $business->id]);

        $lead = Lead::create([
            'business_id' => $business->id,
            'name' => 'Future Client Inc',
            'email' => 'contact@futureclient.com',
            'company_name' => 'Future Client LLC',
            'value' => 60000,
            'status' => 'NEGOTIATION',
        ]);

        $component = Volt::test('leads.pipeline');
        $component->call('convertToClient', $lead->id);

        $component->assertHasNoErrors();
        
        $this->assertEquals('WON', $lead->refresh()->status);
        $this->assertDatabaseHas('clients', [
            'business_id' => $business->id,
            'name' => 'Future Client Inc',
            'email' => 'contact@futureclient.com',
            'company_name' => 'Future Client LLC',
        ]);
    }
}
