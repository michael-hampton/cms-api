<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Adds the three enforcement/lifecycle states pending fulfilments can move
 * through outside the normal dispatch flow:
 *
 *   suspended — payment failure or an admin/system subscription suspension.
 *   paused    — subscription-level delivery pause (SubscriptionPauseService).
 *   cancelled — the owning subscription was cancelled.
 *
 * See docs/issue-delivery-fulfilment.md for the existing state machine this
 * extends.
 */
class AddSuspensionStatesToSubscriptionIssueFulfilments extends Migration
{
    public function up(): void
    {
        Schema::modifyEnum(
            'subscription_issue_fulfilments',
            'status',
            ['scheduled', 'delivered', 'failed', 'pending', 'superseded', 'fulfilled', 'suspended', 'paused', 'cancelled'],
            'scheduled'
        );

        if (!Schema::hasColumn('subscription_issue_fulfilments', 'suspension_reason')) {
            Schema::table('subscription_issue_fulfilments', function (Blueprint $table) {
                // 'payment_failed' | 'subscription_suspended' — audit trail for
                // why a row was moved to SUSPENDED. Cleared on release.
                $table->string('suspension_reason')->nullable();
            });
        }

        if (!Schema::hasIndex('subscription_issue_fulfilments', 'idx_subscription_id_status')) {
            Schema::table('subscription_issue_fulfilments', function (Blueprint $table) {
                $table->index(['subscription_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('subscription_issue_fulfilments', function (Blueprint $table) {
            $table->dropIndex(['subscription_id', 'status']);
            $table->dropColumn('suspension_reason');
        });

        Schema::modifyEnum(
            'subscription_issue_fulfilments',
            'status',
            ['scheduled', 'delivered', 'failed', 'pending', 'superseded', 'fulfilled'],
            'scheduled'
        );
    }
}
