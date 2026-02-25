<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPauseAndCancellationColumnsToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Pause columns
            $table->timestamp('paused_at')->nullable()->after('auto_renew');
            $table->date('pause_until')->nullable()->after('paused_at');
            $table->timestamp('resumed_at')->nullable()->after('pause_until');

            // Cancellation detail columns
            // cancelled_at is likely already present — only add if missing
            if (!Schema::hasColumn('subscriptions', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('resumed_at');
            }

            $table->string('cancellation_reason', 50)->nullable()->after('cancelled_at');
            $table->text('cancellation_notes')->nullable()->after('cancellation_reason');

            // Index for retention analytics queries (reason breakdown)
            $table->index(['cancellation_reason'], 'idx_sub_cancellation_reason');

            // Index for the auto-resume scheduled job: SELECT * WHERE status=paused AND pause_until <= NOW()
            $table->index(['status', 'pause_until'], 'idx_sub_status_pause_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
