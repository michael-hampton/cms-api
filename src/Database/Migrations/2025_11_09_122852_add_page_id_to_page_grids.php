<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPageIdToPageGrids extends Migration
{
    public function up(): void
    {
        Schema::create('page_grid_pages', function (Blueprint $table) {
            $table->foreignId('page_grid_id');
            $table->foreignId('page_id');

            $table->foreign('page_grid_id')->references('id')->on('page_grids')->cascadeOnDelete();
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();

            $table->unique(['page_grid_id', 'page_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
