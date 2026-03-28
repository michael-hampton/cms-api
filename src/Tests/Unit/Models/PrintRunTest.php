<?php

declare(strict_types=1);

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\PrintRunStatus;
use App\Models\PrintRun;
use Mockery;
use PHPUnit\Framework\TestCase;

class PrintRunTest extends TestCase
{
    public function test_it_returns_true_when_fulfilled_chunks_equals_total_chunks(): void
    {
        $printRun = $this->makePrintRun(totalChunks: 5, fulfilledChunks: 5);

        $this->assertTrue($printRun->allChunksComplete());
    }

    // =========================================================================
    // allChunksComplete
    // =========================================================================

    private function makePrintRun(
        PrintRunStatus $status = PrintRunStatus::PENDING,
        int            $totalChunks = 0,
        int            $fulfilledChunks = 0,
    ): PrintRun
    {
        // Use makePartial so we test the real model logic without a DB.
        $printRun = Mockery::mock(PrintRun::class)->makePartial();

        $printRun->status = $status->value;
        $printRun->total_chunks = $totalChunks;
        $printRun->fulfilled_chunks_count = $fulfilledChunks;

        return $printRun;
    }

    public function test_it_returns_false_when_fulfilled_chunks_less_than_total(): void
    {
        $printRun = $this->makePrintRun(totalChunks: 5, fulfilledChunks: 3);

        $this->assertFalse($printRun->allChunksComplete());
    }

    public function test_it_returns_false_when_total_chunks_is_zero(): void
    {
        // Zero total_chunks means no subscriptions — handled before chunks
        // are dispatched, but the guard must still return false safely.
        $printRun = $this->makePrintRun(totalChunks: 0, fulfilledChunks: 0);

        $this->assertFalse($printRun->allChunksComplete());
    }

    public function test_it_returns_true_when_fulfilled_chunks_exceeds_total(): void
    {
        // Should not happen in practice but the guard must be safe.
        $printRun = $this->makePrintRun(totalChunks: 3, fulfilledChunks: 4);

        $this->assertTrue($printRun->allChunksComplete());
    }

    // =========================================================================
    // Status queries
    // =========================================================================

    public function test_it_correctly_identifies_fulfilling_status(): void
    {
        $printRun = $this->makePrintRun(status: PrintRunStatus::FULFILLING);

        $this->assertTrue($printRun->isFulfilling());
        $this->assertFalse($printRun->isBatching());
        $this->assertFalse($printRun->isComplete());
        $this->assertFalse($printRun->isCancelled());
    }

    public function test_it_correctly_identifies_batching_status(): void
    {
        $printRun = $this->makePrintRun(status: PrintRunStatus::BATCHING);

        $this->assertTrue($printRun->isBatching());
        $this->assertFalse($printRun->isFulfilling());
    }

    public function test_it_correctly_identifies_batched_status(): void
    {
        $printRun = $this->makePrintRun(status: PrintRunStatus::BATCHED);

        $this->assertTrue($printRun->isBatched());
        $this->assertFalse($printRun->isBatching());
    }

    public function test_it_correctly_identifies_cancelled_status(): void
    {
        $printRun = $this->makePrintRun(status: PrintRunStatus::CANCELLED);

        $this->assertTrue($printRun->isCancelled());
        $this->assertFalse($printRun->canCancel());
    }

    // =========================================================================
    // canCancel
    // =========================================================================

    public function test_it_can_cancel_from_pending(): void
    {
        $printRun = $this->makePrintRun(status: PrintRunStatus::PENDING);
        $this->assertTrue($printRun->canCancel());
    }

//    public function test_it_can_cancel_from_fulfilling(): void
//    {
//        $printRun = $this->makePrintRun(status: PrintRunStatus::FULFILLING);
//        $this->assertTrue($printRun->canCancel());
//    }

    public function test_it_cannot_cancel_from_batching(): void
    {
        $printRun = $this->makePrintRun(status: PrintRunStatus::BATCHING);
        $this->assertFalse($printRun->canCancel());
    }

    public function test_it_cannot_cancel_from_complete(): void
    {
        $printRun = $this->makePrintRun(status: PrintRunStatus::COMPLETE);
        $this->assertFalse($printRun->canCancel());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}