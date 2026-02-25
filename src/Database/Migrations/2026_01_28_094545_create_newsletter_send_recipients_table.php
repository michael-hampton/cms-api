<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNewsletterSendRecipientsTable extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_send_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_send_id');
            $table->string('email');
            $table->enum('status', ['pending', 'sent', 'failed', 'bounced'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->string('unsubscribe_token')->nullable();
            $table->timestamps();

            $table->foreign('newsletter_send_id')->references('id')->on('newsletter_sends')->cascadeOnDelete();

            $table->index('newsletter_send_id');
            $table->index('email');
            $table->index('status');
            $table->index(['newsletter_send_id', 'status']);
        });

        // Update newsletter_sends table
        Schema::table('newsletter_sends', function (Blueprint $table) {
            // Remove recipients JSON column
            $table->dropColumn(['recipients']);

            // Add summary columns
            $table->integer('sent_count')->default(0)->after('recipient_count');
            $table->integer('failed_count')->default(0)->after('sent_count');
            $table->integer('pending_count')->default(0)->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
