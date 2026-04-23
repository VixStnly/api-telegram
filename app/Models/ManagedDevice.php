<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagedDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_name',
        'device_code',
        'account_label',
        'account_identifier',
        'platform',
        'session_name',
        'session_token',
        'status',
        'last_seen_at',
        'meta',
        'is_active',
    ];

    protected $casts = [
        'meta' => 'array',
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function groups()
    {
        return $this->hasMany(ManagedDeviceGroup::class);
    }

    public function rules()
    {
        return $this->hasMany(AutoReplyRule::class);
    }

    public function logs()
    {
        return $this->hasMany(AutoReplyLog::class);
    }
}