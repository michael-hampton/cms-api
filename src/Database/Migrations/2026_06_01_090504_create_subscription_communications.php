<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionCommunications extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_communications', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type');
            $table->unsignedBigInteger('segment_id')->nullable();
            $table->string('template');
            $table->json('channels');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('segment_id')->references('id')->on('segments')->nullOnDelete();
            $table->index(['type', 'is_active']);
            $table->index(['segment_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
