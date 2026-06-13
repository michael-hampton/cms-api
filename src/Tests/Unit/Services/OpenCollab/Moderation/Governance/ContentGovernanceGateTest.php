<?php

namespace App\Tests\Unit\Services\OpenCollab\Moderation\Governance;

use App\Enums\OpenCollab\EscalationStatus;
use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskStatus;
use App\Enums\Pages\PageStatus;
use App\Exceptions\OpenCollab\GovernanceCheckFailedException;
use App\Framework\Support\Collection;
use App\Models\ContentRiskMarker;
use App\Models\ModerationEscalation;
use App\Models\ModerationQueueEntry;
use App\Models\Page;
use App\Repositories\Cms\ImageRepository;
use App\Repositories\OpenCollab\ModerationEscalationRepository;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use App\Repositories\OpenCollab\RiskMarkerRepository;
use App\Services\Cms\Pages\PageService;
use App\Services\OpenCollab\Moderation\Governance\ContentGovernanceGate;
use Mockery;
use PHPUnit\Framework\TestCase;

class ContentGovernanceGateTest extends TestCase
{
    private PageService $pageService;
    private ModerationQueueRepository $queueRepository;
    private RiskMarkerRepository $riskMarkerRepository;
    private ModerationEscalationRepository $escalationRepository;
    private ImageRepository $imageRepository;
    private ContentGovernanceGate $gate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageService = Mockery::mock(PageService::class);
        $this->queueRepository = Mockery::mock(ModerationQueueRepository::class);
        $this->riskMarkerRepository = Mockery::mock(RiskMarkerRepository::class);
        $this->escalationRepository = Mockery::mock(ModerationEscalationRepository::class);
        $this->imageRepository = Mockery::mock(ImageRepository::class);

        $this->gate = new ContentGovernanceGate(
            $this->pageService,
            $this->queueRepository,
            $this->riskMarkerRepository,
            $this->escalationRepository,
            $this->imageRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_passes_for_clean_page_with_no_risks_or_escalations(): void
    {
        $page = $this->page();

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->with(1, 1)->andReturn(Collection::make([]));
        $this->escalationRepository->shouldReceive('openForPage')->once()->with(1, 1)->andReturn(Collection::make([]));
        $this->queueRepository->shouldReceive('openEntryForPage')->once()->with(1, 1)->andReturn(null);

        $result = $this->gate->check(1);

        $this->assertTrue($result->passed);
        $this->assertEmpty($result->failures);
    }

    public function test_fails_when_page_not_found(): void
    {
        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn(null);

        $result = $this->gate->check(1);

        $this->assertFalse($result->passed);
        $this->assertSame('page_not_found', $result->failures[0]->code);
    }

    public function test_fails_when_page_not_waiting_approval(): void
    {
        $page = $this->page(['status' => PageStatus::DRAFT->value]);

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->andReturn(Collection::make([]));
        $this->escalationRepository->shouldReceive('openForPage')->once()->andReturn(Collection::make([]));
        $this->queueRepository->shouldReceive('openEntryForPage')->once()->andReturn(null);

        $result = $this->gate->check(1);

        $this->assertFalse($result->passed);
        $this->assertContains('page_not_awaiting_approval', array_map(fn($f) => $f->code, $result->failures));
    }

    public function test_fails_on_unresolved_critical_risk(): void
    {
        $page = $this->page();

        $marker = $this->riskMarker(RiskSeverity::Critical);

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->riskMarkerRepository
            ->shouldReceive('outstandingForPage')
            ->once()
            ->with(1, 1)
            ->andReturn(Collection::make([$marker]));

        $this->escalationRepository
            ->shouldReceive('openForPage')
            ->once()
            ->with(1, 1)
            ->andReturn(Collection::make([]));

        $this->queueRepository
            ->shouldReceive('openEntryForPage')
            ->once()
            ->with(1, 1)
            ->andReturn(null);

        $result = $this->gate->check(1);

        $failureCodes = array_map(
            static fn($failure) => $failure->code,
            $result->failures,
        );

        $this->assertFalse($result->passed);
        $this->assertContains('unresolved_critical_risk', $failureCodes);
    }

    public function test_fails_on_unresolved_high_risk(): void
    {
        $page = $this->page();

        $marker = $this->riskMarker(RiskSeverity::High);

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->riskMarkerRepository
            ->shouldReceive('outstandingForPage')
            ->once()
            ->with(1, 1)
            ->andReturn(Collection::make([$marker]));

        $this->escalationRepository
            ->shouldReceive('openForPage')
            ->once()
            ->with(1, 1)
            ->andReturn(Collection::make([]));

        $this->queueRepository
            ->shouldReceive('openEntryForPage')
            ->once()
            ->with(1, 1)
            ->andReturn(null);

        $result = $this->gate->check(1);

        $failureCodes = array_map(
            static fn($failure) => $failure->code,
            $result->failures,
        );

        $this->assertFalse($result->passed);
        $this->assertContains('unresolved_high_risk', $failureCodes);
    }

    public function test_passes_when_only_low_or_medium_risk_outstanding(): void
    {
        $page = $this->page();
        $markers = Collection::make([
            $this->riskMarker(RiskSeverity::Low),
            $this->riskMarker(RiskSeverity::Medium),
        ]);

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->andReturn($markers);
        $this->escalationRepository->shouldReceive('openForPage')->once()->andReturn(Collection::make([]));
        $this->queueRepository->shouldReceive('openEntryForPage')->once()->andReturn(null);

        $result = $this->gate->check(1);

        $this->assertTrue($result->passed);
    }

    public function test_fails_on_open_escalation(): void
    {
        $page = $this->page();
        $escalation = Mockery::mock(ModerationEscalation::class)->makePartial();
        $escalation->id = 1;
        $escalation->status = EscalationStatus::Open;
        $escalation->category = \App\Enums\OpenCollab\EscalationCategory::Copyright;

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->andReturn(Collection::make([]));
        $this->escalationRepository->shouldReceive('openForPage')->once()->andReturn(Collection::make([$escalation]));
        $this->queueRepository->shouldReceive('openEntryForPage')->once()->andReturn(null);

        $result = $this->gate->check(1);

        $this->assertFalse($result->passed);
        $this->assertContains('unresolved_escalation', array_map(fn($f) => $f->code, $result->failures));
    }

    public function test_resolved_risk_no_longer_blocks_approval(): void
    {
        $page = $this->page();

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        // resolved/cleared markers are not "outstanding" — repository contract
        // already excludes them, so an empty collection here represents that.
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->andReturn(Collection::make([]));
        $this->escalationRepository->shouldReceive('openForPage')->once()->andReturn(Collection::make([]));
        $this->queueRepository->shouldReceive('openEntryForPage')->once()->andReturn(null);

        $result = $this->gate->check(1);

        $this->assertTrue($result->passed);
    }

    public function test_fails_when_queue_entry_is_escalated(): void
    {
        $page = $this->page();
        $queueEntry = Mockery::mock(ModerationQueueEntry::class)->makePartial();
        $queueEntry->status = ModerationQueueStatus::Escalated;

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->andReturn(Collection::make([]));
        $this->escalationRepository->shouldReceive('openForPage')->once()->andReturn(Collection::make([]));
        $this->queueRepository->shouldReceive('openEntryForPage')->once()->andReturn($queueEntry);

        $result = $this->gate->check(1);

        $this->assertFalse($result->passed);
        $this->assertContains('queue_escalated', array_map(fn($f) => $f->code, $result->failures));
    }

    public function test_assert_can_approve_throws_governance_exception_on_failure(): void
    {
        $page = $this->page(['status' => PageStatus::DRAFT->value]);

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->andReturn(Collection::make([]));
        $this->escalationRepository->shouldReceive('openForPage')->once()->andReturn(Collection::make([]));
        $this->queueRepository->shouldReceive('openEntryForPage')->once()->andReturn(null);

        $this->expectException(GovernanceCheckFailedException::class);

        $this->gate->assertCanApprove(1, 99);
    }

    public function test_assert_can_approve_does_not_throw_on_valid_page(): void
    {
        $page = $this->page();

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        $this->riskMarkerRepository->shouldReceive('outstandingForPage')->once()->andReturn(Collection::make([]));
        $this->escalationRepository->shouldReceive('openForPage')->once()->andReturn(Collection::make([]));
        $this->queueRepository->shouldReceive('openEntryForPage')->once()->andReturn(null);

        $this->gate->assertCanApprove(1, 99);

        $this->addToAssertionCount(1); // no exception thrown
    }

    private function page(array $attributes = []): Page
    {
        $values = array_merge([
            'id' => 1,
            'site_id' => 1,
            'status' => PageStatus::WAITING_APPROVAL->value,
        ], $attributes);

        $page = Mockery::mock(Page::class)->makePartial();
        foreach ($values as $key => $value) {
            $page->{$key} = $value;
        }

        return $page;
    }

    private function riskMarker(RiskSeverity $severity): ContentRiskMarker
    {
        $marker = Mockery::mock(ContentRiskMarker::class)->makePartial();
        $marker->id = 1;
        $marker->severity = $severity;
        $marker->risk_type = \App\Enums\OpenCollab\RiskType::Copyright;
        $marker->status = RiskStatus::Open;

        return $marker;
    }
}