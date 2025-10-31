<?php

namespace App\Actions\User;

use App\Models\BalanceTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateUserBalanceAction
{
    public function execute(User $user, string $amount): User
    {
        if (DB::transactionLevel() === 0) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        }

        return DB::transaction(function () use ($user, $amount) {
            if (!is_numeric($amount) || bccomp($amount, '0', 2) < 0) {
                throw new \InvalidArgumentException('Invalid balance amount');
            }

            if (strpos($amount, '.') !== false) {
                $decimals = strlen(substr($amount, strpos($amount, '.') + 1));
                if ($decimals > 2) {
                    throw new \InvalidArgumentException('Balance must have at most 2 decimal places');
                }
            }

            $amount = number_format((float)$amount, 2, '.', '');

            $balanceBefore = $user->balance;

            $user->balance = $amount;
            $user->saveOrFail();

            $comparison = bccomp($amount, $balanceBefore, 2);
            $difference = $comparison > 0
                ? bcsub($amount, $balanceBefore, 2)
                : bcsub($balanceBefore, $amount, 2);

            BalanceTransaction::create([
                'user_id' => $user->id,
                'type' => $comparison > 0 ? 'credit' : 'debit',
                'amount' => $difference,
                'balance_before' => $balanceBefore,
                'balance_after' => $amount,
                'description' => 'Balance updated by admin',
            ]);

            return $user->fresh();
        });
    }
}
