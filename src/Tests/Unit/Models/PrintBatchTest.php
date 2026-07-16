<?php

declare(strict_types=1);

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\PrintBatchStatus;
use App\Models\PrintBatch;
use Mockery;
use PHPUnit\Framework\TestCase;

class PrintBatchTest extends TestCase
{
    // =========================================================================
    // canTriggerExport
    // =========================================================================

    public function test_can_trigger_export_for_queued_batch(): void
    {
        $batch = $this->makeBatch(PrintBatchStatus::QUEUED);

        $this->assertTrue($batch->canTriggerExport());
    }

    public function test_can_trigger_export_for_pending_batch(): void
    {
        $batch = $this->makeBatch(PrintBatchStatus::PENDING);

        $this->assertTrue($batch->canTriggerExport());
    }

    public function test_can_trigger_export_for_failed_batch(): void
    {
        $batch = $this->makeBatch(PrintBatchStatus::BATCH_FAILED);

        $this->assertTrue($batch->canTriggerExport());
    }

    public function test_cannot_trigger_export_for_exported_batch(): void
    {
        $batch = $this->makeBatch(PrintBatchStatus::BATCH_EXPORTED);

        $this->assertFalse($batch->canTriggerExport());
    }

    public function test_cannot_trigger_export_for_exporting_batch(): void
    {
        $batch = $this->makeBatch(PrintBatchStatus::BATCH_EXPORTING);

        $this->assertFalse($batch->canTriggerExport());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeBatch(PrintBatchStatus $status): PrintBatch
    {
        $batch = Mockery::mock(PrintBatch::class)->makePartial();
        $batch->status = $status->value;

        return $batch;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
