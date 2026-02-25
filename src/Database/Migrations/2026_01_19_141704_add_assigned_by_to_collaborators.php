<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddAssignedByToCollaborators extends Migration
{
    public function up(): void
    {
        Schema::table('brief_collaborators', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable();
            $table->foreign('assigned_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
