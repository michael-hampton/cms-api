<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddVoucherDiscountFields extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function ($table) {
            $table->boolean('is_stackable')->default(false);
            $table->boolean('is_merchant_funded')->default(false);
            $table->integer('merchant_id')->nullable();
            $table->integer('campaign_id')->nullable();
        });

        Schema::table('voucher_redemptions', function ($table) {
            $table->integer('campaign_id')->nullable();
            $table->integer('merchant_id')->nullable();
        });

        // Create pivot table for voucher-subscription plan relationships
        Schema::create('voucher_subscription_plan', function ($table) {
            $table->id();
            $table->integer('voucher_id');
            $table->integer('subscription_plan_id');
            $table->timestamps();

            $table->unique(['voucher_id', 'subscription_plan_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
