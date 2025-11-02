<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class DropMerchantName extends Migration
{
    public function up(): void
    {
        Schema::table('product_merchants', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
