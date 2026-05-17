<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telegram_client_accounts')) {
            return;
        }

        Schema::table('telegram_client_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_client_accounts', 'pending_2fa_password')) {
                $table->text('pending_2fa_password')->nullable()->after('pending_otp_requested_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('telegram_client_accounts')) {
            return;
        }

        Schema::table('telegram_client_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_client_accounts', 'pending_2fa_password')) {
                $table->dropColumn('pending_2fa_password');
            }
        });
    }
};
