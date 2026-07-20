<?php

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Business-maintained, admin-CRUD cancellation reasons — replaces the
 * hardcoded SubscriptionCancellationReason enum as the single source of
 * truth (decided with the requester; the enum's six cases are seeded as
 * rows with matching `code` in CancellationReasonSeeder to preserve
 * existing reporting/analytics values).
 */
class CreateCancellationReasonsTable extends Migration
{
    public function up(): void
    {
        Schema::create('cancellation_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50);
            $table->string('label', 150);
            // Mirrors the old enum's Other-requires-a-note UI rule, now
            // data-driven instead of an equality check against a case.
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
        Schema::dropIfExists('cancellation_reasons');
    }
}
