<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateSubscriptionSegmentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_id')->index();
            $table->unsignedBigInteger('segment_id')->index();
            $table->timestamp('assigned_at');
            $table->timestamp('evaluated_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('subscription_id')
                ->references('id')->on('subscriptions')
                ->cascadeOnDelete();

            $table->foreign('segment_id')
                ->references('id')->on('segments')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
