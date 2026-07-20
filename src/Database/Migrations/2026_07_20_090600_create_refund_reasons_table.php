<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateRefundReasonsTable extends Migration
{
    public function up(): void
    {
        Schema::create('refund_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('label', 150);
            $table->boolean('requires_note')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('code');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_reasons');
    }
}
