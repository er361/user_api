<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferConfirmation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipient_id',
        'amount',
        'confirmation_token',
        'expires_at',
        'confirmed',
        'confirmed_at',
        'failed_attempts',
    ];

    const MAX_ATTEMPTS = 3;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'confirmed' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return !$this->confirmed
            && !$this->isExpired()
            && $this->failed_attempts < self::MAX_ATTEMPTS;
    }

    public function isBlocked(): bool
    {
        return $this->failed_attempts >= self::MAX_ATTEMPTS;
    }

    public function incrementFailedAttempts(): void
    {
        $this->increment('failed_attempts');
    }
}
