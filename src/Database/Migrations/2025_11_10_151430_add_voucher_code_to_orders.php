<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddVoucherCodeToOrders extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('voucher_code', 50)->nullable();
            $table->index('voucher_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
