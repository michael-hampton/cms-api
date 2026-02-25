<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionWindowsTable extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_windows', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id');
            $table->foreignId('subscription_id');
            $table->foreignId('site_id');

            $table->dateTime('window_start');
            $table->dateTime('window_end');

            $table->enum('type', ['paid', 'trial']);

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('member_id')->references('id')->on('members');
            $table->foreign('subscription_id')->references('id')->on('subscriptions');
            $table->foreign('site_id')->references('id')->on('sites');

            // Optional but recommended
            $table->index(['member_id', 'site_id']);
            $table->index(['subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
