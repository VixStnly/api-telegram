<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('telegram_client_accounts')) {
            return;
        }

        Schema::table('telegram_client_accounts', function (Blueprint $table) {
            $table->longText('pending_session_string')->nullable()->after('session_file');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('telegram_client_accounts')) {
            return;
        }

        Schema::table('telegram_client_accounts', function (Blueprint $table) {
            $table->dropColumn('pending_session_string');
        });
    }
};
