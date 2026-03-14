<?php

namespace App\Tests\Unit\Services\Cms\Briefs;

use App\Actions\Brief\DuplicateBrief;
use App\Models\BriefSchedule;
use App\Repositories\Cms\Briefs\BriefScheduleRepository;
use App\Services\Cms\BriefScheduleProcessor;
use Mockery;
use PHPUnit\Framework\TestCase;

class BriefScheduleProcessorTest extends TestCase
{
    private BriefScheduleProcessor $processor;
    private BriefScheduleRepository $scheduleRepository;
    private DuplicateBrief $duplicateBrief;

    public function test_processor_clones_brief_for_due_schedule(): void
    {
        $schedule = Mockery::mock(BriefSchedule::class)->makePartial();
        $schedule->id = 1;
        $schedule->source_brief_id = 10;
        $schedule->frequency = 'daily';
        $schedule->occurrences_count = 0;
        $schedule->end_type = 'never';
        $schedule->active = true;
        $schedule->processing = false;
        $schedule->next_run_at = now_datetime()->addMonths(1);

        $this->scheduleRepository
            ->shouldReceive('findDue')
            ->once()
            ->andReturn([$schedule]);

        $this->scheduleRepository
            ->shouldReceive('markProcessing')
            ->once()
            ->with($schedule->id)
            ->andReturn(true);

        $this->duplicateBrief
            ->shouldReceive('handle')
            ->once()
            ->with($schedule->source_brief_id, $schedule->source_brief_id, null, true);

        $this->scheduleRepository
            ->shouldReceive('update')
            ->once()
            ->with($schedule->id, Mockery::on(function ($data) {
                return isset($data['occurrences_count'])
                    && $data['occurrences_count'] === 1
                    && isset($data['next_run_at'])
                    && $data['processing'] === false;
            }));

        $this->processor->process();
        $this->assertTrue(true);
    }

    public function test_processor_deactivates_after_occurrences_limit_reached(): void
    {
        $schedule = Mockery::mock(BriefSchedule::class)->makePartial();
        $schedule->id = 1;
        $schedule->source_brief_id = 10;
        $schedule->frequency = 'daily';
        $schedule->occurrences_count = 4;
        $schedule->end_type = 'after_occurrences';
        $schedule->end_after_occurrences = 5;
        $schedule->active = true;
        $schedule->processing = false;
        $schedule->next_run_at = now_datetime()->addMonths(1);

        $this->scheduleRepository
            ->shouldReceive('findDue')
            ->once()
            ->andReturn([$schedule]);

        $this->scheduleRepository
            ->shouldReceive('markProcessing')
            ->once()
            ->with($schedule->id)
            ->andReturn(true);

        $this->duplicateBrief
            ->shouldReceive('handle')
            ->once();

        // After the 5th run, active must be set to false
        $this->scheduleRepository
            ->shouldReceive('update')
            ->once()
            ->with($schedule->id, Mockery::on(function ($data) {
                return $data['active'] === false
                    && $data['occurrences_count'] === 5;
            }));

        $this->processor->process();
        $this->assertTrue(true);
    }

    public function test_processor_deactivates_when_end_date_passed(): void
    {
        $schedule = Mockery::mock(BriefSchedule::class)->makePartial();
        $schedule->id = 1;
        $schedule->source_brief_id = 10;
        $schedule->frequency = 'daily';
        $schedule->occurrences_count = 2;
        $schedule->end_type = 'on_date';
        $schedule->end_date = new \DateTime('-1 day'); // already past
        $schedule->active = true;
        $schedule->processing = false;
        $schedule->next_run_at = now_datetime()->addMonths(1);

        $this->scheduleRepository
            ->shouldReceive('findDue')
            ->once()
            ->andReturn([$schedule]);

        $this->scheduleRepository
            ->shouldReceive('markProcessing')
            ->once()
            ->with($schedule->id)
            ->andReturn(true);

        $this->duplicateBrief
            ->shouldReceive('handle')
            ->once();

        $this->scheduleRepository
            ->shouldReceive('update')
            ->once()
            ->with($schedule->id, Mockery::on(function ($data) {
                return $data['active'] === false;
            }));

        $this->processor->process();
        $this->assertTrue(true);
    }

    public function test_processor_skips_already_processing_schedules(): void
    {
        // findDue only returns schedules where processing = false,
        // so the repository is the guard — processor itself gets an empty list.
        $this->scheduleRepository
            ->shouldReceive('findDue')
            ->once()
            ->andReturn([]);

        $this->duplicateBrief
            ->shouldNotReceive('handle');

        $this->processor->process();
        $this->assertTrue(true);
    }

    public function test_next_run_at_daily_adds_one_day(): void
    {
        $base = new \DateTime('2026-06-01 09:00:00');
        $next = $this->processor->calculateNextRunAt('daily', $base, [], null);

        $this->assertEquals('2026-06-02 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_next_run_at_monthly_adds_one_month(): void
    {
        $base = new \DateTime('2026-06-01 09:00:00');
        $next = $this->processor->calculateNextRunAt('monthly', $base, [], null);

        $this->assertEquals('2026-07-01 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_next_run_at_custom_adds_interval_days(): void
    {
        $base = new \DateTime('2026-06-01 09:00:00');
        $next = $this->processor->calculateNextRunAt('custom', $base, [], 10);

        $this->assertEquals('2026-06-11 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function test_next_run_at_weekly_finds_next_matching_weekday(): void
    {
        // 2026-06-01 is a Monday (weekday 1)
        $base = new \DateTime('2026-06-01 09:00:00');
        // Run on Wed (3) and Fri (5)
        $next = $this->processor->calculateNextRunAt('weekly', $base, [3, 5], null);

        $this->assertEquals('2026-06-03 09:00:00', $next->format('Y-m-d H:i:s'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduleRepository = Mockery::mock(BriefScheduleRepository::class);
        $this->duplicateBrief = Mockery::mock(DuplicateBrief::class);

        $this->processor = new BriefScheduleProcessor(
            $this->scheduleRepository,
            $this->duplicateBrief
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}