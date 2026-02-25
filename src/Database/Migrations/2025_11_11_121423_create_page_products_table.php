final <?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePageProductsTable extends Migration
{
    #[\Override]
    public function up(): void
    {
        Schema::create('page_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('sort_order')->default(0);
            $table->unsignedBigInteger('site_id');
            $table->timestamp('created_at')->useCurrent();

            // Foreign keys
            $table->foreign('page_id')
                    ->references('id')
                    ->on('pages')
                    ->onDelete('cascade');

            $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->onDelete('cascade');

            // Unique constraint
            $table->unique(['page_id', 'product_id'], 'unique_page_product');
        });
    }

    #[\Override]
    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
