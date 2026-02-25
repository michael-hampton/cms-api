<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSubscriptionUpgradeFields extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('includes_digital_access')
                ->default(false)
                ->after('delivery_type');

            $table->integer('upgraded_from_plan_id')
                ->nullable()
                ->after('plan_id');

            $table->dateTime('upgraded_at')
                ->nullable()
                ->after('upgraded_from_plan_id');

            $table->decimal('upgrade_price_difference', 10, 2)
                ->nullable()
                ->after('upgraded_at');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('includes_insider')
                ->default(false);

            $table->boolean('is_upgrade_option')
                ->default(false);

            $table->integer('upgrade_from_plan_id')
                ->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
