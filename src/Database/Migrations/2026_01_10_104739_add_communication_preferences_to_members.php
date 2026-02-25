<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCommunicationPreferencesToMembers extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->json('communication_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
