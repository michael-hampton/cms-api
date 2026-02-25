<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateOrderHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('order_history', function ($table) {
            $table->id();
            $table->foreignId('order_id');
            $table->string('action'); // created, status_changed, cancelled, refunded, etc.
            $table->foreignId('user_id')->nullable();
            $table->json('changes')->nullable(); // Store old/new values
            $table->text('notes')->nullable();
            $table->timestamp('created_at');

            $table->foreign('user_id')->references('id')->on('members')->onDelete('set null');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            $table->index('order_id');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
