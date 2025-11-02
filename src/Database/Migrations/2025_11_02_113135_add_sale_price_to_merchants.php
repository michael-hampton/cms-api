<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSalePriceToMerchants extends Migration
{
    public function up(): void
    {
        Schema::table('product_merchants', function (Blueprint $table) {
            $table->decimal('sale_price', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
