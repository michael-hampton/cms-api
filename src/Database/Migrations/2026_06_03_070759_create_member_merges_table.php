<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMemberMergesTable extends Migration
{
    public function up(): void
    {
        Schema::create('member_merges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('primary_member_id');
            $table->unsignedBigInteger('merged_member_id');
            $table->unsignedBigInteger('merged_by');
            $table->timestamp('merged_at');
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('primary_member_id');
            $table->index('merged_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
