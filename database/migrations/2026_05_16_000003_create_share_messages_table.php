<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('telegram_client_account_id')
                ->constrained('telegram_client_accounts')
                ->cascadeOnDelete();

            $table->string('requested_by_chat_id')->nullable()->index();
            $table->text('message_text');
            $table->string('status')->default('queued')->index();

            $table->unsignedInteger('total_groups')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['telegram_client_account_id', 'status'], 'idx_share_account_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_messages');
    }
};
