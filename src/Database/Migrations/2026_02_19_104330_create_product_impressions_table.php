<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateProductImpressionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_impressions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('context');       // listing | deals | recommendations
            $table->timestamp('viewed_at');

            $table->index(['product_id', 'viewed_at']);
            $table->index(['product_id', 'context']);

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
