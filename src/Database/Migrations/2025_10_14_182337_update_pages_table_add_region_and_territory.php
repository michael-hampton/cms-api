<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdatePagesTableAddRegionAndTerritory extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('region_set_id')->nullable();
            $table->foreignId('territory_id')->nullable();

            $table->foreign('region_set_id')->references('id')->on('region_sets')->cascadeOnDelete();
            $table->foreign('territory_id')->references('id')->on('territories')->cascadeOnDelete();

            $table->index('region_set_id', 'idx_region_set');
            $table->index('territory_id', 'idx_territory');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
