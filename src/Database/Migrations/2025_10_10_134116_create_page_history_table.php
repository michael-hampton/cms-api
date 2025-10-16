<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePageHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_history', function ($table) {
            $table->id();
            $table->foreignId('page_id');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('site_id');
            $table->string('action', 50); // created, updated, published, unpublished, duplicated, deleted
            $table->text('description')->nullable();
            $table->json('changes')->nullable(); // JSON of what changed
            $table->json('snapshot')->nullable(); // Full page snapshot
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at');

            $table->index(['page_id', 'created_at']);
            $table->index('user_id');
            $table->index('action');
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
