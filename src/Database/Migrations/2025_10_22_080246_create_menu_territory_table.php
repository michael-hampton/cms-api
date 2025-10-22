<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMenuTerritoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('menu_territory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id');
            $table->foreignId('territory_id');
            $table->timestamps();

            $table->foreign('menu_id')->references('id')->on('menus')->cascadeOnDelete();
            $table->foreign('territory_id')->references('id')->on('territories')->cascadeOnDelete();

            $table->unique(['menu_id', 'territory_id']);
            $table->index('menu_id');
            $table->index('territory_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
