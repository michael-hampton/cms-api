<?php

use App\Framework\Database\Database;
use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddEntitlementTypeToSubscriptionPlansAndPricing extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscription_plans', 'entitlement_type')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->string('entitlement_type', 16)
                    ->default('time')
                    ->after('delivery_type');
            });
        }

        if (!Schema::hasColumn('subscription_plan_pricing', 'entitlement_type')) {
            Schema::table('subscription_plan_pricing', function (Blueprint $table) {
                $table->string('entitlement_type', 16)
                    ->nullable()
                    ->after('plan_id');
            });
        }

        $db = Database::getInstance();

        $db->exec("
            UPDATE subscription_plans
            SET entitlement_type = 'time'
            WHERE entitlement_type IS NULL OR entitlement_type = ''
        ");

        $db->exec("
            ALTER TABLE subscription_plan_pricing
            MODIFY duration_months INT NULL,
            MODIFY issue_count INT NULL
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_plan_pricing', 'entitlement_type')) {
            Schema::table('subscription_plan_pricing', function (Blueprint $table) {
                $table->dropColumn('entitlement_type');
            });
        }

        if (Schema::hasColumn('subscription_plans', 'entitlement_type')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn('entitlement_type');
            });
        }
    }
}
