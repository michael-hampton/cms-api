<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\PayoutBatchStatus;
use App\Models\PayoutBatch;
use App\Repositories\OpenCollab\PayoutBatchRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class PayoutBatchRepositoryTest extends FunctionalTestCase
{
    private PayoutBatchRepository $repository;

    public function test_create_draft_creates_batch(): void
    {
        $batch = $this->repository->createDraft(
            siteId: 1,
            accrualWindowId: 10,
            createdBy: 99,
        );

        $this->assertSame(1, (int) $batch->site_id);
        $this->assertSame(10, (int) $batch->accrual_window_id);
        $this->assertSame(99, (int) $batch->created_by);
        $this->assertSame(PayoutBatchStatus::Draft->value, $batch->status);
    }

    public function test_mark_processing_updates_status_and_started_at(): void
    {
        $batch = PayoutBatch::create([
            'site_id' => 1,
            'status' => PayoutBatchStatus::Draft->value,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $updated = $this->repository->markProcessing((int) $batch->id);

        $this->assertSame(PayoutBatchStatus::Processing->value, $updated->status);
        $this->assertNotEmpty($updated->started_at);
    }

    public function test_mark_completed_updates_status_and_completed_at(): void
    {
        $batch = PayoutBatch::create([
            'site_id' => 1,
            'status' => PayoutBatchStatus::Processing->value,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $updated = $this->repository->markCompleted((int) $batch->id);

        $this->assertSame(PayoutBatchStatus::Completed->value, $updated->status);
        $this->assertNotEmpty($updated->completed_at);
    }

    public function test_mark_failed_updates_status_and_failed_at(): void
    {
        $batch = PayoutBatch::create([
            'site_id' => 1,
            'status' => PayoutBatchStatus::Processing->value,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $updated = $this->repository->markFailed((int) $batch->id);

        $this->assertSame(PayoutBatchStatus::Failed->value, $updated->status);
        $this->assertNotEmpty($updated->failed_at);
    }

    public function test_find_or_fail_throws_when_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payout batch [999999] not found.');

        $this->repository->findOrFail(999999);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new PayoutBatchRepository();
    }
}