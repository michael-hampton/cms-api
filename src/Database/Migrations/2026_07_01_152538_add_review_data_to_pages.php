<?php
// database/migrations/2026_07_01_000000_add_review_data_to_pages.php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;
use App\Models\Block;
use App\Models\Page;

class AddReviewDataToPages extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->json('review_data')->nullable()->after('hero_video_url');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('review_data');
        });
    }
}