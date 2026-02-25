<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddDispatchDaysToProductsAndSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('products', function ($table) {
            $table->integer('dispatch_days')->default(2);
        });

        Schema::table('subscription_plans', function ($table) {
            $table->integer('dispatch_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
