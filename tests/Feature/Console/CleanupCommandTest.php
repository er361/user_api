<?php

namespace Tests\Feature\Console;

use App\Models\TransferConfirmation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test cleanup command deletes old confirmations
     */
    public function test_cleanup_command_deletes_old_confirmations(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        // Create old confirmation (8 days old)
        $oldConfirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'old-token',
            'expires_at' => now()->subDays(8),
        ]);

        // Create recent confirmation (5 days old)
        $recentConfirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'recent-token',
            'expires_at' => now()->subDays(5),
        ]);

        // Create current confirmation
        $currentConfirmation = TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'current-token',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertDatabaseCount('transfer_confirmations', 3);

        // Run cleanup command (default: 7 days)
        $this->artisan('transfers:cleanup-expired')
            ->expectsOutput('Deleted 1 expired transfer confirmation(s) older than 7 days.')
            ->assertExitCode(0);

        // Verify old confirmation was deleted
        $this->assertDatabaseMissing('transfer_confirmations', [
            'confirmation_token' => 'old-token',
        ]);

        // Verify recent and current confirmations remain
        $this->assertDatabaseHas('transfer_confirmations', [
            'confirmation_token' => 'recent-token',
        ]);

        $this->assertDatabaseHas('transfer_confirmations', [
            'confirmation_token' => 'current-token',
        ]);

        $this->assertDatabaseCount('transfer_confirmations', 2);
    }

    /**
     * Test cleanup command with custom days parameter
     */
    public function test_cleanup_command_with_custom_days(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        // Create confirmation 35 days old
        TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'very-old-token',
            'expires_at' => now()->subDays(35),
        ]);

        // Create confirmation 25 days old
        TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'old-token',
            'expires_at' => now()->subDays(25),
        ]);

        $this->assertDatabaseCount('transfer_confirmations', 2);

        // Run cleanup with 30 days retention
        $this->artisan('transfers:cleanup-expired --days=30')
            ->expectsOutput('Deleted 1 expired transfer confirmation(s) older than 30 days.')
            ->assertExitCode(0);

        // Verify only very old confirmation was deleted
        $this->assertDatabaseMissing('transfer_confirmations', [
            'confirmation_token' => 'very-old-token',
        ]);

        $this->assertDatabaseHas('transfer_confirmations', [
            'confirmation_token' => 'old-token',
        ]);

        $this->assertDatabaseCount('transfer_confirmations', 1);
    }

    /**
     * Test cleanup command with no expired confirmations
     */
    public function test_cleanup_command_with_no_expired_confirmations(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        // Create only recent confirmations
        TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'token-1',
            'expires_at' => now()->subDays(5),
        ]);

        TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'token-2',
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertDatabaseCount('transfer_confirmations', 2);

        // Run cleanup
        $this->artisan('transfers:cleanup-expired')
            ->expectsOutput('Deleted 0 expired transfer confirmation(s) older than 7 days.')
            ->assertExitCode(0);

        // Verify nothing was deleted
        $this->assertDatabaseCount('transfer_confirmations', 2);
    }

    /**
     * Test cleanup command deletes confirmed transfers
     */
    public function test_cleanup_command_deletes_confirmed_transfers(): void
    {
        $user = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        // Create old confirmed transfer
        TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'confirmed-old',
            'expires_at' => now()->subDays(10),
            'confirmed' => true,
            'confirmed_at' => now()->subDays(10),
        ]);

        // Create old unconfirmed transfer
        TransferConfirmation::create([
            'user_id' => $user->id,
            'recipient_id' => $recipient->id,
            'amount' => 25,
            'confirmation_token' => 'unconfirmed-old',
            'expires_at' => now()->subDays(10),
            'confirmed' => false,
        ]);

        $this->assertDatabaseCount('transfer_confirmations', 2);

        // Run cleanup
        $this->artisan('transfers:cleanup-expired')
            ->expectsOutput('Deleted 2 expired transfer confirmation(s) older than 7 days.')
            ->assertExitCode(0);

        // Verify both were deleted
        $this->assertDatabaseCount('transfer_confirmations', 0);
    }

    /**
     * Test scheduled cleanup is configured
     */
    public function test_scheduled_cleanup_is_configured(): void
    {
        // This test verifies the schedule is defined
        // In production, scheduler would run this daily

        $schedule = app()->make(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events())->filter(function ($event) {
            return str_contains($event->command, 'transfers:cleanup-expired');
        });

        $this->assertGreaterThan(0, $events->count(), 'Cleanup command is not scheduled');
    }
}
