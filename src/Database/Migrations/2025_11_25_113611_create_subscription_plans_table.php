<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Migration;

class CreateSubscriptionPlansTable extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function ($table) {
            $table->id();
            $table->foreignId('site_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('billing_period', ['monthly', 'quarterly', 'yearly', 'lifetime'])->default('monthly');
            $table->integer('trial_days')->default(0);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();

            $table->index(['site_id', 'is_active']);
            $table->unique(['site_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
