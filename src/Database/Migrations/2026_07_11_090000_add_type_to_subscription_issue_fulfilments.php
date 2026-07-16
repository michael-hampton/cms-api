<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Back-issue support: distinguishes fulfilments created by the normal
 * subscription pipeline (STANDARD) from single-issue back-issue purchases
 * (BACK_ISSUE) so the latter can be extracted and dispatched separately by
 * BackIssueReplacementCopyDispatchService instead of relying on the Label
 * Run workflow, which only ever processes a batch once.
 */
class AddTypeToSubscriptionIssueFulfilments extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('subscription_issue_fulfilments', 'type')) {
            Schema::table('subscription_issue_fulfilments', function (Blueprint $table) {
                $table->string('type')->default('standard');
            });
        }

        if (!Schema::hasColumn('subscription_issue_fulfilments', 'fulfilled_at')) {
            Schema::table('subscription_issue_fulfilments', function (Blueprint $table) {
                $table->dateTime('fulfilled_at')->nullable();
            });
        }

        // Widen the status enum to include the terminal state written back
        // by the back-issue dispatch process.
        Schema::modifyEnum(
            'subscription_issue_fulfilments',
            'status',
            ['scheduled', 'delivered', 'failed', 'pending', 'superseded', 'fulfilled'],
            'scheduled'
        );

        if (!Schema::hasIndex('subscription_issue_fulfilments', 'idx_type_fulfilled_at')) {
            Schema::table('subscription_issue_fulfilments', function (Blueprint $table) {
                // Supports BackIssueReplacementCopyDispatchService's extraction
                // query: WHERE type = 'back_issue' AND fulfilled_at IS NULL.
                $table->index(['type', 'fulfilled_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscription_issue_fulfilments', function (Blueprint $table) {
            $table->dropIndex(['type', 'fulfilled_at']);
        });

        Schema::modifyEnum(
            'subscription_issue_fulfilments',
            'status',
            ['scheduled', 'delivered', 'failed', 'pending', 'superseded'],
            'scheduled'
        );

        Schema::table('subscription_issue_fulfilments', function (Blueprint $table) {
            $table->dropColumn('fulfilled_at');
            $table->dropColumn('type');
        });
    }
}
