<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateRefundReasonPoliciesTable extends Migration
{
    public function up(): void
    {
        Schema::create('refund_reason_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_decision_id');
            $table->foreignId('refund_reason_id');
            $table->boolean('allow_full')->nullable();
            $table->boolean('allow_pro_rated')->nullable();
            $table->boolean('allow_manual')->nullable();
            $table->boolean('allow_cancel_at_period_end')->nullable();
            $table->boolean('allow_cancel_immediately_no_refund')->nullable();
            $table->unsignedTinyInteger('refund_max_percent')->nullable();
            $table->unsignedTinyInteger('manager_approval_threshold_percent')->nullable();
            $table->boolean('default_notify_customer')->nullable();
            $table->boolean('requires_internal_notes')->nullable();
            $table->timestamps();

            $table->foreign('business_decision_id')->references('id')->on('business_decisions')->cascadeOnDelete();
            $table->foreign('refund_reason_id')->references('id')->on('refund_reasons')->cascadeOnDelete();
            $table->unique(['business_decision_id', 'refund_reason_id'], 'uniq_rrp_decision_reason');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_reason_policies');
    }
}
