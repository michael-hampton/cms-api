<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCancellationColumnsToOrders extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }

            $table->string('cancellation_reason', 50)->nullable()->after('cancelled_at');

            if (!Schema::hasColumn('orders', 'dispatched_at')) {
                $table->timestamp('dispatched_at')->nullable()->after('cancellation_reason');
            }

            // Index lets OrderCancellationService::canCancel() do a fast check
            $table->index(['user_id', 'status'], 'idx_orders_user_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
