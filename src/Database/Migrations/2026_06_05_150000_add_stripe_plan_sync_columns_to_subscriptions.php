<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStripePlanSyncColumnsToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'stripe_subscription_id')) {
                $table->string('stripe_subscription_id')->nullable()->after('payment_subscription_id');
            }

            if (!Schema::hasColumn('subscriptions', 'stripe_subscription_item_id')) {
                $table->string('stripe_subscription_item_id')->nullable()->after('stripe_subscription_id');
            }

            if (!Schema::hasColumn('subscriptions', 'stripe_price_id')) {
                $table->string('stripe_price_id')->nullable()->after('stripe_subscription_item_id');
            }

            if (!Schema::hasColumn('subscriptions', 'stripe_sync_status')) {
                $table->string('stripe_sync_status', 32)->nullable()->after('stripe_price_id');
            }

            if (!Schema::hasColumn('subscriptions', 'stripe_sync_error')) {
                $table->text('stripe_sync_error')->nullable()->after('stripe_sync_status');
            }

            if (!Schema::hasColumn('subscriptions', 'stripe_synced_at')) {
                $table->dateTime('stripe_synced_at')->nullable()->after('stripe_sync_error');
            }
        });
    }

    public function down(): void
    {
        $columns = array_filter([
            Schema::hasColumn('subscriptions', 'stripe_synced_at') ? 'stripe_synced_at' : null,
            Schema::hasColumn('subscriptions', 'stripe_sync_error') ? 'stripe_sync_error' : null,
            Schema::hasColumn('subscriptions', 'stripe_sync_status') ? 'stripe_sync_status' : null,
            Schema::hasColumn('subscriptions', 'stripe_price_id') ? 'stripe_price_id' : null,
            Schema::hasColumn('subscriptions', 'stripe_subscription_item_id') ? 'stripe_subscription_item_id' : null,
            Schema::hasColumn('subscriptions', 'stripe_subscription_id') ? 'stripe_subscription_id' : null,
        ]);

        if (empty($columns)) {
            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
}
