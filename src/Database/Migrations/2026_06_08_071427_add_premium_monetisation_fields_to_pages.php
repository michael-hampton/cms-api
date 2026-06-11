<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPremiumMonetisationFieldsToPages extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->datetime('premium_requested_at')->nullable();
            $table->integer('premium_requested_by')->nullable();
            $table->integer('premium_suggested_price')->nullable(); // pence
            $table->text('premium_request_note')->nullable();

            $table->datetime('premium_approved_at')->nullable();
            $table->integer('premium_approved_by')->nullable();
            $table->text('premium_approval_note')->nullable();

            $table->datetime('premium_rejected_at')->nullable();
            $table->integer('premium_rejected_by')->nullable();
            $table->text('premium_rejection_reason')->nullable();

            $table->datetime('monetisation_disabled_at')->nullable();
            $table->integer('monetisation_disabled_by')->nullable();
            $table->string('monetisation_disabled_reason')->nullable();

            $table->datetime('first_editorial_change_reported_at')->nullable();
            $table->integer('first_editorial_change_reported_by')->nullable();
            $table->integer('first_editorial_change_history_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
