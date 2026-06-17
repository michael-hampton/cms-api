<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class AddModalViewedAtToMemberBadges extends Migration
{
    public function up(): void
    {
        Schema::table('member_badges', function ($table) {
            $table->timestamp('modal_viewed_at')->nullable()->after('earned_at');
            $table->index(['member_id', 'modal_viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('member_badges', function ($table) {
            $table->dropIndex(['member_id', 'modal_viewed_at']);
            $table->dropColumn('modal_viewed_at');
        });
    }
}
