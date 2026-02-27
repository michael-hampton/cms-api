<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateReviewsTable extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function ($table) {
            $table->id();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->tinyInteger('rating')->unsigned(); // 1-5
            $table->string('title', 200)->nullable();
            $table->text('comment')->nullable();
            $table->boolean('is_verified_purchase')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->integer('helpful_count')->default(0);
            $table->integer('unhelpful_count')->default(0);
            $table->foreignId('site_id');
            $table->timestamps();

            $table->index(['product_id', 'is_approved']);
            $table->index(['user_id', 'product_id']);
            $table->index(['rating']);
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
