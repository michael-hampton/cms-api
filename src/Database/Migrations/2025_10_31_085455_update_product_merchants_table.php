<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdateProductMerchantsTable extends Migration
{
    public function up(): void
    {
        Schema::table('product_merchants', function($table) {
            $table->unsignedBigInteger('merchant_id')->nullable()->after('id');
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
