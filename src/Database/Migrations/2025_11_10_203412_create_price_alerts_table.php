final <?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePriceAlertsTable extends Migration
{
    #[\Override]
    public function up(): void
    {
        Schema::create('price_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email', 255);
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('merchant_id')->nullable();
            $table->decimal('target_price', 10, 2);
            $table->decimal('current_price', 10, 2);
            $table->boolean('is_triggered')->default(false);
            $table->boolean('is_notified')->default(false);
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('notified_at')->nullable();
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
            $table->index(['is_triggered', 'is_notified', 'product_id']);
            $table->index(['email', 'product_id']);
            $table->index('created_at');
        });
    }

    #[\Override]
    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
