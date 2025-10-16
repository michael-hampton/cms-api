<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCartItemsTable extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function ($table) {
            $table->id();
            $table->string('session_id', 100)->nullable();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('product_id');
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2);
            $table->text('options')->nullable();
            $table->foreignId('site_id');
            $table->timestamps();

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
