<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddUpdatedAtToPageGrids extends Migration
{
    public function up(): void
    {
        Schema::table('page_grid_history', function (Blueprint $table) {
            $table->timestamp('updated_at')->nullable()->default('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
