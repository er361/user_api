<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test non-admin cannot update user balances
     */
    public function test_non_admin_cannot_update_user_balance(): void
    {
        $admin = User::factory()->create(['is_admin' => false]);
        $targetUser = User::factory()->create(['balance' => 100]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$targetUser->id}/balance", [
                'balance' => 500,
            ]);

        $response->assertForbidden();

        // Verify balance was not changed
        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'balance' => '100.00',
        ]);
    }

    /**
     * Test admin can update user balances
     */
    public function test_admin_can_update_user_balance(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $targetUser = User::factory()->create(['balance' => 100]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$targetUser->id}/balance", [
                'balance' => 500,
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'balance' => '500.00',
        ]);

        // Verify transaction was logged
        $this->assertDatabaseHas('balance_transactions', [
            'user_id' => $targetUser->id,
            'type' => 'credit',
            'description' => 'Balance updated by admin',
        ]);
    }

    /**
     * Test admin attempting to update non-existent user gets consistent error
     */
    public function test_admin_updating_non_existent_user_gets_forbidden(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $response = $this->actingAs($admin)
            ->putJson('/api/v1/users/99999/balance', [
                'balance' => 500,
            ]);

        // Should return 403 (not 404) to prevent user enumeration
        $response->assertForbidden();
    }

    /**
     * Test balance update validates maximum amount
     */
    public function test_admin_cannot_set_balance_above_maximum(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $targetUser = User::factory()->create(['balance' => 100]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$targetUser->id}/balance", [
                'balance' => 9999999999.99, // Above max
            ]);

        $response->assertStatus(422);
    }

    /**
     * Test balance update validates decimal precision
     */
    public function test_admin_balance_update_validates_decimal_precision(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $targetUser = User::factory()->create(['balance' => 100]);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/users/{$targetUser->id}/balance", [
                'balance' => 100.123, // 3 decimal places
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('balance');
    }

    /**
     * Test unauthorized balance update attempt is logged
     */
    public function test_unauthorized_balance_update_is_logged(): void
    {
        $nonAdmin = User::factory()->create(['is_admin' => false]);
        $targetUser = User::factory()->create(['balance' => 100]);

        // Expect warning from Policy
        \Log::shouldReceive('warning')
            ->once()
            ->with('Non-admin attempted to update balance', [
                'user_id' => $nonAdmin->id,
            ]);

        // Expect warning from Exception handler
        \Log::shouldReceive('warning')
            ->once()
            ->with('Access denied', \Mockery::type('array'));

        $response = $this->actingAs($nonAdmin)
            ->putJson("/api/v1/users/{$targetUser->id}/balance", [
                'balance' => 500,
            ]);

        $response->assertForbidden();
    }
}
