<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePreorderSystem extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('preorder_enabled')->default(false);
            $table->datetime('preorder_restock_date')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('preorder_enabled')->default(false);
            $table->datetime('expected_ship_date')->nullable();
            $table->integer('quantity_allocated')->default(0);
            $table->enum('status', ['pending_preorder', 'ready_to_ship', 'shipped', 'cancelled', 'pending'])->default('pending');
        });

        Schema::create('product_stock_alerts', function ($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('user_id')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('user_id')->references('id')->on('members');

            // Unique constraints
            $table->unique(['product_id', 'user_id']);
            $table->unique(['product_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
