<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateBriefDeadlines extends Migration
{
    public function up(): void
    {
        Schema::create('brief_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brief_id');
            $table->dateTime('due_date')->nullable();
            $table->integer('reminder_days')->default(0);
            $table->boolean('notify_collaborators')->default(false);
            $table->foreignId('created_by');
            $table->timestamps();

            $table->foreign('brief_id')->references('id')->on('briefs')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['brief_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
