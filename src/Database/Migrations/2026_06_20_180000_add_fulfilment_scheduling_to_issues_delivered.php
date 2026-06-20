<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;
use App\Models\IssueDelivery;
use App\Models\IssuesDelivered;

class AddFulfilmentSchedulingToIssuesDelivered extends Migration
{
    public function up(): void
    {
        Schema::table('issues_delivered', function (Blueprint $table) {
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('deferred_until')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->string('skip_reason')->nullable();
            $table->index(['status', 'scheduled_for']);
            $table->index(['status', 'deferred_until']);
        });

        $seen = [];
        $fulfilments = IssuesDelivered::orderBy('id', 'asc')->get();

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

        Schema::table('issues_delivered', function (Blueprint $table) {
            $table->unique(['subscription_id', 'issue_delivery_id']);
        });
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
