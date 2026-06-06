<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionEventsTable extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('event_type', 100);

            $table->dateTime('occurred_at');

            $table->json('metadata')->nullable();

            // Fast lookup by subscription, newest first
            $table->index(
                ['subscription_id', 'occurred_at'],
                'idx_subscription_occurred'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
