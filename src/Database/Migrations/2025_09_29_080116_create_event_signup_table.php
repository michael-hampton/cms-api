<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateEventSignupTable extends Migration
{
    public function up(): void
    {
        Schema::create('event_signups', function (Blueprint $table) {
            $table->id();
            $table->string('event_title', 255)->nullable();
            $table->date('event_date')->nullable();
            $table->string('name', 255);
            $table->string('email', 255);
            $table->string('phone', 20)->nullable();
            $table->string('company', 255)->nullable();
            $table->text('dietary_requirements')->nullable();
            $table->text('accessibility_requirements')->nullable();
            $table->boolean('newsletter')->default(false);
            $table->json('notifications')->nullable();
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->string('confirmation_token', 64)->unique();
            $table->timestamps();

            $table->index(['event_title', 'event_date']);
            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
