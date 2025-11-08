<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddAddressIdsToOrdersTable extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_address_id')
                ->nullable();

            $table->foreignId('billing_address_id')
                ->nullable();

            $table->foreign('shipping_address_id')
                ->references('id')
                ->on('addresses')
                ->onDelete('cascade');

            $table->foreign('billing_address_id')
                ->references('id')
                ->on('addresses')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
