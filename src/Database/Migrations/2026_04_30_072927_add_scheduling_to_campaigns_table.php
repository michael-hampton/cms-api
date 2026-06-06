<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSchedulingToCampaignsTable extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // When the campaign should first be dispatched (null = send immediately on activation).
            $table->timestamp('scheduled_at')->nullable()->after('end_date');

            // Tracks whether a scheduled campaign has been paused before it fired.
            // Uses a string enum rather than a boolean so future states (e.g. "cancelled") can be added.
            $table->string('schedule_status', 20)->nullable()->after('scheduled_at');

            $table->index('scheduled_at');
            $table->index('schedule_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
