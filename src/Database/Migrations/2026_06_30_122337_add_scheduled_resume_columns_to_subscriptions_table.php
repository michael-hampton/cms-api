<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddScheduledResumeColumnsToSubscriptionsTable extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dateTime('scheduled_resume_at')->nullable()->after('pause_until');
            $table->dateTime('delivery_resume_scheduled_at')->nullable()->after('delivery_pause_end');

            $table->index('scheduled_resume_at');
            $table->index('delivery_resume_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['scheduled_resume_at']);
            $table->dropIndex(['delivery_resume_scheduled_at']);

            $table->dropColumn('scheduled_resume_at');
            $table->dropColumn('delivery_resume_scheduled_at');
        });
    }
}
