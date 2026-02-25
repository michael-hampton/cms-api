<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateIssueDeliveriesTable extends Migration
{
    public function up(): void
    {
        Schema::create('issue_deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_id');
            $table->integer('issue_number')->nullable();
            $table->string('issue_title')->nullable();

            $table->dateTime('on_sale_date')->nullable();
            $table->dateTime('estimated_delivery_date')->nullable();

            $table->string('status')->nullable();

            // Can store carrier, tracking number, status, etc.
            $table->json('tracking_info')->nullable();

            // Arbitrary metadata
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();

            // Helpful indexes
            $table->index('subscription_id');
            $table->index('status');
            $table->index('estimated_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
