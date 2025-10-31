<?php

namespace Tests\Unit;

use App\Models\TransferConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferConfirmationModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test isExpired returns true for expired confirmations
     */
    public function test_is_expired_returns_true_for_expired_confirmations(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertTrue($confirmation->isExpired());
    }

    /**
     * Test isExpired returns false for non-expired confirmations
     */
    public function test_is_expired_returns_false_for_non_expired_confirmations(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertFalse($confirmation->isExpired());
    }

    /**
     * Test isValid returns false when confirmed
     */
    public function test_is_valid_returns_false_when_confirmed(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
            'confirmed' => true,
        ]);

        $this->assertFalse($confirmation->isValid());
    }

    /**
     * Test isValid returns false when expired
     */
    public function test_is_valid_returns_false_when_expired(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse($confirmation->isValid());
    }

    /**
     * Test isValid returns false when blocked
     */
    public function test_is_valid_returns_false_when_blocked(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
            'failed_attempts' => 3,
        ]);

        $this->assertFalse($confirmation->isValid());
    }

    /**
     * Test isValid returns true for valid confirmations
     */
    public function test_is_valid_returns_true_for_valid_confirmations(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
            'confirmed' => false,
            'failed_attempts' => 0,
        ]);

        $this->assertTrue($confirmation->isValid());
    }

    /**
     * Test user relationship
     */
    public function test_user_relationship(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertEquals($user->id, $confirmation->user->id);
    }

    /**
     * Test recipient relationship
     */
    public function test_recipient_relationship(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertEquals($recipient->id, $confirmation->recipient->id);
    }

    /**
     * Test amount is cast to decimal
     */
    public function test_amount_is_cast_to_decimal(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => '25.50',
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertIsString($confirmation->amount);
        $this->assertEquals('25.50', $confirmation->amount);
    }

    /**
     * Test expires_at is cast to datetime
     */
    public function test_expires_at_is_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $expiresAt = now()->addMinutes(15);

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => $expiresAt,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $confirmation->expires_at);
        $this->assertEquals($expiresAt->timestamp, $confirmation->expires_at->timestamp);
    }

    /**
     * Test confirmed is cast to boolean
     */
    public function test_confirmed_is_cast_to_boolean(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $confirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'test-token',
            'expires_at' => now()->addMinutes(15),
            'confirmed' => true,
        ]);

        $this->assertIsBool($confirmation->confirmed);
        $this->assertTrue($confirmation->confirmed);
    }

    /**
     * Test confirmation_token is unique
     */
    public function test_confirmation_token_is_unique(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'unique-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'unique-token', // Duplicate
            'expires_at' => now()->addMinutes(15),
        ]);
    }
}
