<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMemberActivityAnalytics extends Migration
{
    public function up(): void
    {
        Schema::create('member_activity_analytics', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('site_id');

            $table->json('summary')->nullable();
            $table->json('scores')->nullable();
            $table->json('behaviour')->nullable();
            $table->json('trends')->nullable();
            $table->json('interests')->nullable();
            $table->json('flags')->nullable();

            $table->timestamps();

            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->cascadeOnDelete();

            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->cascadeOnDelete();

            $table->unique(['member_id', 'site_id']);

            $table->index(['member_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
