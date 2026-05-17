<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramAccessCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'label',
        'is_active',
        'max_uses',
        'used_count',
        'expires_at',
        'last_used_at',
        'notes',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'meta' => 'array',
    ];

    public function isAvailable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->max_uses === null || $this->used_count < $this->max_uses;
    }

    public function remainingUses(): ?int
    {
        if ($this->max_uses === null) {
            return null;
        }

        return max(0, $this->max_uses - $this->used_count);
    }

    public function isQuotaExhausted(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    public function markUsed(): void
    {
        $this->forceFill([
            'used_count' => $this->used_count + 1,
            'last_used_at' => now(),
        ])->save();
    }
}
