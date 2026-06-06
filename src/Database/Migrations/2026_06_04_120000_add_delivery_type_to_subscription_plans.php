<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDeliveryTypeToSubscriptionPlans extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscription_plans', 'delivery_type')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->string('delivery_type', 32)
                    ->nullable()
                    ->after('plan_type');
            });
        }

        \App\Framework\Database\Database::getInstance()->exec("
            UPDATE subscription_plans
            SET delivery_type = CASE
                WHEN COALESCE(NULLIF(TRIM(digital_download_url), ''), '') != ''
                    AND COALESCE(print_shipping_required, 0) = 1
                    THEN 'print_and_digital'
                WHEN COALESCE(print_shipping_required, 0) = 1
                    THEN 'print'
                WHEN COALESCE(NULLIF(TRIM(digital_download_url), ''), '') != ''
                    AND COALESCE(print_shipping_required, 0) = 0
                    THEN 'digital'
                ELSE NULL
            END
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_plans', 'delivery_type')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn('delivery_type');
            });
        }
    }
}
