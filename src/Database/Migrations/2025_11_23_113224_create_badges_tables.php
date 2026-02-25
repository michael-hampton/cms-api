<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateBadgesTables extends Migration
{
    public function up(): void
    {
        Schema::create('badges', function ($table) {
            $table->id();
            $table->foreignId('site_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // icon URL or emoji
            $table->string('tier')->default('bronze'); // bronze, silver, gold, platinum
            $table->string('category'); // engagement, loyalty, content, special
            $table->json('criteria'); // Rules for earning the badge
            $table->integer('points')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('site_id');
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
        });

        Schema::create('member_badges', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('badge_id');
            $table->timestamp('earned_at');
            $table->json('criteria_met')->nullable(); // Snapshot of what was met
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['member_id', 'badge_id']);
            $table->index('earned_at');
            $table->index('is_visible');
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('badge_id')->references('id')->on('badges')->cascadeOnDelete();
        });

        Schema::create('member_activities', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('site_id');
            $table->string('activity_type'); // comment, like, read, share, purchase
            $table->string('entity_type')->nullable(); // page, product, order
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('points')->default(0);
            $table->timestamp('activity_date');
            $table->timestamps();

            $table->index(['member_id', 'activity_type', 'activity_date']);
            $table->index(['entity_type', 'entity_id']);
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });

        Schema::create('product_badges', function ($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('badge_type'); // bestseller, new, featured, trending, eco-friendly
            $table->string('label');
            $table->string('color')->default('#3b82f6');
            $table->string('icon')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'badge_type']);
            $table->index('is_active');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::create('member_points', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->integer('points');
            $table->string('reason');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->index(['member_id', 'awarded_at']);
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
