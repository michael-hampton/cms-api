<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddExtraColumnsToProductOffers extends Migration
{
    public function up(): void
    {
        Schema::table('product_offers', function (Blueprint $table) {
            $table->enum('status', ['pending', 'published', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('published_by')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable();
            $table->foreignId('voucher_id')->nullable();

            $table->foreign('published_by')->references('id')->on('users');
            $table->foreign('rejected_by')->references('id')->on('users');
            $table->foreign('voucher_id')->references('id')->on('vouchers');

// Add index
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
