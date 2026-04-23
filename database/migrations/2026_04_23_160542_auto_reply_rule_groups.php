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
        Schema::create('auto_reply_rule_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignId('auto_reply_rule_id')
                ->constrained('auto_reply_rules')
                ->cascadeOnDelete();

            $table->string('group_key')->index();

            $table->timestamps();

            $table->unique(['auto_reply_rule_id', 'group_key'], 'uniq_rule_group_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_reply_rule_groups');
    }
};