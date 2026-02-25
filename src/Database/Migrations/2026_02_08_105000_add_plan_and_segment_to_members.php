<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddPlanAndSegmentToMembers extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('plan')->nullable()->after('email');
            $table->string('segment')->nullable()->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('plan');
            $table->dropColumn('segment');
        });
    }
}

return new AddPlanAndSegmentToMembers();
