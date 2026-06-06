<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateFulfilmentReplacementsTable extends Migration
{
    public function up(): void
    {
        Schema::create('fulfilment_replacements', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('subscription_id');
            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->onDelete('cascade');

            // The issue_delivery record that needs replacing.
            $table->unsignedBigInteger('issue_delivery_id');
            $table->foreign('issue_delivery_id')
                ->references('id')
                ->on('issue_deliveries')
                ->onDelete('cascade');

            // The agent who raised the replacement request.
            $table->unsignedBigInteger('created_by')->nullable();

            // Reason provided by the agent (required by the UI).
            $table->string('reason');

            // Workflow status: pending → queued → sent → completed / failed.
            $table->string('status')->default('pending');

            $table->timestamps();

            // Indexes for the most common lookup patterns.
            $table->index('subscription_id');
            $table->index('issue_delivery_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
