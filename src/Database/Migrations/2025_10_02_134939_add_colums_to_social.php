<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddColumsToSocial extends Migration
{
    public function up(): void
    {
        Schema::table('page_social', function (Blueprint $table) {
            $table->json('platform_overrides')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
