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
        Schema::create('auto_reply_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('managed_device_id')
                ->nullable()
                ->constrained('managed_devices')
                ->nullOnDelete();

            $table->string('name');
            $table->enum('match_type', ['exact', 'contains', 'regex'])->default('contains');
            $table->text('pattern');

            $table->boolean('case_sensitive')->default(false);
            $table->text('reply_text');

            $table->unsignedInteger('priority')->default(100)->index();
            $table->unsignedInteger('cooldown_seconds')->default(0);

            $table->boolean('is_active')->default(true)->index();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['managed_device_id', 'is_active', 'priority'], 'idx_rule_device_active_priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_reply_rules');
    }
};