<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionIssueResolutionsTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscription_issue_resolutions')) {
            return;
        }

        Schema::create('subscription_issue_resolutions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('subscription_id');
            $table->unsignedBigInteger('issue_delivery_id');
            $table->string('category');
            $table->string('decision');
            $table->text('reason');
            $table->boolean('business_decision')->default(false);
            $table->unsignedBigInteger('fulfilment_replacement_id')->nullable();
            $table->unsignedBigInteger('extension_fulfilment_id')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->index(['site_id', 'subscription_id']);
            $table->index(['subscription_id', 'issue_delivery_id']);
            $table->index(['category', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_issue_resolutions');
    }
}
