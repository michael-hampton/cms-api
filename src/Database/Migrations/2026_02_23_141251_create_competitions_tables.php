<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCompetitionsTables extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('slug')->index();
            $table->string('status')->default('active'); // active | ended | draft
            $table->string('entry_type')->default('open'); // open | badge | activity | referral | raffle | sponsored
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedBigInteger('winner_member_id')->nullable();
            $table->string('prize_description')->nullable();
            $table->json('settings')->nullable(); // entry_criteria, external_url, raffle config
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
        });

        Schema::create('competition_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_id')->index();
            $table->unsignedBigInteger('member_id')->index();
            $table->dateTime('entered_at');
            $table->string('entry_method')->nullable();
            $table->unsignedBigInteger('referred_by_member_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['competition_id', 'member_id']);
        });

        Schema::create('competition_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_id')->index();
            $table->unsignedBigInteger('member_id')->index();
            $table->dateTime('notified_at');
            $table->timestamps();

            $table->unique(['competition_id', 'member_id']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
