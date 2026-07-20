<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * The suspension-governance equivalent of cancellation_reason_policies,
 * but simpler: SuspendSubscriptionAction is an admin/system enforcement
 * action with a mandatory free-text reason, not a customer-facing
 * catalogue of reasons — so there is one row per BusinessDecision
 * (SUSPENSIONS category), not one per (decision, reason) pair.
 *
 * Both fields are nullable ("not overridden at this level, inherit") —
 * see SuspensionOptionsResolver, which walks the same
 * product -> brand -> default chain as cancellations. Left entirely
 * unconfigured, allow_suspend defaults to true and requires_note to
 * true — i.e. today's existing SuspendSubscriptionAction behaviour is
 * preserved unless a decision explicitly changes it.
 */
class CreateSuspensionPoliciesTable extends Migration
{
    public function up(): void
    {
        Schema::create('suspension_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_decision_id');

            $table->boolean('allow_suspend')->nullable();
            $table->boolean('requires_note')->nullable();

            $table->timestamps();

            $table->foreign('business_decision_id')->references('id')->on('business_decisions')->cascadeOnDelete();
            $table->unique(['business_decision_id'], 'uniq_suspension_policy_decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suspension_policies');
    }
}
