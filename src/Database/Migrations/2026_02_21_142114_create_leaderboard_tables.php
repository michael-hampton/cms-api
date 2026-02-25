<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateLeaderboardTables extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard_entries', function ($table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('member_id');
            $table->enum('type', ['points', 'activity'])->default('points');
            $table->enum('period', ['weekly'])->default('weekly');
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->date('week_start'); // Monday of the week
            $table->timestamps();

            $table->unique(['site_id', 'member_id', 'type', 'week_start']);
            $table->index(['site_id', 'type', 'week_start', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
