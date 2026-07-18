<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * History log of every communication creation attempt that
 * CommunicationConsentGate dropped, and why. Distinct from
 * ConsentAuditLog, which records consent grant/revoke actions — this
 * records the downstream effect of consent/suppression state on an
 * actual send attempt.
 */
class CreateSubscriptionCommunicationSuppressionLogs extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_communication_suppression_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('subscription_communication_id')->nullable();
            $table->string('channel')->nullable();
            $table->string('reason');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('subscription_id', 'scsl_subscription_fk')
                ->references('id')->on('subscriptions')
                ->cascadeOnDelete();
            $table->foreign('member_id', 'scsl_member_fk')
                ->references('id')->on('members')
                ->nullOnDelete();
            $table->foreign('subscription_communication_id', 'scsl_communication_fk')
                ->references('id')->on('subscription_communications')
                ->nullOnDelete();

            $table->index(['subscription_id', 'created_at'], 'scsl_subscription_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_communication_suppression_logs');
    }
}
