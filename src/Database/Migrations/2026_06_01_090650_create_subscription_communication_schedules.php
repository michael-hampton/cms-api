<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionCommunicationSchedules extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_communication_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_communication_id');
            $table->string('name');
            $table->string('trigger_type'); // relative | fixed
            $table->integer('offset_days')->nullable();
            $table->date('fixed_date')->nullable();
            $table->string('relative_to')->nullable();
            $table->time('send_time')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('subscription_communication_id')
                ->references('id')
                ->on('subscription_communications')
                ->cascadeOnDelete();

            $table->index(['subscription_communication_id', 'is_active']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
