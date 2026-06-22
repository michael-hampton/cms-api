<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class RenameIssuesDeliveredToSubscriptionIssueFulfilments extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('issues_delivered') && !Schema::hasTable('subscription_issue_fulfilments')) {
            Schema::rename('issues_delivered', 'subscription_issue_fulfilments');
        }

        $this->renameReferencingColumns(
            'issues_delivered_id',
            'subscription_issue_fulfilment_id'
        );
    }

    public function down(): void
    {
        $this->renameReferencingColumns(
            'subscription_issue_fulfilment_id',
            'issues_delivered_id'
        );

        if (Schema::hasTable('subscription_issue_fulfilments') && !Schema::hasTable('issues_delivered')) {
            Schema::rename('subscription_issue_fulfilments', 'issues_delivered');
        }
    }

    private function renameReferencingColumns(string $from, string $to): void
    {
        foreach (['print_fulfillments', 'label_runs'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $from)) {
                Schema::renameColumn($table, $from, $to);
            }
        }
    }
}
