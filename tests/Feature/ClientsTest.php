<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Client;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClientsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
    }

    public function test_guests_are_redirected_from_clients_index(): void
    {
        $response = $this->get(route('clients.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_users_can_view_clients_index_page(): void
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

        $response = $this->get(route('clients.index'));
        $response->assertOk()
            ->assertSeeVolt('clients.list-clients');
    }

    public function test_users_can_view_client_profile_page(): void
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

        $client = Client::create([
            'business_id' => $business->id,
            'name' => 'Client Dave',
            'email' => 'dave@client.com',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('clients.show', $client->id));
        $response->assertOk()
            ->assertSeeVolt('clients.client-profile');
    }

    public function test_users_can_create_a_client(): void
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

        $component = Volt::test('clients.create-client')
            ->set('name', 'Hindustan Retail')
            ->set('email', 'retail@hindustan.com')
            ->set('phone', '+9199999999')
            ->set('currency', 'INR');

        $component->call('saveClient');

        $component->assertHasNoErrors();

        $this->assertDatabaseHas('clients', [
            'business_id' => $business->id,
            'name' => 'Hindustan Retail',
            'email' => 'retail@hindustan.com',
        ]);
    }

    public function test_users_can_upload_client_attachment(): void
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

        $client = Client::create([
            'business_id' => $business->id,
            'name' => 'Client Dave',
            'email' => 'dave@client.com',
            'status' => 'active',
        ]);

        \Illuminate\Support\Facades\DB::table('model_has_roles')->insert(['role_id' => \Spatie\Permission\Models\Role::where('name', 'Owner')->first()->id, 'model_type' => App\Models\User::class, 'model_id' => $user->id, 'business_id' => $business->id]);

        $this->actingAs($user);
        session(['active_business_id' => $business->id]);

        // Fake local disk storage
        Storage::fake('public');

        $file = UploadedFile::fake()->create('contract.pdf', 500);

        $component = Volt::test('clients.client-profile', ['client' => $client])
            ->set('uploadedFile', $file);

        $component->call('uploadAttachment');

        $component->assertHasNoErrors();
        $this->assertCount(1, $client->refresh()->getMedia('attachments'));
        $this->assertEquals('contract.pdf', $client->getFirstMedia('attachments')->file_name);
    }
}
