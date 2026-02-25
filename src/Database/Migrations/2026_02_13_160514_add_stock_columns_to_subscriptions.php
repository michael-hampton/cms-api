<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStockColumnsToSubscriptions extends Migration
{
    public function up(): void
    {
        Schema::table('issue_deliveries', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0);
            $table->boolean('preorder_enabled')->default(false);
            $table->dateTime('restock_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
