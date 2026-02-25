<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddOneTimeSubscriptionIdToOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('one_time_subscription_id')->nullable();
            $table->foreign('one_time_subscription_id')->references('id')->on('subscriptions');
            $table->index(['one_time_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
