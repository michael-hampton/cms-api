<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddRenewalAndSwitchColumnsToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {

            // ── Ticket 1: Renewal ─────────────────────────────────────────
            // Links the new subscription back to the subscription it replaced.
            $table->unsignedBigInteger('renewed_from_subscription_id')
                ->nullable()
                ->after('id');

            $table->foreign('renewed_from_subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->onDelete('set null');

            // ── Ticket 2: Product switch ──────────────────────────────────
            // Links old → new when a product/publication switch is performed.
            $table->unsignedBigInteger('replaced_by_subscription_id')
                ->nullable()
                ->after('renewed_from_subscription_id');

            $table->foreign('replaced_by_subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->onDelete('set null');

            // Reason why this subscription was ended (renewal | product_change).
            $table->string('end_reason')->nullable()->after('end_date');

            // Monetary credit carried over from the old subscription (product switch only).
            $table->decimal('carried_over_credit', 10, 2)->nullable()->after('end_reason');

            // How the subscription was replaced.
            $table->string('replacement_reason')->nullable()->after('carried_over_credit');

            $table->index('renewed_from_subscription_id');
            $table->index('replaced_by_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
