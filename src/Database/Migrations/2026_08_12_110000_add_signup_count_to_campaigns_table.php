<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddSignupCountToCampaignsTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('campaigns', 'signup_count')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->unsignedInteger('signup_count')->default(0)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('campaigns', 'signup_count')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->dropColumn('signup_count');
            });
        }
    }
}
