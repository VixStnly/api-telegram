<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramClientAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'bot_chat_id',
        'bot_user_id',
        'bot_username',
        'bot_first_name',
        'phone_number',
        'session_name',
        'session_file',
        'pending_session_string',
        'auth_status',
        'phone_code_hash',
        'pending_otp_code',
        'pending_otp_requested_at',
        'pending_login_token',
        'last_login_at',
        'last_seen_at',
        'subscription_expires_at',
        'is_active',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'pending_otp_requested_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function groups()
    {
        return $this->hasMany(TelegramClientGroup::class);
    }

    public function shareMessages()
    {
        return $this->hasMany(ShareMessage::class);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscription_expires_at === null
            || $this->subscription_expires_at->isFuture();
    }
}
