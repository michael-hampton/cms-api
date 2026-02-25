<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddVariantSupportToCartItems extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_id')->nullable()->after('product_id');

            // Add index for faster lookups
            $table->index(['product_id', 'variant_id', 'session_id']);
            $table->index(['product_id', 'variant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
