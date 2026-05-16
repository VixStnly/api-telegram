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
            $table->string('pending_login_token')->nullable()->after('pending_otp_requested_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('telegram_client_accounts')) {
            return;
        }

        Schema::table('telegram_client_accounts', function (Blueprint $table) {
            $table->dropColumn('pending_login_token');
        });
    }
};
