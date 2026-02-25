<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSupportTicketsTable extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id');
            $table->foreignId('site_id');
            $table->string('reason', 50);
            $table->foreignId('subscription_id')->nullable();
            $table->string('brand', 50)->nullable();
            $table->text('message');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 50)->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('assigned_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members');
            $table->foreign('site_id')->references('id')->on('sites');
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
            $table->foreign('assigned_to')->references('id')->on('users');

            $table->index('member_id');
            $table->index('site_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
