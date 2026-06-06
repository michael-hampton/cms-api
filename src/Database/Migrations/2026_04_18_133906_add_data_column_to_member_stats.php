<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddDataColumnToMemberStats extends Migration
{
    public function up(): void
    {
        Schema::table('member_stats', function (Blueprint $table) {
            $table->json('data')->nullable()->after('last_computed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
