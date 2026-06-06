<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStripeColumnsToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Only add columns that don't already exist.
            // payment_subscription_id already exists but holds a generic value;
            // we add a dedicated stripe_subscription_id for the unique-index constraint.
            $table->string('stripe_customer_id')->nullable()->after('payment_subscription_id');
            $table->boolean('cancel_at_period_end')->default(false)->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
