<?php

namespace App\Actions\User;

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidTransferAmountException;
use App\Exceptions\SelfTransferException;
use App\Models\BalanceTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransferBalanceAction
{
    public function execute(User $sender, User $recipient, string $amount): array
    {
        if ($sender->id === $recipient->id) {
            throw new SelfTransferException();
        }

        if (!is_numeric($amount) || bccomp($amount, '0', 2) <= 0) {
            throw new InvalidTransferAmountException();
        }

        if (strpos($amount, '.') !== false) {
            $decimals = strlen(substr($amount, strpos($amount, '.') + 1));
            if ($decimals > 2) {
                throw new InvalidTransferAmountException('Amount must have at most 2 decimal places');
            }
        }

        $amount = number_format((float)$amount, 2, '.', '');

        if (DB::transactionLevel() === 0) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        }

        return DB::transaction(function () use ($sender, $recipient, $amount) {
            $firstId = min($sender->id, $recipient->id);
            $secondId = max($sender->id, $recipient->id);

            $firstUser = User::where('id', $firstId)->lockForUpdate()->first();
            $secondUser = User::where('id', $secondId)->lockForUpdate()->first();

            if (!$firstUser || !$secondUser) {
                throw new \RuntimeException('User not found during transaction');
            }

            $lockedSender = $sender->id === $firstId ? $firstUser : $secondUser;
            $lockedRecipient = $recipient->id === $firstId ? $firstUser : $secondUser;

            $senderBalanceBefore = $lockedSender->balance;
            $recipientBalanceBefore = $lockedRecipient->balance;

            if (bccomp($senderBalanceBefore, $amount, 2) < 0) {
                throw new InsufficientBalanceException();
            }

            $newSenderBalance = bcsub($senderBalanceBefore, $amount, 2);
            $newRecipientBalance = bcadd($recipientBalanceBefore, $amount, 2);

            $lockedSender->balance = $newSenderBalance;
            $lockedSender->saveOrFail();

            $lockedRecipient->balance = $newRecipientBalance;
            $lockedRecipient->saveOrFail();

            BalanceTransaction::create([
                'user_id' => $lockedSender->id,
                'type' => 'transfer_out',
                'amount' => $amount,
                'balance_before' => $senderBalanceBefore,
                'balance_after' => $lockedSender->balance,
                'related_user_id' => $lockedRecipient->id,
                'description' => "Transfer to user {$lockedRecipient->id}",
            ]);

            BalanceTransaction::create([
                'user_id' => $lockedRecipient->id,
                'type' => 'transfer_in',
                'amount' => $amount,
                'balance_before' => $recipientBalanceBefore,
                'balance_after' => $lockedRecipient->balance,
                'related_user_id' => $lockedSender->id,
                'description' => "Transfer from user {$lockedSender->id}",
            ]);

            return [
                'sender' => $lockedSender->fresh(),
                'recipient' => $lockedRecipient->fresh(),
            ];
        });
    }
}
