<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'title',
        'username',
        'type',
        'is_active',
        'is_allowed_for_broadcast',
        'last_seen_at',
        'meta',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_allowed_for_broadcast' => 'boolean',
        'last_seen_at' => 'datetime',
        'meta' => 'array',
    ];
}