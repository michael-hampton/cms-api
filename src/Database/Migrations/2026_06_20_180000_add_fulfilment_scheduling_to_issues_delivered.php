<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;
use App\Models\IssueDelivery;

class AddFulfilmentSchedulingToIssuesDelivered extends Migration
{
    public function up(): void
    {
        $columns = [
            'scheduled_for' => static fn (Blueprint $table) => $table->dateTime('scheduled_for')->nullable(),
            'deferred_until' => static fn (Blueprint $table) => $table->dateTime('deferred_until')->nullable(),
            'dispatched_at' => static fn (Blueprint $table) => $table->dateTime('dispatched_at')->nullable(),
            'failed_at' => static fn (Blueprint $table) => $table->dateTime('failed_at')->nullable(),
            'skip_reason' => static fn (Blueprint $table) => $table->string('skip_reason')->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (!Schema::hasColumn('issues_delivered', $column)) {
                Schema::table('issues_delivered', $definition);
            }
        }

        if (!Schema::hasIndex('issues_delivered', 'idx_status_scheduled_for')) {
            Schema::table('issues_delivered', function (Blueprint $table) {
                $table->index(['status', 'scheduled_for']);
            });
        }

        if (!Schema::hasIndex('issues_delivered', 'idx_status_deferred_until')) {
            Schema::table('issues_delivered', function (Blueprint $table) {
                $table->index(['status', 'deferred_until']);
            });
        }

        $seen = [];
        $fulfilments = LegacyIssuesDelivered::orderBy('id', 'asc')->get();

        foreach ($fulfilments as $fulfilment) {
            $key = $fulfilment->subscription_id . ':' . $fulfilment->issue_delivery_id;

            if (isset($seen[$key])) {
                $fulfilment->delete();
                continue;
            }

            $seen[$key] = true;

            if ($fulfilment->scheduled_for) {
                continue;
            }

            $issue = IssueDelivery::find($fulfilment->issue_delivery_id);
            $scheduledFor = $issue?->estimated_delivery_date ?? $issue?->on_sale_date;

            if ($scheduledFor) {
                $fulfilment->update([
                    'scheduled_for' => $scheduledFor->format('Y-m-d H:i:s'),
                ]);
            }
        }

        if (!Schema::hasIndex('issues_delivered', 'unique_subscription_id_issue_delivery_id')) {
            Schema::table('issues_delivered', function (Blueprint $table) {
                $table->unique(['subscription_id', 'issue_delivery_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('issues_delivered', function (Blueprint $table) {
            $table->dropUnique(['subscription_id', 'issue_delivery_id']);
            $table->dropIndex(['status', 'scheduled_for']);
            $table->dropIndex(['status', 'deferred_until']);
            $table->dropColumn('scheduled_for');
            $table->dropColumn('deferred_until');
            $table->dropColumn('dispatched_at');
            $table->dropColumn('failed_at');
            $table->dropColumn('skip_reason');
        });
    }
}

class LegacyIssuesDelivered extends \App\Models\Model
{
    protected $table = 'issues_delivered';
}
