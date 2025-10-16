<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateProductViewsTable extends Migration
{
    public function up(): void
    {
        Schema::create('product_views', function ($table) {
            $table->id();
            $table->foreignId('product_id');
            $table->foreignId('user_id')->nullable();
            $table->string('session_id', 100);
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('site_id');
            $table->timestamp('created_at');

            $table->index(['product_id', 'site_id']);
            $table->index(['session_id', 'site_id']);
            $table->index(['created_at']);

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
