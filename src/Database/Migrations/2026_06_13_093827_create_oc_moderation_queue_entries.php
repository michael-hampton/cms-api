<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOcModerationQueueEntries extends Migration
{
    public function up(): void
    {
        Schema::create('oc_moderation_queue_entries', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('page_version_id')->nullable();

            $table->string('status', 30)->default('queued');
            $table->timestamp('submitted_at');

            $table->integer('risk_score')->default(0);
            $table->integer('priority_score')->default(0);

            $table->timestamp('scheduled_publish_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();

            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->timestamp('claimed_at')->nullable();

            $table->timestamps();

            $table->unique(
                ['site_id', 'page_id', 'status'],
                'uq_site_page_open',
            );

            $table->index(
                ['site_id', 'status', 'priority_score'],
                'idx_site_status_priority',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
