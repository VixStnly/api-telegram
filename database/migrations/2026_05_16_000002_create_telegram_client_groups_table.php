<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_client_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('telegram_client_account_id')
                ->constrained('telegram_client_accounts')
                ->cascadeOnDelete();

            $table->string('invite_link')->nullable();
            $table->string('chat_id')->nullable()->index();
            $table->string('username')->nullable()->index();
            $table->string('title')->nullable();

            $table->string('status')->default('pending')->index();
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['telegram_client_account_id', 'status'], 'idx_client_group_account_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_client_groups');
    }
};
