<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddQueueJobIdToJobExecutionLogs extends Migration
{
    public function up(): void
    {
        Schema::table('job_execution_logs', function (Blueprint $table) {
            // Links a queue-mode execution log back to its row in `jobs`
            // (or, once processed, `failed_jobs`) so cancel/terminate can
            // reach the underlying queued job.
            $table->integer('queue_job_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
