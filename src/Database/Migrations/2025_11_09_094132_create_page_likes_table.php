<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePageLikesTable extends Migration
{
    public function up(): void
    {
       Schema::create('page_likes', function ($table) {
            $table->id();
            $table->foreignId('page_id');
            $table->foreignId('member_id');
            $table->foreignId('site_id');
            $table->timestamp('liked_at');

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->unique(['page_id', 'member_id', 'site_id']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
