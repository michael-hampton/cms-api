<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateWishlistsTable extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function ($table) {
            $table->id();
            $table->string('session_id', 100)->nullable();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('site_id');
            $table->timestamps();

            $table->unique(['user_id', 'product_id', 'site_id']);
            $table->unique(['session_id', 'product_id', 'site_id']);
            $table->index(['session_id', 'site_id']);
            $table->index(['user_id', 'site_id']);

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
