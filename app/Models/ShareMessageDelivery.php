<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShareMessageDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'share_message_id',
        'telegram_client_group_id',
        'chat_id',
        'status',
        'telegram_message_id',
        'error_message',
        'sent_at',
        'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'meta' => 'array',
    ];

    public function shareMessage()
    {
        return $this->belongsTo(ShareMessage::class);
    }

    public function group()
    {
        return $this->belongsTo(TelegramClientGroup::class, 'telegram_client_group_id');
    }
}
