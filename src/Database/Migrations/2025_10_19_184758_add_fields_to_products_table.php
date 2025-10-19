<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFieldsToProductsTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('sku')->unique();
            $table->json('attributes'); // {color: 'Red', size: 'Large'}
            $table->decimal('price_modifier', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->index(['product_id', 'is_active']);
        });

        Schema::create('product_images', function($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('variant_id')->nullable();
            $table->string('url');
            $table->string('alt')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('variant_id')->references('id')->on('product_variants')->cascadeOnDelete();

            $table->index(['product_id', 'is_primary']);
        });

        Schema::create('product_merchants', function($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('variant_id')->nullable();
            $table->string('name');
            $table->string('url');
            $table->decimal('price', 10, 2);
            $table->timestamp('last_price_check')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->index(['product_id', 'is_available']);
        });

        Schema::create('product_specifications', function($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('category'); // e.g., 'Technical', 'Physical'
            $table->string('key');
            $table->text('value');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->index(['product_id', 'category']);
        });

        Schema::create('product_inclusions', function($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('page_id');
            $table->foreignId('site_id');
            $table->timestamp('included_at');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->unique(['product_id', 'page_id']);
            $table->index(['page_id', 'site_id']);
        });

        Schema::create('product_price_history', function($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('merchant_id')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('product_merchants')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();

            $table->index(['product_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
