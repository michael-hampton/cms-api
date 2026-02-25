<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateSubscriptionBundleItemsTables extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_bundle_items', function ($table) {
            $table->id();
            $table->unsignedBigInteger('bundle_id')->index();
            $table->unsignedBigInteger('subscription_plan_id')->index();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->string('delivery_type', 20)->default('print')
                ->comment('print | digital — default delivery for this plan in the bundle');
            $table->timestamps();

            $table->foreign('bundle_id')
                ->references('id')->on('subscription_bundles')
                ->onDelete('cascade');

            $table->foreign('subscription_plan_id')
                ->references('id')->on('subscription_plans')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
