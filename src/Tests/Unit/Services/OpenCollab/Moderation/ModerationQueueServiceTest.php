<?php

namespace App\Tests\Unit\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\ModerationActionType;
use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Exceptions\OpenCollab\ModerationQueueClaimConflictException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Collection;
use App\Models\ModerationQueueEntry;
use App\Models\Page;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use App\Repositories\OpenCollab\RiskMarkerRepository;
use App\Services\OpenCollab\Moderation\ModerationAuditService;
use App\Services\OpenCollab\Moderation\ModerationPriorityCalculator;
use App\Services\OpenCollab\Moderation\ModerationQueueService;
use App\Services\OpenCollab\Moderation\RiskScoreCalculator;
use Mockery;
use PHPUnit\Framework\TestCase;

class ModerationQueueServiceTest extends TestCase
{
    private ModerationQueueRepository $queueRepository;
    private RiskMarkerRepository $riskMarkerRepository;
    private RiskScoreCalculator $riskScoreCalculator;
    private ModerationPriorityCalculator $priorityCalculator;
    private ModerationAuditService $auditService;
    private EventDispatcher $eventDispatcher;
    private Database $database;
    private ModerationQueueService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queueRepository = Mockery::mock(ModerationQueueRepository::class);
        $this->riskMarkerRepository = Mockery::mock(RiskMarkerRepository::class);
        $this->riskScoreCalculator = Mockery::mock(RiskScoreCalculator::class);
        $this->priorityCalculator = Mockery::mock(ModerationPriorityCalculator::class);
        $this->auditService = Mockery::mock(ModerationAuditService::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new ModerationQueueService(
            $this->queueRepository,
            $this->riskMarkerRepository,
            $this->riskScoreCalculator,
            $this->priorityCalculator,
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

    // ---- enqueueForSubmission ----

    public function test_enqueue_creates_new_entry_when_none_open(): void
    {
        $page = $this->page();
        $created = $this->entry(['id' => 10, 'status' => ModerationQueueStatus::Queued->value]);
        $refreshed = $this->entry(['id' => 10, 'status' => ModerationQueueStatus::Queued->value]);

        $this->queueRepository->shouldReceive('openEntryForPage')->once()->with(1, 5)->andReturn(null);

        $this->queueRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn(array $attrs) =>
                $attrs['site_id'] === 1 &&
                $attrs['page_id'] === 5 &&
                $attrs['status'] === ModerationQueueStatus::Queued->value &&
                $attrs['risk_score'] === 0 &&
                $attrs['priority_score'] === 0
            )
            ->andReturn($created);

        // recalculatePriority(10) internals
        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn($created);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->with(1, 5)->andReturn(Collection::make([]));
        $this->riskScoreCalculator->shouldReceive('calculate')->once()->andReturn(0);
        $this->priorityCalculator->shouldReceive('forEntry')->once()->andReturn(0);
        $this->queueRepository->shouldReceive('update')->once()->with(10, ['risk_score' => 0, 'priority_score' => 0])->andReturn($created);

        $this->auditService->shouldReceive('record')
            ->once()
            ->withArgs(fn(...$args) => true); // shape verified in ModerationAuditServiceTest

        $created->shouldReceive('refresh')->once()->andReturn($refreshed);

        $result = $this->service->enqueueForSubmission($page, 7, isResubmission: false);

        $this->assertSame($refreshed, $result);
    }

    public function test_enqueue_refreshes_existing_open_entry_on_resubmission(): void
    {
        $page = $this->page();
        $existing = $this->entry(['id' => 10, 'status' => ModerationQueueStatus::ChangesRequested->value]);
        $updated = $this->entry(['id' => 10, 'status' => ModerationQueueStatus::Queued->value]);

        $this->queueRepository->shouldReceive('openEntryForPage')->once()->with(1, 5)->andReturn($existing);

        $this->queueRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, array $attrs) =>
                $id === 10 &&
                $attrs['status'] === ModerationQueueStatus::Queued->value &&
                $attrs['assigned_to_user_id'] === null &&
                $attrs['claimed_at'] === null
            )
            ->andReturn($updated);

        $this->queueRepository->shouldNotReceive('create');

        // recalculatePriority(10)
        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn($updated);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->andReturn(Collection::make([]));
        $this->riskScoreCalculator->shouldReceive('calculate')->once()->andReturn(0);
        $this->priorityCalculator->shouldReceive('forEntry')->once()->andReturn(0);
        $this->queueRepository->shouldReceive('update')->once()->with(10, ['risk_score' => 0, 'priority_score' => 0])->andReturn($updated);

        $this->auditService->shouldReceive('record')
            ->once()
            ->withArgs(function (...$args) {
                $named = $args[0] ?? [];
                return true;
            });

        $updated->shouldReceive('refresh')->once()->andReturn($updated);

        $result = $this->service->enqueueForSubmission($page, 7, isResubmission: true);

        $this->assertSame($updated, $result);
    }

    // ---- claim/release ----

    public function test_claim_succeeds_and_records_audit(): void
    {
        $entry = $this->entry(['id' => 10, 'site_id' => 1, 'page_id' => 5]);

        $this->queueRepository->shouldReceive('claimIfUnassigned')->once()->with(10, 99)->andReturn($entry);

        $this->auditService->shouldReceive('record')
            ->once()
            ->withArgs(fn(...$args) => true);

        $result = $this->service->claim(10, 99, 1);

        $this->assertSame($entry, $result);
    }

    public function test_claim_throws_conflict_when_already_assigned(): void
    {
        $this->queueRepository->shouldReceive('claimIfUnassigned')->once()->with(10, 99)->andReturn(null);

        $this->auditService->shouldNotReceive('record');

        $this->expectException(ModerationQueueClaimConflictException::class);

        $this->service->claim(10, 99, 1);
    }

    public function test_release_succeeds_when_assigned_to_caller(): void
    {
        $entry = $this->entry(['id' => 10, 'site_id' => 1, 'page_id' => 5, 'assigned_to_user_id' => 99]);
        $released = $this->entry(['id' => 10, 'site_id' => 1, 'page_id' => 5, 'assigned_to_user_id' => null]);

        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn($entry);

        $this->queueRepository->shouldReceive('update')
            ->once()
            ->with(10, [
                'assigned_to_user_id' => null,
                'claimed_at' => null,
                'status' => ModerationQueueStatus::Queued->value,
            ])
            ->andReturn($released);

        $this->auditService->shouldReceive('record')->once()->withArgs(fn(...$args) => true);

        $result = $this->service->release(10, 99, 1);

        $this->assertSame($released, $result);
    }

    public function test_release_throws_when_not_found(): void
    {
        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn(null);

        $this->queueRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->release(10, 99, 1);
    }

    public function test_release_throws_when_not_owned_by_caller(): void
    {
        $entry = $this->entry(['id' => 10, 'assigned_to_user_id' => 55]);

        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn($entry);
        $this->queueRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->release(10, 99, 1);
    }

    // ---- markApproved / markRejected / markChangesRequested ----

    public function test_mark_approved_closes_open_entry(): void
    {
        $entry = $this->entry(['id' => 10]);

        $this->queueRepository->shouldReceive('openEntryForPage')->once()->with(1, 5)->andReturn($entry);
        $this->queueRepository->shouldReceive('update')->once()
            ->with(10, ['status' => ModerationQueueStatus::Approved->value]);

        $this->service->markApproved(5, 1);

        $this->assertTrue(true);
    }

    public function test_mark_approved_is_noop_when_no_open_entry(): void
    {
        $this->queueRepository->shouldReceive('openEntryForPage')->once()->with(1, 5)->andReturn(null);
        $this->queueRepository->shouldNotReceive('update');

        $this->service->markApproved(5, 1);

        $this->addToAssertionCount(1); // explicit assertion of "did nothing"
    }

    public function test_mark_rejected_closes_open_entry(): void
    {
        $entry = $this->entry(['id' => 10]);

        $this->queueRepository->shouldReceive('openEntryForPage')->once()->with(1, 5)->andReturn($entry);
        $this->queueRepository->shouldReceive('update')->once()
            ->with(10, ['status' => ModerationQueueStatus::Rejected->value]);

        $this->service->markRejected(5, 1);

        $this->assertTrue(true);
    }

    public function test_mark_changes_requested_updates_open_entry(): void
    {
        $entry = $this->entry(['id' => 10]);

        $this->queueRepository->shouldReceive('openEntryForPage')->once()->with(1, 5)->andReturn($entry);
        $this->queueRepository->shouldReceive('update')->once()
            ->with(10, ['status' => ModerationQueueStatus::ChangesRequested->value]);

        $this->service->markChangesRequested(5, 1);

        $this->assertTrue(true);
    }

    // ---- recalculatePriority ----

    public function test_recalculate_priority_skips_closed_entries(): void
    {
        $entry = $this->entry(['id' => 10, 'status' => ModerationQueueStatus::Approved->value]);

        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn($entry);
        $this->riskMarkerRepository->shouldNotReceive('outstandingForPage');
        $this->queueRepository->shouldNotReceive('update');

        $result = $this->service->recalculatePriority(10);

        $this->assertSame($entry, $result);
    }

    public function test_recalculate_priority_throws_when_entry_missing(): void
    {
        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->recalculatePriority(10);
    }

    public function test_recalculate_priority_updates_scores_for_open_entry(): void
    {
        $entry = $this->entry(['id' => 10, 'status' => ModerationQueueStatus::Queued->value, 'site_id' => 1, 'page_id' => 5]);
        $outstanding = Collection::make(['marker']);
        $updated = $this->entry(['id' => 10, 'risk_score' => 60, 'priority_score' => 80]);

        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn($entry);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->with(1, 5)->andReturn($outstanding);
        $this->riskScoreCalculator->shouldReceive('calculate')->once()->with($outstanding)->andReturn(60);
        $this->priorityCalculator->shouldReceive('forEntry')->once()->with($entry, 60, 0)->andReturn(80);
        $this->queueRepository->shouldReceive('update')->once()
            ->with(10, ['risk_score' => 60, 'priority_score' => 80])
            ->andReturn($updated);

        $result = $this->service->recalculatePriority(10);

        $this->assertSame($updated, $result);
    }

    // ---- overridePriority ----

    public function test_override_priority_runs_in_transaction_and_audits_boost(): void
    {
        $entry = $this->entry(['id' => 10, 'status' => ModerationQueueStatus::Queued->value, 'site_id' => 1, 'page_id' => 5]);
        $updated = $this->entry(['id' => 10, 'risk_score' => 0, 'priority_score' => 25, 'site_id' => 1, 'page_id' => 5]);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $callback) => $callback());

        $this->queueRepository->shouldReceive('find')->once()->with(10)->andReturn($entry);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->andReturn(Collection::make([]));
        $this->riskScoreCalculator->shouldReceive('calculate')->once()->andReturn(0);
        $this->priorityCalculator->shouldReceive('forEntry')->once()->with($entry, 0, 25)->andReturn(25);
        $this->queueRepository->shouldReceive('update')->once()->andReturn($updated);

        $this->auditService->shouldReceive('record')
            ->once()
            ->withArgs(fn(...$args) => true);

        $result = $this->service->overridePriority(10, 25, 99, 1);

        $this->assertSame($updated, $result);
    }

    public function test_override_priority_does_not_audit_when_transaction_throws(): void
    {
        $this->database->shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('db error'));

        $this->auditService->shouldNotReceive('record');

        $this->expectException(\RuntimeException::class);

        $this->service->overridePriority(10, 25, 99, 1);
    }

    private function page(array $attributes = []): Page
    {
        $values = array_merge(['id' => 5, 'site_id' => 1], $attributes);

        $page = Mockery::mock(Page::class)->makePartial();
        foreach ($values as $key => $value) {
            $page->{$key} = $value;
        }

        return $page;
    }

    private function entry(array $attributes = []): ModerationQueueEntry
    {
        $values = array_merge([
            'id' => 1,
            'site_id' => 1,
            'page_id' => 5,
            'status' => ModerationQueueStatus::Queued,
            'risk_score' => 0,
            'priority_score' => 0,
            'assigned_to_user_id' => null,
        ], $attributes);

        if (is_string($values['status'])) {
            $values['status'] = ModerationQueueStatus::from($values['status']);
        }

        $entry = Mockery::mock(ModerationQueueEntry::class)->makePartial();
        foreach ($values as $key => $value) {
            $entry->{$key} = $value;
        }

        return $entry;
    }
}