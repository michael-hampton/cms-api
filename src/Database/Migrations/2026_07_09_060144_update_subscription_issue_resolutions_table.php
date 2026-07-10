<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class UpdateSubscriptionIssueResolutionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_issue_resolutions', function (Blueprint $table) {
            $table->string('decision_source', 30)->default('policy')->after('reason');
            $table->foreignId('replacement_policy_id')->nullable()->after('decision_source');
            $table->foreign('replacement_policy_id')->references('id')->on('replacement_policies')->restrictOnDelete();
            $table->index('replacement_policy_id');
            $table->dropColumn('business_decision');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
