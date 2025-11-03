<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateVoucherLinks extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_categories', function ($table) {
            $table->id();
            $table->foreignId('voucher_id');
            $table->foreignId('category_id');
            $table->timestamps();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();

            $table->unique(['voucher_id', 'category_id']);
        });

        // Create voucher_brands pivot table
        Schema::create('voucher_brands', function ($table) {
            $table->id();
            $table->foreignId('voucher_id');
            $table->foreignId('brand_id');
            $table->timestamps();

            $table->foreign('voucher_id')->references('id')->on('vouchers')->cascadeOnDelete();
            $table->foreign('brand_id')->references('id')->on('brands')->cascadeOnDelete();

            $table->unique(['voucher_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
