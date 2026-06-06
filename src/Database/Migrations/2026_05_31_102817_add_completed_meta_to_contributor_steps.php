<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCompletedMetaToContributorSteps extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_onboarding_steps', function (Blueprint $table) {
           $table->json('completed_meta')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
