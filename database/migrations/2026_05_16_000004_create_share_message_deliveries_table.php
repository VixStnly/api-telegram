<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_message_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('share_message_id')
                ->constrained('share_messages')
                ->cascadeOnDelete();

            $table->foreignId('telegram_client_group_id')
                ->nullable()
                ->constrained('telegram_client_groups')
                ->nullOnDelete();

            $table->string('chat_id')->nullable()->index();
            $table->string('status')->default('queued')->index();
            $table->string('telegram_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['share_message_id', 'status'], 'idx_delivery_share_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_message_deliveries');
    }
};
