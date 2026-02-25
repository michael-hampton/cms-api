<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateTieredPromotionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('tiered_promotions', function ($table) {
            $table->id();
            $table->string('name');
            $table->integer('min_subtotal_cents');
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->decimal('value', 10, 2);
            $table->boolean('stackable')->default(true);
            $table->enum('applies_to', ['one_time', 'subscription', 'both'])->default('one_time');
            $table->boolean('is_active')->default(true);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
