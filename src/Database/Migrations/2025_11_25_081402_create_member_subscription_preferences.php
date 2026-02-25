<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateMemberSubscriptionPreferences extends Migration
{
    public function up(): void
    {
        Schema::create('member_subscription_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('site_id');
            $table->boolean('email_notifications')->default(true);
            $table->string('newsletter_frequency')->default('weekly'); // daily, weekly, monthly, never
            $table->json('content_types')->nullable(); // ['news', 'blog', 'updates']
            $table->json('category_preferences')->nullable(); // [1, 2, 3] category IDs
            $table->string('unsubscribe_token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');

            $table->unique(['member_id', 'site_id']);
            $table->index('is_active');
            $table->index('unsubscribe_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
