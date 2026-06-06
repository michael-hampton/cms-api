<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddCompletedAtToContributorOnboardingTable extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_onboarding', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
