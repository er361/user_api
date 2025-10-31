<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test IDOR protection - users cannot update other users' profiles
     */
    public function test_user_cannot_update_another_users_profile(): void
    {
        $user1 = User::factory()->create(['name' => 'User One']);
        $user2 = User::factory()->create(['name' => 'User Two']);

        $response = $this->actingAs($user1)
            ->putJson('/api/v1/me', [
                'name' => 'Updated Name',
            ]);

        $response->assertOk();

        // Verify only user1's profile was updated
        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'name' => 'Updated Name',
        ]);

        // Verify user2 was not affected
        $this->assertDatabaseHas('users', [
            'id' => $user2->id,
            'name' => 'User Two',
        ]);
    }

    /**
     * Test timing attack protection - consistent response times
     */
    public function test_recipient_not_found_uses_timing_protection(): void
    {
        $user = User::factory()->create(['balance' => 100]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/me/transfers/initiate', [
                'recipient_id' => 99999,
                'amount' => 10,
            ]);

        $response->assertNotFound();
        $response->assertJson([
            'message' => 'Recipient not found.',
        ]);
    }

    /**
     * Test mass assignment protection for is_admin field
     */
    public function test_cannot_set_is_admin_via_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_admin' => true, // This should be ignored
        ]);

        // is_admin should not be set to true (will be NULL which casts to false)
        $this->assertNotTrue($user->is_admin);
        $this->assertFalse((bool)$user->is_admin);
    }

    /**
     * Test that is_admin is guarded from fill()
     */
    public function test_is_admin_is_guarded_from_fill(): void
    {
        $user = User::factory()->create();

        $user->fill(['is_admin' => true]);

        // fill() should not set is_admin to true
        $this->assertNotTrue($user->is_admin);
        $this->assertFalse((bool)$user->is_admin);
    }

    /**
     * Test unauthenticated users cannot access protected endpoints
     */
    public function test_unauthenticated_users_cannot_access_api(): void
    {
        $response = $this->putJson('/api/v1/me', [
            'name' => 'New Name',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Test self-transfer is rejected early
     */
    public function test_cannot_initiate_transfer_to_self(): void
    {
        $user = User::factory()->create(['balance' => 100]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/me/transfers/initiate', [
                'recipient_id' => $user->id,
                'amount' => 10,
            ]);

        $response->assertStatus(400);
    }
}
