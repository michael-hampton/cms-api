<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdateSubscriptionStatuses extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->enum('status', ['active', 'cancelled', 'expired', 'paused', 'past_due', 'pending', 'grace_period', 'retrying']); //'active','cancelled','expired','paused','past_due','pending'
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
