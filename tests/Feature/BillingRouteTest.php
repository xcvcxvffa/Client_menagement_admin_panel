<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BillingRouteTest extends TestCase
{
    public function test_billing_route_renders()
    {
        $user = User::first(); // Assuming a user exists
        $response = $this->actingAs($user)->get('/billing');
        
        if ($response->status() !== 200) {
            echo "\nFailed with status: " . $response->status() . "\n";
            echo substr($response->getContent(), 0, 1000);
        }
        
        $response->assertStatus(200);
    }
}
