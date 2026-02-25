<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateBoostEventsTable extends Migration
{
    public function up(): void
    {
        Schema::create('boost_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boost_id');
            $table->string('type');            // impression | click | conversion
            $table->string('session_hash')->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();

            // Unique constraint drives deduplication for impressions and clicks at DB level.
            // Conversions are excluded via application logic (repeat purchases allowed).
            $table->unique(['boost_id', 'type', 'session_hash'], 'boost_events_dedup');

            $table->index(['boost_id', 'type']);
            $table->index('occurred_at');

            $table->foreign('boost_id')->references('id')->on('boosts')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
