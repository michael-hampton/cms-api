final <?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateFeaturedDealsTable extends Migration
{
    #[\Override]
    public function up(): void
    {
        Schema::create('featured_deals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->unsignedBigInteger('site_id');
            $table->date('featured_date');
            $table->integer('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Foreign keys
            $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->onDelete('cascade');

            $table->foreign('variant_id')
                    ->references('id')
                    ->on('product_variants')
                    ->onDelete('cascade');

            $table->foreign('merchant_id')
                    ->references('id')
                    ->on('merchants')
                    ->onDelete('cascade');

            // Indexes
            $table->index(['featured_date', 'site_id', 'is_active']);
            $table->index(['site_id', 'featured_date']);
        });
    }

    #[\Override]
    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
