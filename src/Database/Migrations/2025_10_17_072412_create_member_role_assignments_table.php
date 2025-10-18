<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMemberRoleAssignmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('member_role_assignments', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('role_id');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'role_id']);
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('member_roles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
