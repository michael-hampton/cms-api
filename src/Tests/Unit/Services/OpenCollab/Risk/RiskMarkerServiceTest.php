<?php

namespace App\Tests\Unit\Services\OpenCollab\Risk;

use App\Enums\OpenCollab\ModerationActionType;
use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskSource;
use App\Enums\OpenCollab\RiskStatus;
use App\Enums\OpenCollab\RiskType;
use App\Events\OpenCollab\RiskMarkerStatusChangedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\ContentRiskMarker;
use App\Repositories\OpenCollab\RiskMarkerRepository;
use App\Services\OpenCollab\Moderation\ModerationAuditService;
use App\Services\OpenCollab\Moderation\ModerationQueueService;
use App\Services\OpenCollab\Risk\RiskMarkerService;
use Mockery;
use PHPUnit\Framework\TestCase;

class RiskMarkerServiceTest extends TestCase
{
    private RiskMarkerRepository $riskMarkerRepository;
    private ModerationAuditService $auditService;
    private EventDispatcher $eventDispatcher;
    private Database $database;
    private ModerationQueueService $moderationQueueService;
    private RiskMarkerService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->riskMarkerRepository = Mockery::mock(RiskMarkerRepository::class);
        $this->auditService = Mockery::mock(ModerationAuditService::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->database = Mockery::mock(Database::class);
        $this->moderationQueueService = Mockery::mock(ModerationQueueService::class);

        $this->service = new RiskMarkerService(
            $this->riskMarkerRepository,
            $this->auditService,
            $this->eventDispatcher,
            $this->database,
            $this->moderationQueueService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_runs_in_transaction_audits_recalculates_queue_and_dispatches_event(): void
    {
        $marker = $this->marker(['id' => 100, 'severity' => RiskSeverity::High]);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        $this->riskMarkerRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn(array $attrs) =>
                $attrs['site_id'] === 1 &&
                $attrs['page_id'] === 5 &&
                $attrs['risk_type'] === RiskType::Copyright->value &&
                $attrs['source'] === RiskSource::Moderator->value &&
                $attrs['severity'] === RiskSeverity::High->value &&
                $attrs['status'] === RiskStatus::Open->value
            )
            ->andReturn($marker);

        $this->auditService->shouldReceive('record')
            ->once()
            ->withArgs(fn(...$args) => true);

        $this->moderationQueueService
            ->shouldReceive('recalculatePriority')
            ->once()
            ->with(10);

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($event) => $event instanceof RiskMarkerStatusChangedEvent && $event->marker === $marker);

        $result = $this->service->create(
            siteId: 1,
            pageId: 5,
            pageVersionId: null,
            cmsImageId: null,
            riskType: RiskType::Copyright,
            source: RiskSource::Moderator,
            severity: RiskSeverity::High,
            createdByUserId: 99,
            queueEntryId: 10,
        );

        $this->assertSame($marker, $result);
    }

    public function test_create_without_queue_entry_does_not_recalculate_priority(): void
    {
        $marker = $this->marker(['id' => 100]);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->riskMarkerRepository->shouldReceive('create')->once()->andReturn($marker);
        $this->auditService->shouldNotReceive('record');
        $this->moderationQueueService->shouldNotReceive('recalculatePriority');
        $this->eventDispatcher->shouldReceive('dispatch')->once()
            ->withArgs(fn($event) => $event instanceof RiskMarkerStatusChangedEvent);

        $this->service->create(
            siteId: 1,
            pageId: 5,
            pageVersionId: null,
            cmsImageId: null,
            riskType: RiskType::AiGenerated,
            source: RiskSource::CreatorDeclaration,
            severity: RiskSeverity::Medium,
            createdByUserId: null,
        );

        $this->assertTrue(true);
    }

    public function test_create_does_not_recalculate_or_dispatch_if_transaction_throws(): void
    {
        $this->database->shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('db error'));

        $this->moderationQueueService->shouldNotReceive('recalculatePriority');
        $this->eventDispatcher->shouldNotReceive('dispatch');
        $this->auditService->shouldNotReceive('record');

        $this->expectException(\RuntimeException::class);

        $this->service->create(
            siteId: 1,
            pageId: 5,
            pageVersionId: null,
            cmsImageId: null,
            riskType: RiskType::Copyright,
            source: RiskSource::Moderator,
            severity: RiskSeverity::High,
            createdByUserId: 99,
        );
    }

    public function test_resolve_succeeds_for_low_severity_without_notes(): void
    {
        $marker = $this->marker(['id' => 100, 'site_id' => 1, 'severity' => RiskSeverity::Low]);
        $resolved = $this->marker(['id' => 100, 'site_id' => 1, 'status' => RiskStatus::Cleared]);

        $this->riskMarkerRepository->shouldReceive('find')->once()->with(100)->andReturn($marker);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->riskMarkerRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, array $attrs) =>
                $id === 100 &&
                $attrs['status'] === RiskStatus::Cleared->value &&
                $attrs['reviewed_by_user_id'] === 99 &&
                $attrs['resolved_by_user_id'] === 99
            )
            ->andReturn($resolved);
        $this->auditService->shouldReceive('record')->once()->withArgs(fn(...$args) => true);
        $this->eventDispatcher->shouldReceive('dispatch')->once()
            ->withArgs(fn($event) => $event instanceof RiskMarkerStatusChangedEvent);

        $result = $this->service->resolve(100, 1, 99, null);

        $this->assertSame($resolved, $result);
    }

    public function test_resolve_throws_for_high_severity_without_notes(): void
    {
        $marker = $this->marker(['id' => 100, 'site_id' => 1, 'severity' => RiskSeverity::High]);

        $this->riskMarkerRepository->shouldReceive('find')->once()->with(100)->andReturn($marker);
        $this->riskMarkerRepository->shouldNotReceive('update');
        $this->database->shouldNotReceive('transaction');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notes are required');

        $this->service->resolve(100, 1, 99, null);
    }

    public function test_resolve_succeeds_for_critical_severity_with_notes(): void
    {
        $marker = $this->marker(['id' => 100, 'site_id' => 1, 'severity' => RiskSeverity::Critical]);
        $resolved = $this->marker(['id' => 100, 'site_id' => 1, 'status' => RiskStatus::Cleared]);

        $this->riskMarkerRepository->shouldReceive('find')->once()->with(100)->andReturn($marker);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->riskMarkerRepository->shouldReceive('update')->once()->andReturn($resolved);
        $this->auditService->shouldReceive('record')->once()->withArgs(fn(...$args) => true);
        $this->eventDispatcher->shouldReceive('dispatch')->once()->withArgs(fn($e) => $e instanceof RiskMarkerStatusChangedEvent);

        $result = $this->service->resolve(100, 1, 99, 'Confirmed cleared by legal.');

        $this->assertSame($resolved, $result);
    }

    public function test_resolve_throws_when_marker_not_found(): void
    {
        $this->riskMarkerRepository->shouldReceive('find')->once()->with(100)->andReturn(null);
        $this->database->shouldNotReceive('transaction');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->resolve(100, 1, 99, null);
    }

    public function test_resolve_throws_when_marker_belongs_to_different_site(): void
    {
        $marker = $this->marker(['id' => 100, 'site_id' => 2, 'severity' => RiskSeverity::Low]);

        $this->riskMarkerRepository->shouldReceive('find')->once()->with(100)->andReturn($marker);
        $this->database->shouldNotReceive('transaction');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->resolve(100, 1, 99, null);
    }

    public function test_dismiss_succeeds_without_notes_regardless_of_severity(): void
    {
        $marker = $this->marker(['id' => 100, 'site_id' => 1, 'severity' => RiskSeverity::Critical]);
        $dismissed = $this->marker(['id' => 100, 'site_id' => 1, 'status' => RiskStatus::Dismissed]);

        $this->riskMarkerRepository->shouldReceive('find')->once()->with(100)->andReturn($marker);
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->riskMarkerRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, array $attrs) => $attrs['status'] === RiskStatus::Dismissed->value)
            ->andReturn($dismissed);
        $this->auditService->shouldReceive('record')->once()->withArgs(fn(...$args) => true);
        $this->eventDispatcher->shouldReceive('dispatch')->once()->withArgs(fn($e) => $e instanceof RiskMarkerStatusChangedEvent);

        $result = $this->service->dismiss(100, 1, 99, null);

        $this->assertSame($dismissed, $result);
    }

    public function test_dismiss_throws_when_marker_not_found(): void
    {
        $this->riskMarkerRepository->shouldReceive('find')->once()->with(100)->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->dismiss(100, 1, 99, null);
    }

    private function marker(array $attributes = []): ContentRiskMarker
    {
        $values = array_merge([
            'id' => 1,
            'site_id' => 1,
            'page_id' => 5,
            'risk_type' => RiskType::Other,
            'source' => RiskSource::Moderator,
            'severity' => RiskSeverity::Low,
            'status' => RiskStatus::Open,
        ], $attributes);

        $marker = Mockery::mock(ContentRiskMarker::class)->makePartial();
        foreach ($values as $key => $value) {
            $marker->{$key} = $value;
        }

        return $marker;
    }
}
