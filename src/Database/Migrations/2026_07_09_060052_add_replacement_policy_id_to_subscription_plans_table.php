<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddReplacementPolicyIdToSubscriptionPlansTable extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->foreignId('replacement_policy_id')->nullable()->after('entitlement_type');
            $table->foreign('replacement_policy_id')->references('id')->on('replacement_policies')->restrictOnDelete();
            $table->index('replacement_policy_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('replacement_policy_id');
        });
    }
}
