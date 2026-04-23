<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AutoReplyRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'managed_device_id',
        'name',
        'match_type',
        'pattern',
        'case_sensitive',
        'reply_text',
        'priority',
        'cooldown_seconds',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'case_sensitive' => 'boolean',
        'priority' => 'integer',
        'cooldown_seconds' => 'integer',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(ManagedDevice::class, 'managed_device_id');
    }

    public function groups()
    {
        return $this->hasMany(AutoReplyRuleGroup::class);
    }

    public function logs()
    {
        return $this->hasMany(AutoReplyLog::class, 'matched_rule_id');
    }
}