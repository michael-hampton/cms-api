<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNewsletterSendPageViews extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_send_page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_send_id');
            $table->foreignId('page_id');
            $table->string('email')->nullable();
            $table->dateTime('clicked_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->index('newsletter_send_id', 'idx_newsletter_send');
            $table->index('page_id', 'idx_page');
            $table->index('email', 'idx_email');

            $table->foreign('newsletter_send_id')
                ->references('id')
                ->on('newsletter_sends')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
