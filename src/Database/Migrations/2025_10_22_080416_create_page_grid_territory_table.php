<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePageGridTerritoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_grid_territory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_grid_id');
            $table->foreignId('territory_id');
            $table->timestamps();

            $table->foreign('page_grid_id')->references('id')->on('page_grids')->cascadeOnDelete();
            $table->foreign('territory_id')->references('id')->on('territories')->cascadeOnDelete();

            $table->unique(['page_grid_id', 'territory_id']);
            $table->index('page_grid_id');
            $table->index('territory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
