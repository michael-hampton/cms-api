<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class CreateBoostsTable extends Migration
{
    public function up(): void
    {
        Schema::create('boosts', function (Blueprint $table) {
            $table->id();
            $table->string('boostable_type');      // 'product' | 'offer'
            $table->unsignedBigInteger('boostable_id');
            $table->unsignedBigInteger('merchant_id');
            $table->string('context');             // 'listing' | 'deals' | 'recommendations'
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->decimal('multiplier', 8, 4);
            $table->string('status');              // BoostStatus enum values
            $table->decimal('price_paid', 10, 2);
            $table->string('currency', 3)->default('GBP');
            $table->string('payment_reference')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['boostable_type', 'boostable_id']);
            $table->index(['status', 'ends_at']);
            $table->index('context');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
