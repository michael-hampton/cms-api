<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMemberStats extends Migration
{
    public function up(): void
    {
        Schema::create('member_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('site_id');
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('reward_claimed_count')->default(0);
            $table->unsignedInteger('articles_gifted_count')->default(0);
            $table->unsignedInteger('articles_received_count')->default(0);
            $table->timestamp('last_computed_at')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->unique(['member_id', 'site_id']);
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
