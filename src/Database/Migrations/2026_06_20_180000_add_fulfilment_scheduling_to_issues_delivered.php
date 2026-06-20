<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddFulfilmentSchedulingToIssuesDelivered extends Migration
{
    public function up(): void
    {
        Schema::table('issues_delivered', function (Blueprint $table) {
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('deferred_until')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->string('skip_reason')->nullable();
            $table->index(['status', 'scheduled_for']);
            $table->index(['status', 'deferred_until']);
        });
    }

    public function down(): void
    {
        Schema::table('issues_delivered', function (Blueprint $table) {
            $table->dropIndex(['status', 'scheduled_for']);
            $table->dropIndex(['status', 'deferred_until']);
            $table->dropColumn('scheduled_for');
            $table->dropColumn('deferred_until');
            $table->dropColumn('dispatched_at');
            $table->dropColumn('failed_at');
            $table->dropColumn('skip_reason');
        });
    }
}
