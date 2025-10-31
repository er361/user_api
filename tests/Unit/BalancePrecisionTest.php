<?php

namespace Tests\Unit;

use App\Actions\User\TransferBalanceAction;
use App\Actions\User\UpdateUserBalanceAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BalancePrecisionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test bcmath is used for balance subtraction
     */
    public function test_transfer_uses_bcmath_for_precision(): void
    {
        $sender = User::factory()->create(['balance' => '100.99']);
        $recipient = User::factory()->create(['balance' => '50.01']);

        $action = new TransferBalanceAction();
        $result = $action->execute($sender, $recipient, '10.50');

        // Verify exact precision (no floating point errors)
        $this->assertEquals('90.49', $result['sender']->balance);
        $this->assertEquals('60.51', $result['recipient']->balance);

        // Verify in database as well
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '90.49',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $recipient->id,
            'balance' => '60.51',
        ]);
    }

    /**
     * Test precision is maintained for multiple decimal operations
     */
    public function test_multiple_transfers_maintain_precision(): void
    {
        $sender = User::factory()->create(['balance' => '100.00']);
        $recipient = User::factory()->create(['balance' => '0.00']);

        $action = new TransferBalanceAction();

        // Transfer 1: 33.33
        $action->execute($sender, $recipient, '33.33');
        $sender->refresh();
        $this->assertEquals('66.67', $sender->balance);

        // Transfer 2: 33.33
        $action->execute($sender, $recipient, '33.33');
        $sender->refresh();
        $this->assertEquals('33.34', $sender->balance);

        // Transfer 3: 33.34 (exact balance)
        $action->execute($sender, $recipient, '33.34');
        $sender->refresh();
        $this->assertEquals('0.00', $sender->balance);

        // Transfer 4: 0.01 (should fail - insufficient)
        $this->expectException(\App\Exceptions\InsufficientBalanceException::class);
        $action->execute($sender, $recipient, '0.01');
    }

    /**
     * Test bcmath comparison for balance check
     */
    public function test_balance_check_uses_bcmath_comparison(): void
    {
        $sender = User::factory()->create(['balance' => '10.01']);
        $recipient = User::factory()->create(['balance' => '0.00']);

        $action = new TransferBalanceAction();

        // This should succeed (10.01 >= 10.01)
        $result = $action->execute($sender, $recipient, '10.01');

        $this->assertEquals('0.00', $result['sender']->balance);
        $this->assertEquals('10.01', $result['recipient']->balance);
    }

    /**
     * Test insufficient balance check is precise
     */
    public function test_insufficient_balance_check_is_precise(): void
    {
        $sender = User::factory()->create(['balance' => '10.00']);
        $recipient = User::factory()->create(['balance' => '0.00']);

        $action = new TransferBalanceAction();

        // This should fail (10.00 < 10.01)
        $this->expectException(\App\Exceptions\InsufficientBalanceException::class);
        $action->execute($sender, $recipient, '10.01');
    }

    /**
     * Test balance update uses bcmath for calculations
     */
    public function test_balance_update_uses_bcmath(): void
    {
        $user = User::factory()->create(['balance' => '100.50']);

        $action = new UpdateUserBalanceAction();
        $result = $action->execute($user, '150.75');

        $this->assertEquals('150.75', $result->balance);

        // Verify transaction amount is calculated correctly
        $this->assertDatabaseHas('balance_transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => '50.25', // 150.75 - 100.50
            'balance_before' => '100.50',
            'balance_after' => '150.75',
        ]);
    }

    /**
     * Test debit transaction calculation is precise
     */
    public function test_debit_transaction_calculation_is_precise(): void
    {
        $user = User::factory()->create(['balance' => '100.99']);

        $action = new UpdateUserBalanceAction();
        $result = $action->execute($user, '50.50');

        $this->assertEquals('50.50', $result->balance);

        // Verify debit amount is calculated correctly
        $this->assertDatabaseHas('balance_transactions', [
            'user_id' => $user->id,
            'type' => 'debit',
            'amount' => '50.49', // 100.99 - 50.50
            'balance_before' => '100.99',
            'balance_after' => '50.50',
        ]);
    }

    /**
     * Test amount is normalized to 2 decimal places
     */
    public function test_amount_normalized_to_two_decimals(): void
    {
        $sender = User::factory()->create(['balance' => '100.00']);
        $recipient = User::factory()->create(['balance' => '0.00']);

        $action = new TransferBalanceAction();

        // Pass amount with 1 decimal
        $result = $action->execute($sender, $recipient, '10.5');

        // Should be stored as 10.50
        $this->assertDatabaseHas('balance_transactions', [
            'user_id' => $sender->id,
            'amount' => '10.50',
        ]);
    }

    /**
     * Test very small transfer amounts work correctly
     */
    public function test_very_small_transfer_amounts(): void
    {
        $sender = User::factory()->create(['balance' => '1.00']);
        $recipient = User::factory()->create(['balance' => '0.00']);

        $action = new TransferBalanceAction();
        $result = $action->execute($sender, $recipient, '0.01');

        $this->assertEquals('0.99', $result['sender']->balance);
        $this->assertEquals('0.01', $result['recipient']->balance);
    }

    /**
     * Test large transfer amounts maintain precision
     */
    public function test_large_transfer_amounts_maintain_precision(): void
    {
        $sender = User::factory()->create(['balance' => '999999999.99']);
        $recipient = User::factory()->create(['balance' => '0.00']);

        $action = new TransferBalanceAction();
        $result = $action->execute($sender, $recipient, '123456789.99');

        $this->assertEquals('876543210.00', $result['sender']->balance);
        $this->assertEquals('123456789.99', $result['recipient']->balance);
    }

    /**
     * Test transfer with exact decimal edge cases
     */
    public function test_transfer_with_decimal_edge_cases(): void
    {
        $testCases = [
            ['initial' => '100.00', 'transfer' => '33.33', 'expected' => '66.67'],
            ['initial' => '100.00', 'transfer' => '33.34', 'expected' => '66.66'],
            ['initial' => '99.99', 'transfer' => '0.01', 'expected' => '99.98'],
            ['initial' => '50.50', 'transfer' => '25.25', 'expected' => '25.25'],
        ];

        foreach ($testCases as $case) {
            $sender = User::factory()->create(['balance' => $case['initial']]);
            $recipient = User::factory()->create(['balance' => '0.00']);

            $action = new TransferBalanceAction();
            $result = $action->execute($sender, $recipient, $case['transfer']);

            $this->assertEquals(
                $case['expected'],
                $result['sender']->balance,
                "Failed for initial: {$case['initial']}, transfer: {$case['transfer']}"
            );
        }
    }

    /**
     * Test amount validation rejects more than 2 decimal places
     */
    public function test_rejects_more_than_two_decimal_places(): void
    {
        $sender = User::factory()->create(['balance' => '100.00']);
        $recipient = User::factory()->create(['balance' => '0.00']);

        $action = new TransferBalanceAction();

        $this->expectException(\App\Exceptions\InvalidTransferAmountException::class);
        $action->execute($sender, $recipient, '10.123');
    }

    /**
     * Test balance cannot go negative (database constraint)
     */
    public function test_balance_cannot_go_negative(): void
    {
        $sender = User::factory()->create(['balance' => '10.00']);
        $recipient = User::factory()->create(['balance' => '0.00']);

        $action = new TransferBalanceAction();

        $this->expectException(\App\Exceptions\InsufficientBalanceException::class);
        $action->execute($sender, $recipient, '10.01');

        // Verify balance unchanged
        $this->assertDatabaseHas('users', [
            'id' => $sender->id,
            'balance' => '10.00',
        ]);
    }
}
