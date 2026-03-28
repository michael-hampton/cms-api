<?php

declare(strict_types=1);

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\LabelExportFormat;
use App\Enums\Subscriptions\LabelRunStatus;
use App\Models\LabelRun;
use Mockery;
use PHPUnit\Framework\TestCase;

class LabelRunTest extends TestCase
{
    public function test_it_correctly_identifies_pending_status(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending);

        $this->assertTrue($labelRun->isPending());
        $this->assertFalse($labelRun->isGenerating());
        $this->assertFalse($labelRun->isComplete());
        $this->assertFalse($labelRun->isFailed());
    }

    // =========================================================================
    // Status queries
    // =========================================================================

    private function makeLabelRun(
        LabelRunStatus    $status,
        int               $attemptCount = 0,
        LabelExportFormat $format = LabelExportFormat::Csv,
    ): LabelRun
    {
        $labelRun = Mockery::mock(LabelRun::class)->makePartial();

        $labelRun->status = $status->value;
        $labelRun->attempt_count = $attemptCount;
        $labelRun->format = $format->value;

        return $labelRun;
    }

    public function test_it_correctly_identifies_generating_status(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Generating);

        $this->assertTrue($labelRun->isGenerating());
        $this->assertFalse($labelRun->isPending());
    }

    public function test_it_correctly_identifies_complete_status(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Complete);

        $this->assertTrue($labelRun->isComplete());
        $this->assertFalse($labelRun->isFailed());
    }

    public function test_it_correctly_identifies_failed_status(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Failed);

        $this->assertTrue($labelRun->isFailed());
        $this->assertFalse($labelRun->isComplete());
    }

    // =========================================================================
    // canRetry
    // =========================================================================

    public function test_it_can_retry_failed_run_below_max_attempts(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Failed, attemptCount: 2);

        $this->assertTrue($labelRun->canRetry(maxAttempts: 3));
    }

    public function test_it_cannot_retry_failed_run_at_max_attempts(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Failed, attemptCount: 3);

        $this->assertFalse($labelRun->canRetry(maxAttempts: 3));
    }

    public function test_it_cannot_retry_non_failed_run(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Complete, attemptCount: 0);

        $this->assertFalse($labelRun->canRetry());
    }

    public function test_it_cannot_retry_pending_run(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending, attemptCount: 0);

        $this->assertFalse($labelRun->canRetry());
    }

    // =========================================================================
    // format()
    // =========================================================================

    public function test_it_returns_correct_format_enum_for_csv(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending, format: LabelExportFormat::Csv);

        $this->assertSame(LabelExportFormat::Csv, $labelRun->format());
    }

    public function test_it_returns_correct_format_enum_for_pdf(): void
    {
        $labelRun = $this->makeLabelRun(LabelRunStatus::Pending, format: LabelExportFormat::Pdf);

        $this->assertSame(LabelExportFormat::Pdf, $labelRun->format());
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