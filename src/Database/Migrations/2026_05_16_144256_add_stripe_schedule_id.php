<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStripeScheduleId extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_schedule_id')->nullable()->after('payment_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
