<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddAdminNotesToMemberRewards extends Migration
{
    public function up(): void
    {
        Schema::table('member_rewards', function (Blueprint $table) {
            $table->text('admin_notes')->nullable();
            $table->integer('declined_by_admin_id')->nullable();
            $table->datetime('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
