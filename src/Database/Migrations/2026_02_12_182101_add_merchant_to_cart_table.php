<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddMerchantToCartTable extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('merchant_id')->nullable();
            $table->foreign('merchant_id')->references('id')->on('merchants');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
