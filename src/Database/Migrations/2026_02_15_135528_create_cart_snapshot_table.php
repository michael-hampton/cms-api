<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateCartSnapshotTable extends Migration
{
    public function up(): void
    {
        Schema::create('cart_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('email', 255);
            $table->string('session_id', 255);
            $table->string('checkout_token', 64)->unique();
            $table->foreignId('site_id');
            $table->text('cart_data'); // JSON of cart items
            $table->timestamp('expires_at');
            $table->timestamp('created_at');

            $table->foreign('site_id')->references('id')->on('sites');

            // Composite indexes for common queries
            $table->index(['email', 'site_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
