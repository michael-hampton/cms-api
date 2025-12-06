<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('site_id');
            $table->string('plan_name', 255);
            $table->enum('status', ['active', 'cancelled', 'expired', 'paused', 'past_due', 'pending'])->default('active');
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->index('member_id');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
