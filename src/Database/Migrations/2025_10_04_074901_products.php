<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class Products extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->foreignId('category_id')->nullable();
            $table->string('brand')->nullable();
            $table->string('image')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->text('meta_keywords')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('brand');
            $table->index('created_at');
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();;
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
