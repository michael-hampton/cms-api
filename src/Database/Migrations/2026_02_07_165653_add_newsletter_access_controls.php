<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class AddNewsletterAccessControls extends Migration
{
    public function up(): void
    {
        Schema::table('newsletters', function (Blueprint $table) {
            // Add geographic restrictions to newsletters
            $table->json('allowed_regions')->nullable();
            $table->json('blocked_regions')->nullable();
            $table->boolean('has_geographic_restrictions')->default(false);

            // Add time-based access windows to newsletters
            $table->datetime('access_window_start')->nullable();
            $table->datetime('access_window_end')->nullable();
            $table->boolean('has_time_window')->default(false);

            // Add bundle support to newsletters
            $table->integer('bundle_id')->nullable();
            $table->boolean('requires_bundle')->default(false);
        });

        // Add region to members
        Schema::table('members', function (Blueprint $table) {
            $table->string('region', 2)->nullable();
            $table->string('timezone', 50)->nullable();
        });

        // Create bundles table
        Schema::create('subscription_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('newsletter_slugs')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->index(['site_id', 'slug']);
        });

        // Link subscriptions to bundles
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->integer('bundle_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
