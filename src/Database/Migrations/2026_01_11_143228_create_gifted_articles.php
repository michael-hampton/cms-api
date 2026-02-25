<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateGiftedArticles extends Migration
{
    public function up(): void
    {
        Schema::create('gifted_articles', function ($table) {
            $table->id();
            $table->foreignId('page_id');
            $table->foreignId('gifted_by_member_id');
            $table->foreignId('site_id');
            $table->string('recipient_email');
            $table->foreignId('recipient_member_id')->nullable();
            $table->string('gift_token', 64)->unique();
            $table->timestamp('gifted_at');
            $table->timestamp('claimed_at')->nullable();
            $table->string('personal_message', 500)->nullable();
            $table->string('status', 20)->default('pending'); // pending, claimed, expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign('gifted_by_member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('recipient_member_id')->references('id')->on('members')->cascadeOnDelete();

            $table->index(['recipient_email', 'status']);
            $table->index('gift_token');
            $table->index(['gifted_by_member_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
