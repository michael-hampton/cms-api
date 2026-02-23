<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Enums\Newsletters\NewsletterScheduleStatus;
use App\Enums\Newsletters\ScheduleFrequency;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Newsletter;
use App\Models\NewsletterSendSchedule;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendScheduleRepository;
use App\Services\Newsletter\NewsletterSendScheduleRunner;
use App\Services\Newsletter\NewsletterSendService;
use App\Services\Newsletter\ScheduleNextRunCalculator;
use Mockery;
use PHPUnit\Framework\TestCase;

class NewsletterSendScheduleRunnerTest extends TestCase
{
    private NewsletterSendScheduleRunner $runner;
    private $mockScheduleRepository;
    private $mockNewsletterRepository;
    private $mockSendService;
    private $mockCalculator;
    private $mockDatabase;
    private $mockLogger;

    public function test_returns_zero_counts_when_no_schedules_due(): void
    {
        $this->mockScheduleRepository->shouldReceive('getDueSchedules')
            ->once()
            ->with(null)
            ->andReturn(collect([]));

        $result = $this->runner->run();

        $this->assertSame(['processed' => 0, 'failed' => 0, 'skipped' => 0], $result);
    }

    public function test_processes_due_schedule_and_advances_next_run_at(): void
    {
        $schedule = $this->makeSchedule();
        $newsletter = $this->makeNewsletter();
        $nextRunAt = new \DateTimeImmutable('+1 week');

        $this->mockScheduleRepository->shouldReceive('getDueSchedules')
            ->andReturn(collect([$schedule]));

        $this->mockNewsletterRepository->shouldReceive('find')
            ->with($schedule->newsletter_id)
            ->andReturn($newsletter);

        $this->mockSendService->shouldReceive('sendNewsletter')
            ->once()
            ->with($newsletter, $schedule->site_id)
            ->andReturn(['success' => true, 'send_id' => 1, 'recipients' => 3]);

        $this->mockCalculator->shouldReceive('calculate')->andReturn($nextRunAt);

        $this->mockDatabase->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->mockScheduleRepository->shouldReceive('update')
            ->once()
            ->with($schedule->id, Mockery::on(function ($data) {
                return isset($data['last_run_at']) && isset($data['next_run_at']);
            }));

        $result = $this->runner->run();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(0, $result['failed']);
    }

    // =========================================================================
    // No due schedules
    // =========================================================================

    private function makeSchedule(int $id = 1, int $newsletterId = 10): NewsletterSendSchedule
    {
        $schedule = Mockery::mock(NewsletterSendSchedule::class)->makePartial();
        $schedule->id = $id;
        $schedule->newsletter_id = $newsletterId;
        $schedule->site_id = 1;
        $schedule->frequency = ScheduleFrequency::WEEKLY->value;
        $schedule->day_of_week = 1;
        $schedule->day_of_month = null;
        $schedule->time = '08:00';
        $schedule->status = NewsletterScheduleStatus::ACTIVE->value;
        return $schedule;
    }

    // =========================================================================
    // Successful send
    // =========================================================================

    private function makeNewsletter(): Newsletter
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 10;
        $newsletter->shouldReceive('isAutomated')->andReturn(false);
        return $newsletter;
    }

    public function test_advances_next_run_at_on_partial_failure(): void
    {
        $schedule = $this->makeSchedule();
        $newsletter = $this->makeNewsletter();

        $this->mockScheduleRepository->shouldReceive('getDueSchedules')
            ->andReturn(collect([$schedule]));

        $this->mockNewsletterRepository->shouldReceive('find')->andReturn($newsletter);

        $this->mockSendService->shouldReceive('sendNewsletter')
            ->andReturn([
                'success' => false,
                'partial_failure' => true,
                'send_id' => 2,
                'recipients' => 2,
            ]);

        $this->mockCalculator->shouldReceive('calculate')->andReturn(new \DateTimeImmutable('+1 week'));
        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->mockScheduleRepository->shouldReceive('update')->once();

        $result = $this->runner->run();

        $this->assertSame(1, $result['processed']);
    }

    // =========================================================================
    // Total send failure — next_run_at must NOT advance
    // =========================================================================

    public function test_does_not_advance_next_run_at_on_total_failure(): void
    {
        $schedule = $this->makeSchedule();
        $newsletter = $this->makeNewsletter();

        $this->mockScheduleRepository->shouldReceive('getDueSchedules')
            ->andReturn(collect([$schedule]));

        $this->mockNewsletterRepository->shouldReceive('find')->andReturn($newsletter);

        $this->mockSendService->shouldReceive('sendNewsletter')
            ->andReturn(['success' => false, 'error' => 'No eligible recipients']);

        // Must NOT call update for next_run_at
        $this->mockDatabase->shouldNotReceive('transaction');
        $this->mockScheduleRepository->shouldNotReceive('update');

        $result = $this->runner->run();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(1, $result['failed']);
    }

    // =========================================================================
    // Missing newsletter — skips and still advances to avoid re-hammering
    // =========================================================================

    public function test_skips_and_advances_when_newsletter_not_found(): void
    {
        $schedule = $this->makeSchedule();

        $this->mockScheduleRepository->shouldReceive('getDueSchedules')
            ->andReturn(collect([$schedule]));

        $this->mockNewsletterRepository->shouldReceive('find')
            ->andReturn(null);

        $this->mockSendService->shouldNotReceive('sendNewsletter');

        $this->mockCalculator->shouldReceive('calculate')->andReturn(new \DateTimeImmutable('+1 week'));
        $this->mockDatabase->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->mockScheduleRepository->shouldReceive('update')->once();

        $result = $this->runner->run();

        $this->assertSame(0, $result['processed']);
        $this->assertSame(1, $result['skipped']);
    }

    // =========================================================================
    // Site ID filtering
    // =========================================================================

    public function test_passes_site_id_to_repository(): void
    {
        $this->mockScheduleRepository->shouldReceive('getDueSchedules')
            ->once()
            ->with(42)
            ->andReturn(collect([]));

        $this->runner->run(42);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Multiple schedules — each processed independently
    // =========================================================================

    public function test_processes_multiple_schedules(): void
    {
        $schedule1 = $this->makeSchedule(id: 1, newsletterId: 10);
        $schedule2 = $this->makeSchedule(id: 2, newsletterId: 20);
        $newsletter = $this->makeNewsletter();

        $this->mockScheduleRepository->shouldReceive('getDueSchedules')
            ->andReturn(collect([$schedule1, $schedule2]));

        $this->mockNewsletterRepository->shouldReceive('find')
            ->twice()
            ->andReturn($newsletter);

        $this->mockSendService->shouldReceive('sendNewsletter')
            ->twice()
            ->andReturn(['success' => true, 'send_id' => 1, 'recipients' => 1]);

        $this->mockCalculator->shouldReceive('calculate')
            ->twice()
            ->andReturn(new \DateTimeImmutable('+1 week'));

        $this->mockDatabase->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn($cb) => $cb());

        $this->mockScheduleRepository->shouldReceive('update')->twice();

        $result = $this->runner->run();

        $this->assertSame(2, $result['processed']);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockScheduleRepository = Mockery::mock(NewsletterSendScheduleRepository::class);
        $this->mockNewsletterRepository = Mockery::mock(NewsletterRepository::class);
        $this->mockSendService = Mockery::mock(NewsletterSendService::class);
        $this->mockCalculator = Mockery::mock(ScheduleNextRunCalculator::class);
        $this->mockDatabase = Mockery::mock(Database::class);
        $this->mockLogger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->runner = new NewsletterSendScheduleRunner(
            $this->mockScheduleRepository,
            $this->mockNewsletterRepository,
            $this->mockSendService,
            $this->mockCalculator,
            $this->mockDatabase,
            $this->mockLogger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}