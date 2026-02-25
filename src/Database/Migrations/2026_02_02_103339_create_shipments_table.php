<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateShipmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id')->unique();
            $table->string('checkout_id', 100);
            $table->unsignedBigInteger('merchant_id')->nullable();

            $table->decimal('shipping_cost', 10, 2)->default(0.00);
            $table->string('country', 2)->default('US');
            $table->string('status', 50)->default('pending');

            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('site_id');

            $table->timestamps();

            // Indexes
            $table->index('checkout_id', 'idx_checkout_id');
            $table->index('merchant_id', 'idx_merchant_id');

            // Foreign keys
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->onDelete('cascade');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('merchant_id')
                ->nullable()
                ->after('site_id');

            $table->json('metadata')
                ->nullable()
                ->after('admin_notes');

            $table->index('merchant_id', 'idx_merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
