<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddMerchantToPriceHistory extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_history', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable();
            $table->foreign('merchant_id')->references('id')->on('merchants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
