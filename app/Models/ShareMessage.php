<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShareMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'telegram_client_account_id',
        'requested_by_chat_id',
        'message_text',
        'status',
        'total_groups',
        'sent_count',
        'failed_count',
        'started_at',
        'completed_at',
        'last_error',
        'meta',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
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
