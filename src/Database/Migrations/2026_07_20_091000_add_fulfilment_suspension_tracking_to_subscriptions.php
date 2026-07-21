<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Tracks a payment-failure/suspension trigger whose fulfilment suspension
 * has been deferred by the plan's FulfilmentSuspensionRule (delay_type
 * days/issues). ProcessPendingFulfilmentSuspensionsJob re-checks these
 * subscriptions and applies the suspension once the rule is satisfied —
 * see FulfilmentSuspensionService.
 */
class AddFulfilmentSuspensionTrackingToSubscriptions extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscriptions', 'fulfilment_suspension_pending')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->boolean('fulfilment_suspension_pending')->default(false);
            });
        }

        if (!Schema::hasColumn('subscriptions', 'fulfilment_suspension_reason')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                // 'payment_failed' | 'subscription_suspended'
                $table->string('fulfilment_suspension_reason')->nullable();
            });
        }

        if (!Schema::hasColumn('subscriptions', 'fulfilment_suspension_triggered_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dateTime('fulfilment_suspension_triggered_at')->nullable();
            });
        }

        if (!Schema::hasIndex('subscriptions', 'idx_fulfilment_suspension_pending')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->index(['fulfilment_suspension_pending']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['fulfilment_suspension_pending']);
            $table->dropColumn('fulfilment_suspension_triggered_at');
            $table->dropColumn('fulfilment_suspension_reason');
            $table->dropColumn('fulfilment_suspension_pending');
        });
    }
}
