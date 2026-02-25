<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddSubscriptionIdToPayments extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function ($table) {
            $table->foreignId('subscription_id')->nullable()->after('order_id');
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('set null');
            $table->index('subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
