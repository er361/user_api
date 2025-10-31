<?php

namespace App\Actions\User;

use App\Exceptions\SelfTransferException;
use App\Models\TransferConfirmation;
use App\Models\User;
use Illuminate\Support\Str;

class InitiateTransferAction
{
    public function execute(User $sender, User $recipient, string $amount): TransferConfirmation
    {
        if ($sender->id === $recipient->id) {
            throw new SelfTransferException();
        }

        $amount = number_format((float)$amount, 2, '.', '');
        $token = Str::random(64);

        return TransferConfirmation::create([
            'user_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'amount' => $amount,
            'confirmation_token' => $token,
            'expires_at' => now()->addMinutes(15),
        ]);
    }
}
