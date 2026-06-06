<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddTemplateToCampaignVariants extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_variants', function (Blueprint $table) {
           $table->string('template')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
