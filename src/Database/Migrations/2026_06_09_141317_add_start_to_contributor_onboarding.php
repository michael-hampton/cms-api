<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddStartToContributorOnboarding extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_onboarding', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
