<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateWorkflowHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('brief_workflow_history', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->foreignId('brief_id');
            $table->foreignId('changed_by');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->index(['brief_id', 'status']);
            $table->foreign('brief_id')->references('id')->on('briefs')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
