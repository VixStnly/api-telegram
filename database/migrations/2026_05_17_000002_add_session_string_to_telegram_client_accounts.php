<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telegram_client_accounts')) {
            return;
        }

        Schema::table('telegram_client_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('telegram_client_accounts', 'session_string')) {
                $table->longText('session_string')->nullable()->after('session_file');
            }
        });

        if (Schema::hasColumn('telegram_client_accounts', 'pending_session_string')) {
            DB::table('telegram_client_accounts')
                ->whereNull('session_string')
                ->whereNotNull('pending_session_string')
                ->update([
                    'session_string' => DB::raw('pending_session_string'),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('telegram_client_accounts')) {
            return;
        }

        Schema::table('telegram_client_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('telegram_client_accounts', 'session_string')) {
                $table->dropColumn('session_string');
            }
        });
    }
};
