<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('telegram_client_accounts')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE telegram_client_accounts MODIFY phone_code_hash TEXT NULL');
    }

    public function down(): void
    {
        if (!Schema::hasTable('telegram_client_accounts')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE telegram_client_accounts MODIFY phone_code_hash VARCHAR(255) NULL');
    }
};
