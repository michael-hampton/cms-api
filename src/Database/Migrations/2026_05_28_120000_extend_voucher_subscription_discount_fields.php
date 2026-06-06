<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class ExtendVoucherSubscriptionDiscountFields extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            if (!Schema::hasColumn('vouchers', 'discount_type')) {
                $table->string('discount_type')->nullable()->after('value');
            }

            if (!Schema::hasColumn('vouchers', 'discount_amount')) {
                $table->unsignedInteger('discount_amount')->nullable()->after('discount_type');
            }

            if (!Schema::hasColumn('vouchers', 'discount_percentage')) {
                $table->unsignedInteger('discount_percentage')->nullable()->after('discount_amount');
            }

            if (!Schema::hasColumn('vouchers', 'applies_to_orders')) {
                $table->boolean('applies_to_orders')->default(true)->after('per_user_limit');
            }

            if (!Schema::hasColumn('vouchers', 'subscription_discount_duration')) {
                $table->string('subscription_discount_duration')->nullable()->after('applies_to_subscriptions');
            }

            if (!Schema::hasColumn('vouchers', 'subscription_duration_months')) {
                $table->unsignedInteger('subscription_duration_months')->nullable()->after('subscription_discount_duration');
            }

            if (!Schema::hasColumn('vouchers', 'stripe_coupon_synced_at')) {
                $table->timestamp('stripe_coupon_synced_at')->nullable()->after('stripe_coupon_id');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            Schema::hasColumn('vouchers', 'discount_type') ? 'discount_type' : null,
            Schema::hasColumn('vouchers', 'discount_amount') ? 'discount_amount' : null,
            Schema::hasColumn('vouchers', 'discount_percentage') ? 'discount_percentage' : null,
            Schema::hasColumn('vouchers', 'applies_to_orders') ? 'applies_to_orders' : null,
            Schema::hasColumn('vouchers', 'subscription_discount_duration') ? 'subscription_discount_duration' : null,
            Schema::hasColumn('vouchers', 'subscription_duration_months') ? 'subscription_duration_months' : null,
            Schema::hasColumn('vouchers', 'stripe_coupon_synced_at') ? 'stripe_coupon_synced_at' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('vouchers', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
}
