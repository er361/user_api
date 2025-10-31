<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class UserPolicy
{
    public function update(User $currentUser, User $user): bool
    {
        $allowed = $currentUser->id === $user->id;

        if (!$allowed) {
            Log::warning('Unauthorized user update attempt', [
                'current_user_id' => $currentUser->id,
                'target_user_id' => $user->id,
            ]);
        }

        return $allowed;
    }

    /**
     * Determine if user can update any user's balance (admin only)
     */
    public function updateAnyBalance(User $currentUser): bool
    {
        if (!$currentUser->is_admin) {
            Log::warning('Non-admin attempted to update balance', [
                'user_id' => $currentUser->id,
            ]);
        }

        return $currentUser->is_admin;
    }
}
