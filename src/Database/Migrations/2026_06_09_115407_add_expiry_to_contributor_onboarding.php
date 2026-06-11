<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddExpiryToContributorOnboarding extends Migration
{
    public function up(): void
    {
        Schema::table('oc_contributor_onboarding', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('completed_at');
            $table->timestamp('expired_at')->nullable()->after('expires_at');
            $table->timestamp('last_activity_at')->nullable()->after('expired_at');
            $table->string('expiry_reason')->nullable()->after('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
