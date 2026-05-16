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
            $table->string('pending_otp_code')->nullable()->after('phone_code_hash');
            $table->timestamp('pending_otp_requested_at')->nullable()->after('pending_otp_code');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('telegram_client_accounts')) {
            return;
        }

        Schema::table('telegram_client_accounts', function (Blueprint $table) {
            $table->dropColumn(['pending_otp_code', 'pending_otp_requested_at']);
        });
    }
};
