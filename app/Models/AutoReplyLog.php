<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoReplyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'managed_device_id',
        'group_key',
        'group_name',
        'sender_key',
        'sender_name',
        'message_text',
        'matched_rule_id',
        'is_matched',
        'is_replied',
        'skip_reason',
        'reply_text',
        'meta',
        'processed_at',
    ];

    protected $casts = [
        'is_matched' => 'boolean',
        'is_replied' => 'boolean',
        'meta' => 'array',
        'processed_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(ManagedDevice::class, 'managed_device_id');
    }

    public function rule()
    {
        return $this->belongsTo(AutoReplyRule::class, 'matched_rule_id');
    }
}