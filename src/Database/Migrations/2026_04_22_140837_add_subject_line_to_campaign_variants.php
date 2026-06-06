<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSubjectLineToCampaignVariants extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_variants', function (Blueprint $table) {
            // After 'key' to keep schema readable
            $table->string('subject_line')->nullable()->after('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
