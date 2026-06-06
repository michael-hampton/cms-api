<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddMergeColumnsToMembersTable extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Tracks which member this account was absorbed into.
            $table->unsignedBigInteger('merged_into_member_id')->nullable()->after('is_active');
            $table->timestamp('merged_at')->nullable()->after('merged_into_member_id');
            $table->unsignedBigInteger('merged_by')->nullable()->after('merged_at');

            // 'merged' is a terminal status distinct from simple deactivation.
            // Existing code uses is_active; we add a string status column so the
            // CRM can distinguish "merged" from "manually deactivated".
            $table->string('status', 32)->nullable()->after('merged_by');

            $table->index('merged_into_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
