<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddLastRecalculatedAtToSegmentsTable extends Migration
{
    public function up(): void
    {
        Schema::table('segments', function (Blueprint $table) {
            // Stamped by PlanSegmentRecalculationService after each batch run.
            // Null means the segment has never been recalculated via the batch process.
            $table->timestamp('last_recalculated_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
