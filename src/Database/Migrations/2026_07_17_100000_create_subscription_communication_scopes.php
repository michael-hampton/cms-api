<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Enable/disable a subscription_communication at system, site, or plan
 * level. Resolution order (most specific wins) is implemented in
 * SubscriptionCommunicationScopeRepository::isEnabled():
 *   (site + plan) > site only > plan only > system default (both null).
 */
class CreateSubscriptionCommunicationScopes extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_communication_scopes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_communication_id');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->unsignedBigInteger('subscription_plan_id')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('subscription_communication_id')
                ->references('id')->on('subscription_communications')
                ->cascadeOnDelete();
            $table->foreign('site_id')
                ->references('id')->on('sites')
                ->cascadeOnDelete();
            $table->foreign('subscription_plan_id')
                ->references('id')->on('subscription_plans')
                ->cascadeOnDelete();

            $table->index(
                ['subscription_communication_id', 'site_id', 'subscription_plan_id'],
                'scs_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_communication_scopes');
    }
}
