<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateProductOfferBundlesTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_offer_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('total_price', 10, 2);
            $table->decimal('bundle_price', 10, 2);
            $table->integer('discount_percentage')->default(0);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('pending'); // pending, published, rejected
            $table->text('rejection_reason')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('published_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('product_offer_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bundle_id');
            $table->unsignedBigInteger('product_offer_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->foreign('bundle_id')->references('id')->on('product_offer_bundles')->onDelete('cascade');
            $table->foreign('product_offer_id')->references('id')->on('product_offers')->onDelete('cascade');

            $table->unique(['bundle_id', 'product_offer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
