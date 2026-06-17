<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOcModerationActions extends Migration
{
    public function up(): void
    {
        Schema::create('oc_moderation_actions', function (Blueprint $table): void {
            $table->id();

            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('queue_entry_id')->nullable();

            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('page_version_id')->nullable();

            $table->unsignedBigInteger('actor_user_id');

            $table->string('action', 30);
            $table->string('reason_code', 50)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('created_at');

            $table->index(
                ['site_id', 'page_id'],
                'idx_site_page',
            );

            $table->index(
                'queue_entry_id',
                'idx_queue_entry',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
