<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOtpVerifications extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->string('otp', 255); // Will store hashed OTP
            $table->foreignId('site_id');
            $table->string('session_id', 255);
            $table->timestamp('expires_at');
            $table->integer('attempts')->default(0);
            $table->integer('resend_count')->default(0);
            $table->timestamp('last_resend_at')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites');

            // Composite indexes for common queries
            $table->index(['email', 'site_id', 'verified']);
            $table->index(['session_id', 'verified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
