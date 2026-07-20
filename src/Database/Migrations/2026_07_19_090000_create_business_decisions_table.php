<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Business Decisions are a generic, reusable, category-tagged concept
 * (see docs/domains — CancellationOptionsResolver). "Site level" per the
 * ticket is a single global default per category (is_default = true),
 * not a new Site entity — the Site/SubscriptionPlan-level overrides live
 * in business_decision_assignments (see the following migration).
 */
class CreateBusinessDecisionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('business_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_default']);
            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_decisions');
    }
}
