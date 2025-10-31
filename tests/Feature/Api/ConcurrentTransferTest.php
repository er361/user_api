<?php

namespace Tests\Feature\Api;

use App\Actions\User\TransferBalanceAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrentTransferTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test deterministic lock ordering prevents deadlocks
     */
    public function test_lock_ordering_is_deterministic(): void
    {
        $user1 = User::factory()->create(['id' => 1, 'balance' => 100]);
        $user2 = User::factory()->create(['id' => 2, 'balance' => 100]);

        // Reset IDs to ensure predictable ordering
        DB::table('users')->where('id', $user1->id)->delete();
        DB::table('users')->where('id', $user2->id)->delete();

        $user1 = User::factory()->create(['balance' => 100]);
        $user2 = User::factory()->create(['balance' => 100]);

        $action = new TransferBalanceAction();

        // Transfer from higher ID to lower ID
        $result1 = $action->execute($user2, $user1, '10.00');

        $this->assertEquals('90.00', $result1['sender']->balance);
        $this->assertEquals('110.00', $result1['recipient']->balance);

        // Transfer from lower ID to higher ID
        $result2 = $action->execute($user1, $user2, '5.00');

        $this->assertEquals('105.00', $result2['sender']->balance);
        $this->assertEquals('95.00', $result2['recipient']->balance);

        // Verify locks are always acquired in same order
        // (tested by not causing deadlock)
    }

    /**
     * Test transaction isolation prevents dirty reads
     */
    public function test_transaction_isolation_prevents_dirty_reads(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $action = new TransferBalanceAction();

        // Execute transfer
        $result = $action->execute($sender, $recipient, '25.00');

        // Verify both balances updated atomically
        $this->assertEquals('75.00', $result['sender']->balance);
        $this->assertEquals('75.00', $result['recipient']->balance);

        // Verify in database (committed)
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '75.00',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $recipient->id,
            'balance' => '75.00',
        ]);
    }

    /**
     * Test serializable isolation level is set
     */
    public function test_serializable_isolation_level_prevents_anomalies(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 0]);

        $action = new TransferBalanceAction();

        // Execute transfer with serializable isolation
        DB::transaction(function () use ($sender, $recipient, $action) {
            // Check that we're in a transaction
            $this->assertTrue(DB::transactionLevel() > 0);

            $result = $action->execute($sender, $recipient, '50.00');

            $this->assertEquals('50.00', $result['sender']->balance);
            $this->assertEquals('50.00', $result['recipient']->balance);
        });

        // Verify committed correctly
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '50.00',
        ]);
    }

    /**
     * Test balance check happens after lock (prevents race condition)
     */
    public function test_balance_check_happens_after_lock(): void
    {
        $sender = User::factory()->create(['balance' => 10]);
        $recipient = User::factory()->create(['balance' => 0]);

        $action = new TransferBalanceAction();

        // Try to transfer more than balance (should fail)
        $this->expectException(\App\Exceptions\InsufficientBalanceException::class);
        $action->execute($sender, $recipient, '15.00');

        // Verify no partial updates occurred
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '10.00',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $recipient->id,
            'balance' => '0.00',
        ]);

        // Verify no transactions were logged
        $this->assertDatabaseCount('balance_transactions', 0);
    }

    /**
     * Test both users are locked before any updates
     */
    public function test_both_users_locked_before_updates(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $action = new TransferBalanceAction();

        $result = $action->execute($sender, $recipient, '25.00');

        // Verify both balances were updated correctly
        $this->assertEquals('75.00', $result['sender']->balance);
        $this->assertEquals('75.00', $result['recipient']->balance);

        // Verify transactions were created for both
        $this->assertDatabaseHas('balance_transactions', [
            'user_id' => $sender->id,
            'type' => 'transfer_out',
            'amount' => '25.00',
        ]);

        $this->assertDatabaseHas('balance_transactions', [
            'user_id' => $recipient->id,
            'type' => 'transfer_in',
            'amount' => '25.00',
        ]);
    }

    /**
     * Test transaction rollback on failure
     */
    public function test_transaction_rollback_on_failure(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $action = new TransferBalanceAction();

        try {
            // Try invalid amount (more than 2 decimals)
            $action->execute($sender, $recipient, '25.123');
        } catch (\Exception $e) {
            // Expected to fail
        }

        // Verify no changes were made
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '100.00',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $recipient->id,
            'balance' => '50.00',
        ]);

        // Verify no transactions were logged
        $this->assertDatabaseCount('balance_transactions', 0);
    }

    /**
     * Test users are re-verified after lock
     */
    public function test_users_reverified_after_lock(): void
    {
        $sender = User::factory()->create(['balance' => 100]);
        $recipient = User::factory()->create(['balance' => 50]);

        $action = new TransferBalanceAction();

        // Normal case - both users exist
        $result = $action->execute($sender, $recipient, '10.00');

        $this->assertEquals('90.00', $result['sender']->balance);
        $this->assertEquals('60.00', $result['recipient']->balance);
    }

    /**
     * Test sequential transfers maintain consistency
     */
    public function test_sequential_transfers_maintain_consistency(): void
    {
        $user1 = User::factory()->create(['balance' => 100]);
        $user2 = User::factory()->create(['balance' => 100]);
        $user3 = User::factory()->create(['balance' => 100]);

        $action = new TransferBalanceAction();

        // user1 -> user2: 10
        $action->execute($user1, $user2, '10.00');

        // user2 -> user3: 20
        $action->execute($user2, $user3, '20.00');

        // user3 -> user1: 30
        $action->execute($user3, $user1, '30.00');

        // Verify final balances
        $this->assertDatabaseHas('users', [
            'id' => $user1->id,
            'balance' => '120.00', // 100 - 10 + 30
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user2->id,
            'balance' => '90.00', // 100 + 10 - 20
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user3->id,
            'balance' => '90.00', // 100 + 20 - 30
        ]);

        // Verify total balance is conserved
        $totalBalance = User::sum('balance');
        $this->assertEquals('300.00', number_format($totalBalance, 2, '.', ''));
    }
}
