<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateOfferClicksTable extends Migration
{
    public function up(): void
    {
        Schema::create('offer_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id');
            $table->foreignId('member_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('action', 50)->default('view'); // view, click, copy_code
            $table->timestamps();

            $table->foreign('offer_id')->references('id')->on('product_offers')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('set null');

            $table->index(['offer_id', 'created_at']);
            $table->index(['member_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
