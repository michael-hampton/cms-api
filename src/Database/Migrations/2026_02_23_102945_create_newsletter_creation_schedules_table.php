<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateNewsletterCreationSchedulesTable extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_creation_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('newsletter_id');
            $table->unsignedBigInteger('site_id');
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->tinyInteger('day_of_week')->unsigned()->nullable()->comment('0=Sun, 6=Sat — used when frequency=weekly');
            $table->tinyInteger('day_of_month')->unsigned()->nullable()->comment('1-28 — used when frequency=monthly');
            $table->char('time', 5)->comment('HH:MM in 24h format');
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active');
            $table->dateTime('next_run_at')->nullable();
            $table->dateTime('last_run_at')->nullable();
            $table->timestamps();

            $table->foreign('newsletter_id')->references('id')->on('newsletters')->onDelete('cascade');
            $table->index(['site_id', 'status']);
            $table->index('newsletter_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
