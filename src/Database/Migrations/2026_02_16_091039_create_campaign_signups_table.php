<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCampaignSignupsTable extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_signups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('site_id');

            // Optional identifiers
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email')->nullable();

            // Context for analytics
            $table->string('ip_address', 45)->nullable(); // IPv4/IPv6
            $table->string('user_agent')->nullable();
            $table->string('referrer')->nullable();

            $table->timestamps();

            // Foreign keys
            $table->foreign('campaign_id')
                ->references('id')
                ->on('campaigns')
                ->onDelete('cascade');

            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');

            // Indexes for performance
            $table->index(['campaign_id', 'created_at']);
            $table->index(['site_id', 'created_at']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
