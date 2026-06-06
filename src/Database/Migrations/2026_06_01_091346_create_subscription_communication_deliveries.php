<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionCommunicationDeliveries extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_communication_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_communication_id');
            $table->unsignedBigInteger('subscription_communication_schedule_id')->nullable();
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('segment_id')->nullable();
            $table->unsignedBigInteger('subscription_segment_id')->nullable();
            $table->string('channel');
            $table->string('status');
            $table->string('token')->unique()->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('subscription_communication_id')
                ->references('id')->on('subscription_communications');
            $table->foreign('subscription_id')
                ->references('id')->on('subscriptions');

            $table->index(['subscription_id', 'subscription_communication_id']);
            $table->index(['subscription_communication_id', 'subscription_communication_schedule_id', 'subscription_id'],
                'scd_dedupe_idx');
            $table->index('token');
        });

        Schema::create('subscription_communication_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_communication_delivery_id');
            $table->string('event_type');
            $table->string('url')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->foreign('subscription_communication_delivery_id')
                ->references('id')->on('subscription_communication_deliveries')
                ->cascadeOnDelete();

            $table->index(['subscription_communication_delivery_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
