<?php
        use App\Framework\Authorization\Exceptions\Framework\Database\Schema;
use App\Framework\Authorization\Exceptions\Framework\Migration\Blueprint;
use App\Framework\Authorization\Exceptions\Framework\Migration\Migration;

class AddFieldsToProductVariants extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('name')->nullable()->after('sku');
            $table->decimal('price', 10, 2)->default(0)->after('attributes');
            $table->decimal('sale_price', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
