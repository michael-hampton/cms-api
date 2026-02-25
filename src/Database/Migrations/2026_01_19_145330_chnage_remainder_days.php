<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class ChnageRemainderDays extends Migration
{
    public function up(): void
    {
        Schema::table('brief_deadlines', function (Blueprint $table) {
            $table->dropColumn('reminder_days');
            $table->json('reminder_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
