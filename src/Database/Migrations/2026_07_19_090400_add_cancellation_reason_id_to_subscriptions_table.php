<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Adds the FK to the new DB-driven cancellation_reasons table. The
 * existing `cancellation_reason` string column (idx_sub_cancellation_reason)
 * is kept — populated with the resolved reason's `code` — so existing
 * reason-breakdown analytics queries keep working unchanged.
 */
class AddCancellationReasonIdToSubscriptionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('cancellation_reason_id')->nullable()->after('cancellation_reason');
            $table->foreign('cancellation_reason_id')->references('id')->on('cancellation_reasons')->nullOnDelete();
            $table->index(['cancellation_reason_id']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('cancellation_reason_id');
        });
    }
}
