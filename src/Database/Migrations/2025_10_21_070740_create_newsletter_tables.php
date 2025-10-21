<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNewsletterTables extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->foreignId('site_id');
            $table->dateTime('subscribed_at');
            $table->boolean('confirmed')->default(false);
            $table->string('confirmation_token', 255)->nullable();
            $table->string('unsubscribe_token', 255)->nullable()->unique();
            $table->timestamps();

            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');
            $table->unique(['email', 'site_id'], 'unique_email_site');
            $table->index('site_id', 'idx_site_id');
            $table->index('confirmation_token', 'idx_confirmation_token');
            $table->index('unsubscribe_token', 'idx_unsubscribe_token');
        });

        // Newsletters table
        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('content');
            $table->string('interval', 20);
            $table->dateTime('last_sent')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('site_id');
            $table->timestamps();

            $table->foreign('site_id')
                ->references('id')
                ->on('sites')
                ->onDelete('cascade');

            $table->index('site_id', 'idx_site_id');
            $table->index('active', 'idx_active');
        });

        // Newsletter sends table
        Schema::create('newsletter_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id');
            $table->dateTime('sent_at');
            $table->integer('recipient_count');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('newsletter_id')
                ->references('id')
                ->on('newsletters')
                ->onDelete('cascade');

            $table->index('newsletter_id', 'idx_newsletter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
