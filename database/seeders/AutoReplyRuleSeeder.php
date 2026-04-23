<?php

namespace Database\Seeders;

use App\Models\AutoReplyRule;
use App\Models\AutoReplyRuleGroup;
use App\Models\ManagedDevice;
use Illuminate\Database\Seeder;

class AutoReplyRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $device = ManagedDevice::first();

        $rule1 = AutoReplyRule::create([
            'managed_device_id' => $device?->id,
            'name' => 'Balasan Harga',
            'match_type' => 'contains',
            'pattern' => 'harga',
            'case_sensitive' => false,
            'reply_text' => "Halo, untuk info harga silakan cek katalog terbaru.\n\nGrup: {group_name}\nJam: {time}",
            'priority' => 1,
            'cooldown_seconds' => 60,
            'is_active' => true,
            'meta' => null,
        ]);

        AutoReplyRuleGroup::create([
            'auto_reply_rule_id' => $rule1->id,
            'group_key' => 'cabang_a',
        ]);

        AutoReplyRuleGroup::create([
            'auto_reply_rule_id' => $rule1->id,
            'group_key' => 'cabang_b',
        ]);

        AutoReplyRule::create([
            'managed_device_id' => null,
            'name' => 'Balasan Jam Operasional',
            'match_type' => 'regex',
            'pattern' => 'jam\s*(buka|operasional|kerja)',
            'case_sensitive' => false,
            'reply_text' => "Jam operasional kami Senin - Sabtu, 08:00 - 17:00.",
            'priority' => 2,
            'cooldown_seconds' => 120,
            'is_active' => true,
            'meta' => null,
        ]);

        AutoReplyRule::create([
            'managed_device_id' => null,
            'name' => 'Balasan Alamat',
            'match_type' => 'contains',
            'pattern' => 'alamat',
            'case_sensitive' => false,
            'reply_text' => "Alamat cabang bisa dicek di deskripsi grup atau pinned message.",
            'priority' => 3,
            'cooldown_seconds' => 120,
            'is_active' => true,
            'meta' => null,
        ]);
    }
}