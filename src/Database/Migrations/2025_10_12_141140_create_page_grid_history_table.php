<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePageGridHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_grid_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_grid_id');
            $table->foreignId('user_id')->nullable();
            $table->string('action', 50);
            $table->json('changes')->nullable();
            $table->timestamp('created_at');

            // Foreign keys
            $table->foreign('page_grid_id')->references('id')->on('page_grids')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('page_grid_id', 'idx_page_grid_id');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
