<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class RenamePagesColumn extends Migration
{
    public function up(): void
    {
        Schema::table('page_grids', function (Blueprint $table) {
            $table->dropColumn('pages');
            $table->json('items')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
