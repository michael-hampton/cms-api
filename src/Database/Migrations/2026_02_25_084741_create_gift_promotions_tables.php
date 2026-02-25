<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateGiftPromotionsTables extends Migration
{
    public function up(): void
    {
        Schema::create('gift_promotions', function (Blueprint $table) {
            $table->id();

            // nullable = platform-level promotion; set = merchant-specific
            $table->foreignId('merchant_id');

            // What is being gifted
            $table->enum('gift_type', ['product', 'subscription']);
            $table->foreignId('gift_product_id');
            $table->foreignId('gift_subscription_plan_id');

            $table->foreign('gift_product_id')->references('id')->on('products');
            $table->foreign('gift_subscription_plan_id')->references('id')->on('subscription_plans');
            $table->foreign('merchant_id')->references('id')->on('merchants');

            // Resolution behaviour
            $table->enum('quantity_rule', ['one_per_qualifying', 'cap', 'merge'])
                ->default('one_per_qualifying');
            $table->unsignedSmallInteger('max_per_order')->default(1);

            // Stacking / exclusivity
            // exclusive = true suppresses all non-exclusive promotions within same merchant scope
            $table->boolean('exclusive')->default(false);
            $table->unsignedSmallInteger('priority')->default(0);

            // Scheduling
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();

            // Constraint: exactly one of gift_product_id / gift_subscription_plan_id must be set.
            // Enforced at application layer (GiftPromotionRepository::create validates this).
        });

        Schema::create('gift_promotion_triggers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('promotion_id');

            $table->foreign('promotion_id')->on('gift_promotions')->references('id')->cascadeOnDelete();

            $table->enum('type', [
                'product',
                'subscription_plan',
                'cart_total',
                'item_count',
                'category',
                'first_time_buyer',
            ]);

            $table->enum('operator', ['=', 'in', '>=', '<=']);

            // FK for entity-based triggers (product, subscription_plan, category)
            $table->unsignedBigInteger('reference_id')->nullable();

            // Threshold for numeric triggers (cart_total, item_count)
            // Also used for 'in' operator as JSON array of IDs: [1, 2, 3]
            $table->decimal('value', 12, 4)->nullable();
            $table->json('value_set')->nullable();

            // Grouping: triggers with the same group_key are AND-ed.
            // Different group_keys are OR-ed. Single character or short string.
            $table->string('group_key', 8)->default('A');

            // If true, this condition must NOT be met for the group to pass.
            $table->boolean('negated')->default(false);

            $table->timestamps();

            $table->index(['promotion_id', 'group_key']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
