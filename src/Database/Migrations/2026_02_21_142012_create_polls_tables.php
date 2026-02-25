<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreatePollsTables extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function ($table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('question');
            $table->enum('status', ['draft', 'active', 'closed'])->default('active');
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });

        Schema::create('poll_options', function ($table) {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('poll_id')->references('id')->on('polls')->onDelete('cascade');
            $table->index('poll_id');
        });

        Schema::create('poll_votes', function ($table) {
            $table->id();
            $table->unsignedBigInteger('poll_id');
            $table->unsignedBigInteger('poll_option_id');
            $table->unsignedBigInteger('member_id');
            $table->timestamp('voted_at');
            $table->timestamps();

            $table->unique(['poll_id', 'member_id']); // one vote per member per poll
            $table->foreign('poll_id')->references('id')->on('polls')->onDelete('cascade');
            $table->foreign('poll_option_id')->references('id')->on('poll_options')->onDelete('cascade');
            $table->index(['poll_id', 'poll_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
