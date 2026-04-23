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
        Schema::create('managed_devices', function (Blueprint $table) {
            $table->id();

            $table->string('device_name');
            $table->string('device_code')->unique();

            $table->string('account_label')->nullable();
            $table->string('account_identifier')->nullable()->index();

            $table->string('platform')->nullable(); 
            $table->string('session_name')->nullable();
            $table->string('session_token')->nullable();

            $table->string('status')->default('inactive')->index(); 
            $table->timestamp('last_seen_at')->nullable();

            $table->json('meta')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('managed_devices');
    }
};