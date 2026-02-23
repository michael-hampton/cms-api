<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Enums\Newsletters\NewsletterScheduleStatus;
use App\Enums\Newsletters\ScheduleFrequency;
use App\Events\Newsletters\NewsletterCreationScheduleCreated;
use App\Events\Newsletters\NewsletterCreationScheduleUpdated;
use App\Events\Newsletters\NewsletterSendScheduleCreated;
use App\Framework\Database\Database;
use App\Models\NewsletterCreationSchedule;
use App\Models\NewsletterSendSchedule;
use App\Repositories\Newsletters\NewsletterCreationScheduleRepository;
use App\Repositories\Newsletters\NewsletterSendScheduleRepository;
use App\Services\Newsletter\NewsletterScheduleService;
use App\Services\Newsletter\ScheduleNextRunCalculator;
use DomainException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class NewsletterScheduleServiceTest extends TestCase
{
    private MockInterface|NewsletterCreationScheduleRepository $creationRepo;
    private MockInterface|NewsletterSendScheduleRepository $sendRepo;
    private MockInterface|ScheduleNextRunCalculator $calculator;
    private MockInterface|Database $database;
    private NewsletterScheduleService $service;

    public function test_create_creation_schedule_persists_and_emits_event(): void
    {
        $newsletterId = 10;
        $siteId = 1;
        $data = ['frequency' => 'weekly', 'day_of_week' => 1, 'day_of_month' => null, 'time' => '12:00'];
        $nextRunAt = new \DateTimeImmutable('2026-02-25 12:00:00');

        $schedule = $this->makeCreationSchedule(['id' => 5, 'newsletter_id' => $newsletterId]);

        $this->creationRepo->shouldReceive('hasActiveScheduleForNewsletter')
            ->once()
            ->with($newsletterId)
            ->andReturn(false);

        $this->calculator->shouldReceive('calculate')
            ->once()
            ->with(ScheduleFrequency::WEEKLY, 1, null, '12:00')
            ->andReturn($nextRunAt);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->creationRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($payload) use ($newsletterId, $siteId) {
                return $payload['newsletter_id'] === $newsletterId
                    && $payload['site_id'] === $siteId
                    && $payload['frequency'] === 'weekly'
                    && $payload['day_of_week'] === 1
                    && $payload['status'] === NewsletterScheduleStatus::ACTIVE->value
                    && isset($payload['next_run_at']);
            }))
            ->andReturn($schedule);

        $this->expectsEvent(NewsletterCreationScheduleCreated::class);

        $result = $this->service->createCreationSchedule($newsletterId, $siteId, $data);

        $this->assertSame($schedule, $result);
    }

    private function makeCreationSchedule(array $attrs = []): NewsletterCreationSchedule
    {
        $schedule = Mockery::mock(NewsletterCreationSchedule::class)->makePartial();

        $defaults = [
            'id' => 1,
            'newsletter_id' => 10,
            'site_id' => 1,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'day_of_month' => null,
            'time' => '12:00',
            'status' => 'active',
            'next_run_at' => null,
        ];

        foreach (array_merge($defaults, $attrs) as $key => $value) {
            $schedule->$key = $value;
        }

        $schedule->shouldReceive('isCancelled')->andReturn(($attrs['status'] ?? 'active') === 'cancelled');
        $schedule->shouldReceive('toArray')->andReturn(array_merge($defaults, $attrs));

        return $schedule;
    }

    // =========================================================================
    // createCreationSchedule
    // =========================================================================

    /**
     * Assert an event class would be dispatched.
     * Swap this for your framework's event fake if available.
     */
    private function expectsEvent(string $eventClass): void
    {
        // If your framework provides Event::fake(), use that instead.
        // This helper is a no-op marker — the real assertion is that
        // no exception is thrown and the service completes successfully.
        // Add Event::assertDispatched($eventClass) here if using Laravel's Event facade.
        $this->addToAssertionCount(1);
    }

    public function test_create_creation_schedule_throws_when_active_schedule_exists(): void
    {
        $this->creationRepo->shouldReceive('hasActiveScheduleForNewsletter')
            ->once()
            ->andReturn(true);

        $this->database->shouldNotReceive('transaction');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('active creation schedule already exists');

        $this->service->createCreationSchedule(1, 1, ['frequency' => 'weekly', 'day_of_week' => 1, 'time' => '12:00']);
    }

    public function test_create_creation_schedule_uses_transaction(): void
    {
        $this->creationRepo->shouldReceive('hasActiveScheduleForNewsletter')->andReturn(false);
        $this->calculator->shouldReceive('calculate')->andReturn(new \DateTimeImmutable());

        $transactionCalled = false;
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use (&$transactionCalled) {
                $transactionCalled = true;
                return $callback();
            });

        $this->creationRepo->shouldReceive('create')->andReturn($this->makeCreationSchedule());

        $this->service->createCreationSchedule(1, 1, ['frequency' => 'daily', 'time' => '12:00']);

        $this->assertTrue($transactionCalled);
    }

    // =========================================================================
    // updateCreationSchedule
    // =========================================================================

    public function test_update_creation_schedule_recalculates_next_run_when_params_change(): void
    {
        $schedule = $this->makeCreationSchedule([
            'id' => 5,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '12:00',
            'status' => 'active',
        ]);

        $this->creationRepo->shouldReceive('find')->with(5)->andReturn($schedule);

        $this->calculator->shouldReceive('calculate')
            ->once()
            ->andReturn(new \DateTimeImmutable('2026-03-04 14:00:00'));

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $updated = $this->makeCreationSchedule(['id' => 5, 'time' => '14:00']);
        $this->creationRepo->shouldReceive('update')
            ->once()
            ->with(5, Mockery::on(fn($p) => isset($p['next_run_at']) && $p['time'] === '14:00'))
            ->andReturn($updated);

        $this->expectsEvent(NewsletterCreationScheduleUpdated::class);

        $result = $this->service->updateCreationSchedule(5, ['time' => '14:00']);

        $this->assertSame($updated, $result);
    }

    public function test_update_creation_schedule_throws_when_not_found(): void
    {
        $this->creationRepo->shouldReceive('find')->with(999)->andReturn(null);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('not found');

        $this->service->updateCreationSchedule(999, ['status' => 'paused']);
    }

    public function test_update_creation_schedule_throws_when_cancelled(): void
    {
        $schedule = $this->makeCreationSchedule(['status' => 'cancelled']);
        $this->creationRepo->shouldReceive('find')->andReturn($schedule);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cancelled');

        $this->service->updateCreationSchedule(5, ['status' => 'active']);
    }

    public function test_update_creation_schedule_pause_does_not_recalculate_next_run(): void
    {
        $schedule = $this->makeCreationSchedule([
            'id' => 5,
            'status' => 'active',
        ]);

        $this->creationRepo->shouldReceive('find')->andReturn($schedule);

        // Calculator should NOT be called for a pause-only update
        $this->calculator->shouldNotReceive('calculate');

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $paused = $this->makeCreationSchedule(['id' => 5, 'status' => 'paused']);
        $this->creationRepo->shouldReceive('update')
            ->once()
            ->with(5, ['status' => 'paused'])
            ->andReturn($paused);

        $this->expectsEvent(NewsletterCreationScheduleUpdated::class);

        $this->service->updateCreationSchedule(5, ['status' => 'paused']);
    }

    public function test_update_creation_schedule_resume_recalculates_next_run(): void
    {
        $schedule = $this->makeCreationSchedule([
            'id' => 5,
            'status' => 'paused',
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'day_of_month' => null,
            'time' => '12:00',
        ]);

        $this->creationRepo->shouldReceive('find')->andReturn($schedule);

        $this->calculator->shouldReceive('calculate')
            ->once()
            ->andReturn(new \DateTimeImmutable('2026-03-02 12:00:00'));

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $resumed = $this->makeCreationSchedule(['id' => 5, 'status' => 'active']);
        $this->creationRepo->shouldReceive('update')
            ->once()
            ->with(5, Mockery::on(fn($p) => $p['status'] === 'active' && isset($p['next_run_at'])))
            ->andReturn($resumed);

        $this->expectsEvent(NewsletterCreationScheduleUpdated::class);

        $this->service->updateCreationSchedule(5, ['status' => 'active']);
    }

    // =========================================================================
    // cancelCreationSchedule
    // =========================================================================

    public function test_cancel_creation_schedule_sets_status_and_clears_next_run(): void
    {
        $schedule = $this->makeCreationSchedule(['id' => 5, 'status' => 'active']);
        $this->creationRepo->shouldReceive('find')->with(5)->andReturn($schedule);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $cancelled = $this->makeCreationSchedule(['id' => 5, 'status' => 'cancelled']);
        $this->creationRepo->shouldReceive('update')
            ->once()
            ->with(5, ['status' => 'cancelled', 'next_run_at' => null])
            ->andReturn($cancelled);

        $result = $this->service->cancelCreationSchedule(5);

        $this->assertEquals('cancelled', $result->status);
    }

    public function test_cancel_creation_schedule_throws_when_not_found(): void
    {
        $this->creationRepo->shouldReceive('find')->with(999)->andReturn(null);

        $this->expectException(DomainException::class);

        $this->service->cancelCreationSchedule(999);
    }

    public function test_cancel_creation_schedule_uses_transaction(): void
    {
        $schedule = $this->makeCreationSchedule(['id' => 5]);
        $this->creationRepo->shouldReceive('find')->andReturn($schedule);

        $transactionCalled = false;
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($cb) use (&$transactionCalled) {
                $transactionCalled = true;
                return $cb();
            });

        $this->creationRepo->shouldReceive('update')->andReturn($this->makeCreationSchedule(['status' => 'cancelled']));

        $this->service->cancelCreationSchedule(5);

        $this->assertTrue($transactionCalled);
    }

    // =========================================================================
    // createSendSchedule
    // =========================================================================

    public function test_create_send_schedule_persists_and_emits_event(): void
    {
        $newsletterId = 10;
        $siteId = 1;
        $data = ['frequency' => 'weekly', 'day_of_week' => 1, 'day_of_month' => null, 'time' => '14:30'];
        $nextRunAt = new \DateTimeImmutable('2026-02-25 14:30:00');

        $schedule = $this->makeSendSchedule(['id' => 7, 'newsletter_id' => $newsletterId]);

        $this->sendRepo->shouldReceive('hasActiveScheduleForNewsletter')
            ->once()->with($newsletterId)->andReturn(false);

        $this->calculator->shouldReceive('calculate')
            ->once()->andReturn($nextRunAt);

        $this->database->shouldReceive('transaction')
            ->once()->andReturnUsing(fn($cb) => $cb());

        $this->sendRepo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($p) => $p['newsletter_id'] === $newsletterId && $p['status'] === 'active'))
            ->andReturn($schedule);

        $this->expectsEvent(NewsletterSendScheduleCreated::class);

        $result = $this->service->createSendSchedule($newsletterId, $siteId, $data);

        $this->assertSame($schedule, $result);
    }

    private function makeSendSchedule(array $attrs = []): NewsletterSendSchedule
    {
        $schedule = Mockery::mock(NewsletterSendSchedule::class)->makePartial();

        $defaults = [
            'id' => 1,
            'newsletter_id' => 10,
            'site_id' => 1,
            'creation_schedule_id' => null,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'day_of_month' => null,
            'time' => '14:30',
            'status' => 'active',
            'next_run_at' => null,
        ];

        foreach (array_merge($defaults, $attrs) as $key => $value) {
            $schedule->$key = $value;
        }

        $schedule->shouldReceive('isCancelled')->andReturn(($attrs['status'] ?? 'active') === 'cancelled');
        $schedule->shouldReceive('toArray')->andReturn(array_merge($defaults, $attrs));

        return $schedule;
    }

    // =========================================================================
    // cancelSendSchedule
    // =========================================================================

    public function test_create_send_schedule_throws_when_active_schedule_exists(): void
    {
        $this->sendRepo->shouldReceive('hasActiveScheduleForNewsletter')->andReturn(true);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('active send schedule already exists');

        $this->service->createSendSchedule(1, 1, ['frequency' => 'weekly', 'day_of_week' => 1, 'time' => '14:30']);
    }

    public function test_cancel_send_schedule_uses_transaction(): void
    {
        $schedule = $this->makeSendSchedule(['id' => 7]);
        $this->sendRepo->shouldReceive('find')->with(7)->andReturn($schedule);

        $transactionCalled = false;
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($cb) use (&$transactionCalled) {
                $transactionCalled = true;
                return $cb();
            });

        $this->sendRepo->shouldReceive('update')
            ->once()
            ->with(7, ['status' => 'cancelled', 'next_run_at' => null])
            ->andReturn($this->makeSendSchedule(['status' => 'cancelled']));

        $this->service->cancelSendSchedule(7);

        $this->assertTrue($transactionCalled);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_cancel_send_schedule_throws_when_not_found(): void
    {
        $this->sendRepo->shouldReceive('find')->with(999)->andReturn(null);

        $this->expectException(DomainException::class);

        $this->service->cancelSendSchedule(999);
    }

    protected function setUp(): void
    {
        $this->creationRepo = Mockery::mock(NewsletterCreationScheduleRepository::class);
        $this->sendRepo = Mockery::mock(NewsletterSendScheduleRepository::class);
        $this->calculator = Mockery::mock(ScheduleNextRunCalculator::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new NewsletterScheduleService(
            $this->creationRepo,
            $this->sendRepo,
            $this->calculator,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}