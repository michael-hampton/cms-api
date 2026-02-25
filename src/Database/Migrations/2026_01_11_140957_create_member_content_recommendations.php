<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateMemberContentRecommendations extends Migration
{
    public function up(): void
    {
        Schema::create('member_reading_preferences', function ($table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('site_id');
            $table->json('preferred_categories')->nullable();
            $table->json('preferred_tags')->nullable();
            $table->json('preferred_authors')->nullable();
            $table->integer('engagement_score')->default(0);
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->unique(['member_id', 'site_id']);
            $table->index('engagement_score');
        });

        Schema::create('trending_content', function ($table) {
            $table->id();
            $table->foreignId('page_id');
            $table->foreignId('site_id');
            $table->integer('view_count_24h')->default(0);
            $table->integer('comment_count_24h')->default(0);
            $table->integer('like_count_24h')->default(0);
            $table->decimal('trending_score', 10, 2)->default(0);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->onDelete('cascade');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->unique(['page_id', 'site_id']);
            $table->index('trending_score');
            $table->index('last_calculated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
