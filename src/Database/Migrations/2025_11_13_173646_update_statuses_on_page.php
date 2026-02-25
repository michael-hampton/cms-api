<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdateStatusesOnPage extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false);
            $table->dropColumn('status');
            $table->enum('status', ['draft', 'published', 'archived', 'scheduled', 'waiting_approval', 'private', 'on_hold'])->default('draft');
            $table->foreignId('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
