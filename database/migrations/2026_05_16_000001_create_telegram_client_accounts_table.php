<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_client_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('bot_chat_id')->unique();
            $table->string('bot_user_id')->nullable()->index();
            $table->string('bot_username')->nullable()->index();
            $table->string('bot_first_name')->nullable();

            $table->string('phone_number')->nullable()->unique();
            $table->string('session_name')->unique();
            $table->string('session_file')->nullable();

            $table->string('auth_status')->default('awaiting_phone')->index();
            $table->string('phone_code_hash')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();

            $table->timestamp('subscription_expires_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();

            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_client_accounts');
    }
};
