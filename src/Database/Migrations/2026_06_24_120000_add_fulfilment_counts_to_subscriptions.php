<?php

use App\Framework\Database\Database;
use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFulfilmentCountsToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('subscriptions', 'fulfilments_count')) {
                $table->unsignedInteger('fulfilments_count')->default(0)->after('billing_day_of_month');
            }

            if (!Schema::hasColumn('subscriptions', 'scheduled_fulfilments_count')) {
                $table->unsignedInteger('scheduled_fulfilments_count')->default(0)->after('fulfilments_count');
            }

            if (!Schema::hasColumn('subscriptions', 'dispatched_fulfilments_count')) {
                $table->unsignedInteger('dispatched_fulfilments_count')->default(0)->after('scheduled_fulfilments_count');
            }

            if (!Schema::hasColumn('subscriptions', 'delivered_fulfilments_count')) {
                $table->unsignedInteger('delivered_fulfilments_count')->default(0)->after('dispatched_fulfilments_count');
            }

            if (!Schema::hasColumn('subscriptions', 'failed_fulfilments_count')) {
                $table->unsignedInteger('failed_fulfilments_count')->default(0)->after('delivered_fulfilments_count');
            }

            if (!Schema::hasColumn('subscriptions', 'superseded_fulfilments_count')) {
                $table->unsignedInteger('superseded_fulfilments_count')->default(0)->after('failed_fulfilments_count');
            }
        });

        $this->backfillCounts();
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'fulfilments_count',
                'scheduled_fulfilments_count',
                'dispatched_fulfilments_count',
                'delivered_fulfilments_count',
                'failed_fulfilments_count',
                'superseded_fulfilments_count',
            ]);
        });
    }

    private function backfillCounts(): void
    {
        if (!Schema::hasTable('subscription_issue_fulfilments')) {
            return;
        }

        Database::getInstance()->exec(<<<SQL
UPDATE subscriptions
SET
    fulfilments_count = (
        SELECT COUNT(*)
        FROM subscription_issue_fulfilments fulfilments
        WHERE fulfilments.subscription_id = subscriptions.id
    ),
    scheduled_fulfilments_count = (
        SELECT COUNT(*)
        FROM subscription_issue_fulfilments fulfilments
        WHERE fulfilments.subscription_id = subscriptions.id
          AND fulfilments.status = 'scheduled'
          AND fulfilments.dispatched_at IS NULL
    ),
    dispatched_fulfilments_count = (
        SELECT COUNT(*)
        FROM subscription_issue_fulfilments fulfilments
        WHERE fulfilments.subscription_id = subscriptions.id
          AND fulfilments.dispatched_at IS NOT NULL
    ),
    delivered_fulfilments_count = (
        SELECT COUNT(*)
        FROM subscription_issue_fulfilments fulfilments
        WHERE fulfilments.subscription_id = subscriptions.id
          AND fulfilments.status = 'delivered'
    ),
    failed_fulfilments_count = (
        SELECT COUNT(*)
        FROM subscription_issue_fulfilments fulfilments
        WHERE fulfilments.subscription_id = subscriptions.id
          AND fulfilments.status = 'failed'
    ),
    superseded_fulfilments_count = (
        SELECT COUNT(*)
        FROM subscription_issue_fulfilments fulfilments
        WHERE fulfilments.subscription_id = subscriptions.id
          AND fulfilments.status = 'superseded'
    )
SQL);
    }
}
