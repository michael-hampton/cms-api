<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateMemberRewards extends Migration
{
    public function up(): void
    {
        Schema::create('reward_definitions', function ($table) {
            $table->id();
            $table->foreignId('site_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('reward_type'); // voucher, discount, physical_item, points
            $table->json('criteria'); // What triggers this reward
            $table->json('reward_config'); // Voucher details, discount amount, etc.
            $table->integer('max_claims_per_member')->default(1);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->index(['site_id', 'is_active']);
            $table->index('slug');
        });

        Schema::create('member_rewards', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('reward_definition_id');
            $table->foreignId('site_id');
            $table->string('status', 20)->default('pending'); // pending, claimed, expired, redeemed
            $table->timestamp('earned_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('reward_data')->nullable(); // Voucher code, tracking info, etc.
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('reward_definition_id')->references('id')->on('reward_definitions')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->index(['member_id', 'status']);
            $table->index('status');
            $table->index('expires_at');
        });

        Schema::create('reward_voucher_codes', function ($table) {
            $table->id();
            $table->foreignId('reward_definition_id');
            $table->foreignId('site_id');
            $table->string('voucher_code', 100);
            $table->string('provider'); // amazon, tesco, etc.
            $table->decimal('value', 10, 2);
            $table->string('currency', 3)->default('GBP');
            $table->foreignId('assigned_to_member_id')->nullable();
            $table->foreignId('member_reward_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->boolean('is_used')->default(false);
            $table->timestamps();

            $table->foreign('reward_definition_id')->references('id')->on('reward_definitions')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('assigned_to_member_id')->references('id')->on('members')->cascadeOnDelete();

            $table->unique('voucher_code');
            $table->index(['reward_definition_id', 'is_used']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
