<?php

namespace Database\Seeders;

use App\Models\ManagedDevice;
use App\Models\ManagedDeviceGroup;
use Illuminate\Database\Seeder;

class ManagedDeviceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $device = ManagedDevice::create([
            'device_name' => 'Device Telegram Bisnis A',
            'device_code' => 'DEV-BISNIS-A001',
            'account_label' => 'Telegram Bisnis Utama',
            'account_identifier' => 'akun_bisnis_utama',
            'platform' => 'telegram',
            'session_name' => 'session_bisnis_utama',
            'session_token' => null,
            'status' => 'inactive',
            'last_seen_at' => null,
            'meta' => [
                'note' => 'Device contoh untuk testing',
            ],
            'is_active' => true,
        ]);

        ManagedDeviceGroup::create([
            'managed_device_id' => $device->id,
            'group_key' => 'cabang_a',
            'group_name' => 'Grup Cabang A',
            'meta' => null,
        ]);

        ManagedDeviceGroup::create([
            'managed_device_id' => $device->id,
            'group_key' => 'cabang_b',
            'group_name' => 'Grup Cabang B',
            'meta' => null,
        ]);
    }
}