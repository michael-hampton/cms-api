<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCampaignsTable extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('newsletter_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('gates_premium_content')->default(false);
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            $table->json('tracking_params')->nullable(); // UTM params, etc.
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->foreign('newsletter_id')->references('id')->on('newsletters')->onDelete('set null');
            $table->index(['site_id', 'slug']);
            $table->index(['site_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
