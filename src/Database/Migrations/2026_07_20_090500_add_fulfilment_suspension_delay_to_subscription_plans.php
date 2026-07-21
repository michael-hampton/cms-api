<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Product-level override for how long we wait, after a payment-failure or
 * subscription-suspension trigger, before suspending a subscriber's pending
 * fulfilments.
 *
 * fulfilment_suspension_delay_type: 'immediate' | 'days' | 'issues'.
 * fulfilment_suspension_delay_value: the N for 'days'/'issues'; ignored for
 * 'immediate'.
 *
 * A plan with no explicit override (default 'immediate', null value) gets
 * the system default of suspending pending fulfilments straight away — see
 * FulfilmentSuspensionPolicyResolver::DEFAULT_RULE.
 */
class AddFulfilmentSuspensionDelayToSubscriptionPlans extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscription_plans', 'fulfilment_suspension_delay_type')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->string('fulfilment_suspension_delay_type', 16)->default('immediate');
            });
        }

        if (!Schema::hasColumn('subscription_plans', 'fulfilment_suspension_delay_value')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->unsignedInteger('fulfilment_suspension_delay_value')->nullable();
            });
        }
    }

    public function down(): void
    {
        $columns = array_filter([
            Schema::hasColumn('subscription_plans', 'fulfilment_suspension_delay_value') ? 'fulfilment_suspension_delay_value' : null,
            Schema::hasColumn('subscription_plans', 'fulfilment_suspension_delay_type') ? 'fulfilment_suspension_delay_type' : null,
        ]);

        if (empty($columns)) {
            return;
        }

        Schema::table('subscription_plans', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
}
