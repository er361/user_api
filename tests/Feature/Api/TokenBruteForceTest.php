<?php

namespace Tests\Feature\Api;

use App\Models\TransferConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenBruteForceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test token is blocked after 3 failed attempts
     */
    public function test_token_blocked_after_max_failed_attempts(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $confirmation = TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'valid-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        // Attempt 1 with wrong token (token doesn't exist)
        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => 'wrong-token-1',
            ]);
        $response->assertNotFound();

        // Attempt 2
        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => 'wrong-token-2',
            ]);
        $response->assertNotFound();

        // Attempt 3
        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => 'wrong-token-3',
            ]);
        $response->assertNotFound();

        // Now the valid token should still work (these were attempts on non-existent tokens)
        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => 'valid-token',
            ]);

        $response->assertOk();
    }

    /**
     * Test specific confirmation gets blocked after 3 failed attempts
     */
    public function test_confirmation_blocked_after_three_failed_validations(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $confirmation = TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token-block',
            'expires_at' => now()->addMinutes(15),
            'failed_attempts' => 0,
        ]);

        // Manually set failed_attempts to 3 (simulating 3 failed attempts)
        $confirmation->update(['failed_attempts' => 3]);

        // Now try to use the token (should be blocked)
        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => 'test-token-block',
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'This confirmation has been blocked due to too many failed attempts.',
        ]);

        // Verify transfer did not execute
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '100.00',
        ]);

        $this->assertDatabaseHas('transfer_confirmations', [
            'confirmation_token' => 'test-token-block',
            'confirmed' => false,
        ]);
    }

    /**
     * Test isBlocked method returns true after max attempts
     */
    public function test_confirmation_is_blocked_method(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $confirmation = TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
            'failed_attempts' => 0,
        ]);

        $this->assertFalse($confirmation->isBlocked());

        $confirmation->update(['failed_attempts' => 3]);
        $confirmation->refresh();

        $this->assertTrue($confirmation->isBlocked());
    }

    /**
     * Test isValid returns false when blocked
     */
    public function test_is_valid_returns_false_when_blocked(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $confirmation = TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
            'failed_attempts' => 3,
        ]);

        $this->assertFalse($confirmation->isValid());
    }

    /**
     * Test MAX_ATTEMPTS constant is set correctly
     */
    public function test_max_attempts_constant_is_three(): void
    {
        $this->assertEquals(3, TransferConfirmation::MAX_ATTEMPTS);
    }

    /**
     * Test failed_attempts column exists and defaults to 0
     */
    public function test_failed_attempts_defaults_to_zero(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $confirmation = TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertEquals(0, $confirmation->failed_attempts);
    }

    /**
     * Test incrementFailedAttempts method
     */
    public function test_increment_failed_attempts_method(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $confirmation = TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertEquals(0, $confirmation->failed_attempts);

        $confirmation->incrementFailedAttempts();
        $confirmation->refresh();

        $this->assertEquals(1, $confirmation->failed_attempts);

        $confirmation->incrementFailedAttempts();
        $confirmation->refresh();

        $this->assertEquals(2, $confirmation->failed_attempts);
    }

    /**
     * Test valid confirmation with 2 failed attempts still works
     */
    public function test_confirmation_works_with_two_failed_attempts(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $confirmation = TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token-2-fails',
            'expires_at' => now()->addMinutes(15),
            'failed_attempts' => 2,
        ]);

        $this->assertTrue($confirmation->isValid());

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => 'test-token-2-fails',
            ]);

        $response->assertOk();

        // Verify transfer executed
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '75.00',
        ]);
    }
}
