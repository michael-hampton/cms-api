<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddStripeCouponToVouchers extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function ($table) {
            $table->string('stripe_coupon_id')->nullable()->after('code');
            $table->integer('duration_in_months')->nullable()->after('applies_to_subscriptions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
