<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStockQuantityToProducts extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0)->after('description');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
