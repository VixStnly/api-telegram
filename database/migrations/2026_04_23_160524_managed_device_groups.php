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
        Schema::create('managed_device_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('managed_device_id')
                ->constrained('managed_devices')
                ->cascadeOnDelete();

            $table->string('group_key')->index();
            $table->string('group_name')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['managed_device_id', 'group_key'], 'uniq_device_group_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('managed_device_groups');
    }
};