<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStripeRefundFieldsToRefundsTable extends Migration
{
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('stripe_refund_id')->nullable()->after('status');
            $table->string('stripe_refund_status')->nullable()->after('stripe_refund_id');
            $table->text('stripe_failure_reason')->nullable()->after('stripe_refund_status');
            $table->timestamp('stripe_refunded_at')->nullable()->after('stripe_failure_reason');

            $table->index('stripe_refund_id');
            $table->index('stripe_refund_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
