<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManagedDeviceGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'managed_device_id',
        'group_key',
        'group_name',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(ManagedDevice::class, 'managed_device_id');
    }
}