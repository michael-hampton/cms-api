<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class PageCategoriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id');
            $table->string('category');
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->index(['page_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
