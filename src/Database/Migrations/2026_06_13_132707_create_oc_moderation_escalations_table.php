<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOcModerationEscalationsTable extends Migration
{
    public function up(): void
    {
        Schema::create('oc_moderation_escalations', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('queue_entry_id');
            $table->unsignedBigInteger('page_id');

            $table->unsignedBigInteger('page_version_id')->nullable();
            $table->unsignedBigInteger('cms_image_id')->nullable();
            $table->unsignedBigInteger('risk_marker_id')->nullable();

            $table->string('category', 30);
            $table->string('severity', 20);
            $table->string('assigned_team', 50);
            $table->unsignedBigInteger('assigned_user_id')->nullable();

            $table->string('status', 20)->default('open');

            $table->timestamp('due_at')->nullable();
            $table->unsignedBigInteger('created_by_user_id');

            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->string('resolution', 50)->nullable();
            $table->text('resolution_notes')->nullable();

            $table->index(
                ['site_id', 'status'],
                'idx_site_status'
            );

            $table->index(
                'queue_entry_id',
                'idx_queue_entry'
            );

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
