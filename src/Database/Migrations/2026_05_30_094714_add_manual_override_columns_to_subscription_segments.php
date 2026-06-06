<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddManualOverrideColumnsToSubscriptionSegments extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_segments', function (Blueprint $table) {
            // 'rule_based' | 'manual' | 'webhook' | 'batch'
            $table->string('source')->default('rule_based')->index()->after('status');

            $table->text('reason')->nullable()->after('source');

            $table->timestamp('expires_at')->nullable()->after('reason');

            $table->unsignedBigInteger('assigned_by_user_id')->nullable()->after('expires_at');

            $table->foreign('assigned_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
