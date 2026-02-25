<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddOriginalPriceToOffers extends Migration
{
    public function up(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->decimal('original_price', 10, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
