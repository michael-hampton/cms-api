<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPlanIdToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable();
            $table->foreign('plan_id')->references('id')->on('subscription_plans');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
