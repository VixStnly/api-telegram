<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramClientGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_client_account_id',
        'invite_link',
        'chat_id',
        'username',
        'title',
        'status',
        'last_verified_at',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'last_verified_at' => 'datetime',
        'meta' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(TelegramClientAccount::class, 'telegram_client_account_id');
    }

    public function deliveries()
    {
        return $this->hasMany(ShareMessageDelivery::class);
    }
}
