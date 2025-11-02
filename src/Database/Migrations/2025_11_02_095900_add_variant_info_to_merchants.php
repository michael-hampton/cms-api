<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddVariantInfoToMerchants extends Migration
{
    public function up(): void
    {
        Schema::table('product_merchants', function (Blueprint $table) {
            $table->string('variant_sku', 100)->nullable()->after('variant_id');
            $table->boolean('override_price')->default(false)->after('price');
            $table->boolean('override_sale_price')->default(false)->after('override_price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
