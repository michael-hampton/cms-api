<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPurposeAndFallbackToCampaigns extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('purpose')->default('marketing')->after('channel');
            $table->json('fallback_channels')->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
