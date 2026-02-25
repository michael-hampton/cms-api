<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddCommissionColumnsToOrderItems extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function ($table) {
            $table->decimal('commission_rate', 5, 4)->default(0)->after('total');
            $table->decimal('commission_amount', 10, 2)->default(0)->after('commission_rate');
            $table->decimal('net_amount', 10, 2)->default(0)->after('commission_amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
