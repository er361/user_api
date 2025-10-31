<?php

namespace Tests\Feature\Api;

use App\Models\TransferConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferConfirmationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test successful 2-step transfer flow
     */
    public function test_successful_two_step_transfer_flow(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        // Step 1: Initiate transfer
        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/initiate', [
                'recipient_id' => $recipient->id,
                'amount' => 25.50,
            ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'message',
            'confirmation_token',
            'expires_at',
            'amount',
            'recipient_id',
        ]);

        $token = $response->json('confirmation_token');

        // Verify confirmation was created
        $this->assertDatabaseHas('transfer_confirmations', [
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => '25.50',
            'confirmed' => false,
        ]);

        // Step 2: Confirm transfer
        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => $token,
            ]);

        $response->assertOk();

        // Verify balances updated
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '74.50',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $recipient->id,
            'balance' => '75.50',
        ]);

        // Verify confirmation was marked as confirmed
        $this->assertDatabaseHas('transfer_confirmations', [
            'confirmation_token' => $token,
            'confirmed' => true,
        ]);

        // Verify transactions were logged
        $this->assertDatabaseHas('balance_transactions', [
            'user_id' => $sender->id,
            'type' => 'transfer_out',
            'amount' => '25.50',
        ]);

        $this->assertDatabaseHas('balance_transactions', [
            'user_id' => $recipient->id,
            'type' => 'transfer_in',
            'amount' => '25.50',
        ]);
    }

    /**
     * Test confirmation token expires after 15 minutes
     */
    public function test_confirmation_token_expires(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $token = str_repeat('a', 64); // 64-character token

        $confirmation = TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => $token,
            'expires_at' => now()->subMinutes(1), // Expired
        ]);

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => $token,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'This confirmation token has expired.',
        ]);

        // Verify balances were not changed
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '100.00',
        ]);
    }

    /**
     * Test cannot confirm transfer twice
     */
    public function test_cannot_confirm_transfer_twice(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $token = str_repeat('b', 64); // 64-character token

        $confirmation = TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => $token,
            'expires_at' => now()->addMinutes(15),
            'confirmed' => true,
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => $token,
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'message' => 'This transfer has already been confirmed.',
        ]);
    }

    /**
     * Test invalid token returns 404
     */
    public function test_invalid_confirmation_token_returns_404(): void
    {
        $sender = User::factory()->create(['balance' => 100]);

        $token = str_repeat('c', 64); // 64-character token that doesn't exist

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => $token,
            ]);

        $response->assertNotFound();
        $response->assertJson([
            'message' => 'Invalid confirmation token.',
        ]);
    }

    /**
     * Test user cannot confirm another user's transfer
     */
    public function test_user_cannot_confirm_another_users_transfer(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);
        $attacker = User::factory()->create(['balance' => 0]);

        $token = str_repeat('d', 64); // 64-character token

        $confirmation = TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => $token,
            'expires_at' => now()->addMinutes(15),
        ]);

        // Attacker tries to confirm sender's transfer
        $response = $this->actingAs($attacker)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => $token,
            ]);

        $response->assertNotFound();

        // Verify transfer did not execute
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '100.00',
        ]);
    }

    /**
     * Test transfer validates sufficient balance at confirmation time
     */
    public function test_transfer_validates_balance_at_confirmation_time(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        // Step 1: Initiate transfer
        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/initiate', [
                'recipient_id' => $recipient->id,
                'amount' => 50,
            ]);

        $token = $response->json('confirmation_token');

        // Sender spends money elsewhere, reducing balance
        \DB::table('users')->where('id', $sender->id)->update(['balance' => '30.00']);

        // Step 2: Try to confirm transfer (should fail - insufficient balance)
        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/confirm', [
                'confirmation_token' => $token,
            ]);

        $response->assertStatus(400);

        // Verify balances unchanged
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '30.00',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $recipient->id,
            'balance' => '50.00',
        ]);
    }

    /**
     * Test confirmation token is exactly 64 characters
     */
    public function test_confirmation_token_has_correct_length(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/initiate', [
                'recipient_id' => $recipient->id,
                'amount' => 10,
            ]);

        $token = $response->json('confirmation_token');

        $this->assertEquals(64, strlen($token));
    }

    /**
     * Test initiate transfer validates recipient exists
     */
    public function test_initiate_transfer_validates_recipient_exists(): void
    {
        $sender = User::factory()->create(['balance' => 100]);

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/initiate', [
                'recipient_id' => 99999,
                'amount' => 10,
            ]);

        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Recipient not found.',
        ]);

        // Verify no confirmation was created
        $this->assertDatabaseCount('transfer_confirmations', 0);
    }

    /**
     * Test cannot initiate transfer with negative amount
     */
    public function test_cannot_initiate_transfer_with_negative_amount(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/initiate', [
                'recipient_id' => $recipient->id,
                'amount' => -10,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }

    /**
     * Test cannot initiate transfer with more than 2 decimal places
     */
    public function test_cannot_initiate_transfer_with_invalid_decimals(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $response = $this->actingAs($sender)
            ->postJson('/api/v1/me/transfers/initiate', [
                'recipient_id' => $recipient->id,
                'amount' => 10.123, // 3 decimal places
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }
}
