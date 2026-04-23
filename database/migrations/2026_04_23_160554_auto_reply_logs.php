<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auto_reply_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('managed_device_id')
                ->nullable()
                ->constrained('managed_devices')
                ->nullOnDelete();

            $table->string('group_key')->nullable()->index();
            $table->string('group_name')->nullable();

            $table->string('sender_key')->nullable()->index();
            $table->string('sender_name')->nullable();

            $table->text('message_text')->nullable();

            $table->foreignId('matched_rule_id')
                ->nullable()
                ->constrained('auto_reply_rules')
                ->nullOnDelete();

            $table->boolean('is_matched')->default(false)->index();
            $table->boolean('is_replied')->default(false)->index();

            $table->string('skip_reason')->nullable()->index();
            $table->text('reply_text')->nullable();

            $table->json('meta')->nullable();

            $table->timestamp('processed_at')->nullable()->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_reply_logs');
    }
};