<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddUseHeroToPageGrids extends Migration
{
    public function up(): void
    {
        Schema::table('page_grids', function (Blueprint $table) {
            $table->boolean('use_hero')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
