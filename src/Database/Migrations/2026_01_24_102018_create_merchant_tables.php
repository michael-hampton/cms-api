<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMerchantTables extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_product_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->string('feed_url');
            $table->string('feed_type'); // e.g., 'xml', 'csv', 'json'
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('next_fetch_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('fetch_frequency')->default('daily');
            $table->string('status')->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
        });

        Schema::create('merchant_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->string('url');
            $table->boolean('is_primary')->default(false);
            $table->string('label')->nullable(); // e.g., 'Official Store', 'Support'
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
