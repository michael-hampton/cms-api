<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateRewardClicksTable extends Migration
{
    public function up(): void
    {
        Schema::create('reward_clicks', function ($table) {
            $table->id();
            $table->foreignId('member_reward_id');
            $table->foreignId('member_id');
            $table->integer('site_id');
            $table->string('action', 50); // 'view', 'claim', 'copy_code'
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('member_reward_id')->references('id')->on('member_rewards')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();

            $table->index('member_reward_id');
            $table->index('member_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
