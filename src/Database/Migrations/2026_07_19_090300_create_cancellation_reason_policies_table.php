<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * The save options for one (business_decision, cancellation_reason) pair.
 *
 * ASSUMPTION (nullable inheritance): every option column is nullable.
 * A null value means "not overridden at this decision level" — when
 * CancellationOptionsResolver resolves a reason's options for a
 * plan/site, it walks the same plan-decision -> site-decision ->
 * global-default-decision chain per *field*, not just per decision row,
 * so a brand/site can override e.g. refund_max_percent for "bereavement"
 * while leaving show_save_actions/marketing_consent inherited from the
 * global default. This is the concrete mechanism behind the ticket's
 * "with nullable inheritance" note. allow_cancel defaults true at final
 * resolution (see CancellationOptionsResolver) if left null everywhere.
 */
class CreateCancellationReasonPoliciesTable extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_reason_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_decision_id');
            $table->foreignId('cancellation_reason_id');

            $table->boolean('show_save_actions')->nullable();
            $table->boolean('allow_discount')->nullable();
            $table->boolean('allow_offer_switch')->nullable();
            $table->boolean('allow_cancel')->nullable();
            $table->unsignedTinyInteger('refund_max_percent')->nullable();
            $table->boolean('marketing_consent')->nullable();

            $table->timestamps();

            $table->foreign('business_decision_id')->references('id')->on('business_decisions')->cascadeOnDelete();
            $table->foreign('cancellation_reason_id')->references('id')->on('cancellation_reasons')->cascadeOnDelete();
            $table->unique(['business_decision_id', 'cancellation_reason_id'], 'uniq_crp_decision_reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cancellation_reason_policies');
    }
}
