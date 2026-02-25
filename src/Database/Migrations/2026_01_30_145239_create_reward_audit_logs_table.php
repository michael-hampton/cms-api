<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateRewardAuditLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('reward_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_reward_id')->nullable();
            $table->unsignedBigInteger('reward_definition_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // Admin who performed action
            $table->string('action'); // created, updated, claimed, declined, expired, status_changed
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->text('notes')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('member_reward_id')->references('id')->on('member_rewards')->onDelete('cascade');
            $table->foreign('reward_definition_id')->references('id')->on('reward_definitions')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['member_reward_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
