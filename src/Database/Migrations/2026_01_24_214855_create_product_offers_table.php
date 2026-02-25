<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateProductOffersTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('merchant_id')->nullable();
            $table->decimal('sale_price', 10, 2);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');

            // Ensure only one active offer per product at a time
            $table->index(['product_id', 'is_active', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
