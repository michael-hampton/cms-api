<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionPremiumAccessTable extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_premium_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id');
            $table->string('premium_type', 50); // newsletter, archive, video, etc.
            $table->string('premium_identifier', 100); // insider, tech-weekly, etc.
            $table->dateTime('granted_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->cascadeOnDelete();

            $table->index(
                ['subscription_id', 'premium_type', 'is_active'],
                'idx_subscription_premium'
            );

            $table->unique(
                ['subscription_id', 'premium_type', 'premium_identifier'],
                'unique_subscription_premium'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
