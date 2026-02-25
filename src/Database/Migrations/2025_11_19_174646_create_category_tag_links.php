<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCategoryTagLinks extends Migration
{
    public function up(): void
    {
        Schema::create('tag_categories', function (Blueprint $table) {
            $table->foreignId('category_id');
            $table->foreignId('tag_id');

            $table->foreign('tag_id')->references('id')->on('tags')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();

            $table->unique(['tag_id', 'category_id']);
            $table->index(['tag_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
