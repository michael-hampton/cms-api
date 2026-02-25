<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSubscriptionPlanIdToCartTable extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('subscription_plan_id')->nullable();
            $table->foreign('subscription_plan_id')->references('id')->on('subscription_plans');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
