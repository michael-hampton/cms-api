<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Polymorphic per-category assignment of a BusinessDecision to a Site or
 * a SubscriptionPlan (assignable_type/assignable_id — same morph
 * convention as Review::reviewable). Replaces the single-FK assumption
 * described in the ticket.
 *
 * ASSUMPTION: this codebase has no separate "brand" entity for
 * subscriptions (SubscriptionPlan only carries site_id) — confirmed with
 * the requester. So the ticket's "site -> brand -> product" chain is
 * realised here as two assignable levels (Site, SubscriptionPlan) sitting
 * beneath the single global is_default decision on business_decisions.
 *
 * `category` is denormalised from the assigned business_decision at write
 * time (kept in sync by BusinessDecisionAssignmentRepository) so a plain
 * unique index can enforce "one assignment per assignable entity per
 * category" without a join.
 */
class CreateBusinessDecisionAssignmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('business_decision_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_decision_id');
            $table->string('assignable_type', 150);
            $table->unsignedBigInteger('assignable_id');
            $table->string('category', 50);
            $table->timestamps();

            $table->foreign('business_decision_id')->references('id')->on('business_decisions')->cascadeOnDelete();
            $table->unique(['assignable_type', 'assignable_id', 'category'], 'uniq_bda_assignable_category');
            $table->index(['business_decision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_decision_assignments');
    }
}
