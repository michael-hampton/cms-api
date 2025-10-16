<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateReviewHelpfulTable extends Migration
{
    public function up(): void
    {
        Schema::create('review_helpful', function ($table) {
            $table->id();
            $table->foreignId('review_id');
            $table->foreignId('user_id')->nullable();
            $table->string('session_id', 100)->nullable();
            $table->boolean('is_helpful');
            $table->foreignId('site_id');
            $table->timestamp('created_at');

            $table->unique(['review_id', 'user_id']);
            $table->unique(['review_id', 'session_id']);
            $table->index(['review_id', 'site_id']);

            $table->foreign('review_id')->references('id')->on('reviews')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
