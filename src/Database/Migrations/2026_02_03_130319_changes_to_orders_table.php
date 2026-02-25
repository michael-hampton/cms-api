<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class ChangesToOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('order_number');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->boolean('is_guest')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
