<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreatePageViewsTable extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function ($table) {
            $table->id();
            $table->foreignId('page_id');
            $table->foreignId('member_id')->nullable();
            $table->foreignId('site_id');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('referer')->nullable();
            $table->timestamp('viewed_at');

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();

            $table->index(['page_id', 'member_id']);
            $table->index(['member_id', 'viewed_at']);
            $table->index('viewed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
