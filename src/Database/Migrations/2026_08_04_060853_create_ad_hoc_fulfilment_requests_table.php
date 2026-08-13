<?php

declare(strict_types=1);

use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

/**
 * Audit trail for manually (ad-hoc) triggered fulfilment file generation.
 *
 * Phase 1 supports a single process — the existing PrintBatch export
 * pipeline (PrintBatchExportTriggerService / PrintBatchExportService) — via
 * the nullable print_batch_id column. `process` is a discriminator so
 * later phases (e.g. label_run) can add their own nullable FK column
 * alongside this one without restructuring the table.
 *
 * This table intentionally does NOT duplicate status/file columns already
 * tracked on print_batches — it is the "who/when/why requested this"
 * record, not a second source of truth for generation state.
 */
class CreateAdHocFulfilmentRequestsTable extends Migration
{
    public function up(): void
    {
        Schema::create('ad_hoc_fulfilment_requests', function (Blueprint $table) {
            $table->id();
            $table->string('process', 50);
            $table->unsignedBigInteger('print_batch_id')->nullable();
            $table->unsignedBigInteger('requested_by_user_id');
            $table->timestamps();

            $table->foreign('print_batch_id')->references('id')->on('print_batches');
            $table->foreign('requested_by_user_id')->references('id')->on('users');

            $table->index(['process', 'created_at']);
            $table->index('requested_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_hoc_fulfilment_requests');
    }
}