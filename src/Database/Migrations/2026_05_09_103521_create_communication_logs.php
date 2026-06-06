<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCommunicationLogs extends Migration
{
    public function up(): void
    {
        Schema::create('communication_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->index();
            $table->string('type');            // transactional | marketing
            $table->string('channel')->default('email');
            $table->string('subject')->nullable();
            $table->text('preview')->nullable();
            $table->string('status');          // sent|delivered|opened|bounced|failed|unsubscribed
            $table->string('template_name')->nullable();
            $table->string('campaign_name')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
