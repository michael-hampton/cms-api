<?php

namespace App\Tests\Unit\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\EscalationCategory;
use App\Enums\OpenCollab\EscalationStatus;
use App\Enums\OpenCollab\ModerationActionType;
use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Enums\OpenCollab\RiskSeverity;
use App\Events\OpenCollab\ModerationEscalationCreatedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Collection;
use App\Models\ModerationEscalation;
use App\Models\ModerationQueueEntry;
use App\Repositories\OpenCollab\ModerationEscalationRepository;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use App\Services\OpenCollab\Moderation\EscalationRoutingService;
use App\Services\OpenCollab\Moderation\EscalationService;
use App\Services\OpenCollab\Moderation\EscalationSlaService;
use App\Services\OpenCollab\Moderation\ModerationAuditService;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

class EscalationServiceTest extends TestCase
{
    private ModerationEscalationRepository $escalationRepository;
    private ModerationQueueRepository $queueRepository;
    private EscalationRoutingService $routingService;
    private EscalationSlaService $slaService;
    private ModerationAuditService $auditService;
    private EventDispatcher $eventDispatcher;
    private Database $database;
    private EscalationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->escalationRepository = Mockery::mock(ModerationEscalationRepository::class);
        $this->queueRepository = Mockery::mock(ModerationQueueRepository::class);
        $this->routingService = Mockery::mock(EscalationRoutingService::class);
        $this->slaService = Mockery::mock(EscalationSlaService::class);
        $this->auditService = Mockery::mock(ModerationAuditService::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new EscalationService(
            $this->escalationRepository,
            $this->queueRepository,
            $this->routingService,
            $this->slaService,
            $this->auditService,
            $this->eventDispatcher,
            $this->database,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---- escalate ----

    public function test_escalate_creates_record_updates_queue_audits_and_dispatches_event(): void
    {
        $entry = $this->queueEntry(['id' => 10, 'site_id' => 1, 'page_id' => 5]);
        $escalation = $this->escalation(['id' => 50]);
        $dueAt = new DateTimeImmutable('2026-06-16 23:59:59');

        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn($entry);

        $this->routingService->shouldReceive('teamFor')->once()->with(EscalationCategory::Copyright)->andReturn('legal');
        $this->slaService->shouldReceive('dueAt')->once()->with(EscalationCategory::Copyright, Mockery::type(DateTimeImmutable::class))->andReturn($dueAt);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->escalationRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn(array $attrs) =>
                $attrs['site_id'] === 1 &&
                $attrs['queue_entry_id'] === 10 &&
                $attrs['page_id'] === 5 &&
                $attrs['category'] === EscalationCategory::Copyright->value &&
                $attrs['severity'] === RiskSeverity::High->value &&
                $attrs['assigned_team'] === 'legal' &&
                $attrs['status'] === EscalationStatus::Open->value &&
                $attrs['due_at'] === '2026-06-16 23:59:59'
            )
            ->andReturn($escalation);

        $this->queueRepository->shouldReceive('update')
            ->once()
            ->with(10, ['status' => ModerationQueueStatus::Escalated->value]);

        $this->auditService->shouldReceive('record')
            ->once()
            ->withArgs(fn(...$args) => true);

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($event) => $event instanceof ModerationEscalationCreatedEvent && $event->escalation === $escalation);

        $result = $this->service->escalate(10, EscalationCategory::Copyright, RiskSeverity::High, 99);

        $this->assertSame($escalation, $result);
    }

    public function test_escalate_throws_when_queue_entry_not_found(): void
    {
        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn(null);

        $this->database->shouldNotReceive('transaction');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->escalate(10, EscalationCategory::Copyright, RiskSeverity::High, 99);
    }

    public function test_escalate_does_not_dispatch_event_if_transaction_throws(): void
    {
        $entry = $this->queueEntry(['id' => 10, 'site_id' => 1, 'page_id' => 5]);

        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn($entry);
        $this->routingService->shouldReceive('teamFor')->once()->andReturn('legal');
        $this->slaService->shouldReceive('dueAt')->once()->andReturn(new DateTimeImmutable());

        $this->database->shouldReceive('transaction')->once()->andThrow(new \RuntimeException('db error'));

        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\RuntimeException::class);

        $this->service->escalate(10, EscalationCategory::Copyright, RiskSeverity::High, 99);
    }

    // ---- assign ----

    public function test_assign_updates_assigned_user(): void
    {
        $escalation = $this->escalation(['id' => 50, 'site_id' => 1]);
        $updated = $this->escalation(['id' => 50, 'site_id' => 1, 'assigned_user_id' => 77]);

        $this->escalationRepository->shouldReceive('find')->once()->with(50)->andReturn($escalation);
        $this->escalationRepository->shouldReceive('update')->once()->with(50, ['assigned_user_id' => 77])->andReturn($updated);

        $result = $this->service->assign(50, 77, 1);

        $this->assertSame($updated, $result);
    }

    public function test_assign_throws_when_wrong_site(): void
    {
        $escalation = $this->escalation(['id' => 50, 'site_id' => 2]);

        $this->escalationRepository->shouldReceive('find')->once()->with(50)->andReturn($escalation);
        $this->escalationRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->assign(50, 77, 1);
    }

    // ---- acknowledge ----

    public function test_acknowledge_sets_status_and_timestamp(): void
    {
        $escalation = $this->escalation(['id' => 50, 'site_id' => 1]);
        $acknowledged = $this->escalation(['id' => 50, 'site_id' => 1, 'status' => EscalationStatus::Acknowledged]);

        $this->escalationRepository->shouldReceive('find')->once()->with(50)->andReturn($escalation);
        $this->escalationRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, array $attrs) =>
                $id === 50 &&
                $attrs['status'] === EscalationStatus::Acknowledged->value &&
                isset($attrs['acknowledged_at'])
            )
            ->andReturn($acknowledged);

        $result = $this->service->acknowledge(50, 99, 1);

        $this->assertSame($acknowledged, $result);
    }

    // ---- resolve ----

    public function test_resolve_reopens_queue_to_in_review_when_no_open_escalations_remain(): void
    {
        $escalation = $this->escalation(['id' => 50, 'site_id' => 1, 'page_id' => 5, 'queue_entry_id' => 10]);
        $resolved = $this->escalation(['id' => 50, 'site_id' => 1, 'page_id' => 5, 'queue_entry_id' => 10, 'status' => EscalationStatus::Resolved]);
        $queueEntry = $this->queueEntry(['id' => 10, 'status' => ModerationQueueStatus::Escalated->value]);

        $this->escalationRepository->shouldReceive('find')->once()->with(50)->andReturn($escalation);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $this->escalationRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, array $attrs) =>
                $id === 50 &&
                $attrs['status'] === EscalationStatus::Resolved->value &&
                $attrs['resolution'] === 'cleared' &&
                $attrs['resolution_notes'] === 'All good.'
            )
            ->andReturn($resolved);

        $this->escalationRepository->shouldReceive('openForPage')->once()->with(1, 5)->andReturn(Collection::make([]));

        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn($queueEntry);
        $this->queueRepository->shouldReceive('update')->once()
            ->with(10, ['status' => ModerationQueueStatus::InReview->value]);

        $result = $this->service->resolve(50, 99, 1, 'cleared', 'All good.');

        $this->assertSame($resolved, $result);
    }

    public function test_resolve_leaves_queue_escalated_when_other_open_escalations_remain(): void
    {
        $escalation = $this->escalation(['id' => 50, 'site_id' => 1, 'page_id' => 5, 'queue_entry_id' => 10]);
        $resolved = $this->escalation(['id' => 50, 'site_id' => 1, 'page_id' => 5, 'queue_entry_id' => 10, 'status' => EscalationStatus::Resolved]);
        $otherOpen = $this->escalation(['id' => 51, 'site_id' => 1, 'page_id' => 5]);

        $this->escalationRepository->shouldReceive('find')->once()->with(50)->andReturn($escalation);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $this->escalationRepository->shouldReceive('update')->once()->andReturn($resolved);
        $this->escalationRepository->shouldReceive('openForPage')->once()->with(1, 5)->andReturn(Collection::make([$otherOpen]));

        $this->queueRepository->shouldNotReceive('find');
        $this->queueRepository->shouldNotReceive('update');

        $result = $this->service->resolve(50, 99, 1, 'cleared', null);

        $this->assertSame($resolved, $result);
    }

    public function test_resolve_throws_when_escalation_not_found(): void
    {
        $this->escalationRepository->shouldReceive('find')->once()->with(50)->andReturn(null);

        $this->database->shouldNotReceive('transaction');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->resolve(50, 99, 1, 'cleared', null);
    }

    private function queueEntry(array $attributes = []): ModerationQueueEntry
    {
        $values = array_merge([
            'id' => 1,
            'site_id' => 1,
            'page_id' => 5,
            'page_version_id' => null,
            'status' => ModerationQueueStatus::Queued->value,
        ], $attributes);

        $entry = Mockery::mock(ModerationQueueEntry::class)->makePartial();
        foreach ($values as $key => $value) {
            $entry->{$key} = is_string($value) && $key === 'status'
                ? ModerationQueueStatus::from($value)
                : $value;
        }

        return $entry;
    }

    private function escalation(array $attributes = []): ModerationEscalation
    {
        $values = array_merge([
            'id' => 1,
            'site_id' => 1,
            'page_id' => 5,
            'queue_entry_id' => 10,
            'status' => EscalationStatus::Open,
            'assigned_user_id' => null,
        ], $attributes);

        if (is_string($values['status'])) {
            $values['status'] = EscalationStatus::from($values['status']);
        }

        $escalation = Mockery::mock(ModerationEscalation::class)->makePartial();
        foreach ($values as $key => $value) {
            $escalation->{$key} = $value;
        }

        return $escalation;
    }
}