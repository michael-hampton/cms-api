<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddMerchantIdToUsers extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
           $table->foreignId('merchant_id')->nullable();
           $table->foreign('merchant_id')->on('merchants')->references('id')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
