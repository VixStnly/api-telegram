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
        Schema::create('telegram_groups', function (Blueprint $table) {
            $table->id();

            $table->string('chat_id')->unique();
            $table->string('title')->nullable();
            $table->string('username')->nullable()->index();
            $table->string('type')->nullable()->index(); // group, supergroup, channel

            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_allowed_for_broadcast')->default(true)->index();

            $table->timestamp('last_seen_at')->nullable()->index();
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_groups');
    }
};