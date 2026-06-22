<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateIssuesDeliveredTable extends Migration
{
    public function up(): void
    {
        Schema::create('issues_delivered', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id');
            $table->foreignId('issue_delivery_id');
            $table->enum('status', ['scheduled', 'delivered', 'failed', 'pending', 'superseded'])->default('scheduled');
            $table->integer('attempts')->default(0);
            $table->datetime('delivered_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            // Ensure each subscription only has one delivery per schedule
            $table->unique(['subscription_id', 'issue_delivery_id']);

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('issue_delivery_id')->references('id')->on('issue_deliveries')->cascadeOnDelete();

            $table->index(['status', 'attempts']);
            $table->index('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues_delivered');
    }
}
