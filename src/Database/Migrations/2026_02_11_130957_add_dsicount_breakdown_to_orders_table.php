<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDsicountBreakdownToOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('offer_discount_cents')->default(0)->after('discount');
            $table->integer('voucher_discount_cents')->default(0)->after('offer_discount_cents');
            $table->integer('reward_discount_cents')->default(0)->after('voucher_discount_cents');
            $table->integer('tiered_discount_cents')->default(0)->after('reward_discount_cents');
            $table->integer('store_credit_cents')->default(0)->after('tiered_discount_cents');
            $table->integer('merchant_funded_cents')->default(0)->after('store_credit_cents');
            $table->integer('platform_funded_cents')->default(0)->after('merchant_funded_cents');
            $table->integer('customer_credit_cents')->default(0)->after('platform_funded_cents');

            // Keep existing discount column for backward compatibility
            // It will store total discount for now
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
