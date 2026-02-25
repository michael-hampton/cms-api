<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddChangedAtToWorkflowHistory extends Migration
{
    public function up(): void
    {
        Schema::table('brief_workflow_history', function (Blueprint $table) {
            $table->dateTime('changed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
