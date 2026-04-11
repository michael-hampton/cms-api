<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Enums\OpenCollab\RejectionReason;
use App\Enums\Pages\PageStatus;
use App\Events\OpenCollab\ArticleApprovedEvent;
use App\Events\OpenCollab\ArticleRejectedEvent;
use App\Events\OpenCollab\ArticleSubmittedForReviewEvent;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\OpenCollab\ArticleApprovalService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class ArticleApprovalServiceTest extends FunctionalTestCase
{
    private ArticleApprovalService $service;
    private MockInterface $pageRepository;
    private MockInterface $activityRepository;
    private MockInterface $eventDispatcher;
    private MockInterface $databaseMock;

    // -------------------------------------------------------------------------
    // submitForReview()
    // -------------------------------------------------------------------------

    public function test_submit_transitions_draft_to_waiting_approval(): void
    {
        $page = $this->makePage(['id' => 1, 'contributor_id' => 7, 'status' => PageStatus::DRAFT->value, 'site_id' => 1]);
        $updated = $this->makePage(['id' => 1, 'contributor_id' => 7, 'status' => PageStatus::WAITING_APPROVAL->value, 'site_id' => 1]);

        $this->pageRepository->shouldReceive('find')->with(1)->andReturn($page, $updated);
        $this->pageRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, $data) => $id === 1 && $data['status'] === PageStatus::WAITING_APPROVAL->value);
        $this->activityRepository->shouldReceive('record')->once();
        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($e) => $e instanceof ArticleSubmittedForReviewEvent && $e->contributorId === 7);

        $result = $this->service->submitForReview(1, 7);

        $this->assertEquals(PageStatus::WAITING_APPROVAL->value, $result->status);
    }

    private function makePage(array $attributes = []): Page
    {
        $defaults = [
            'id' => 1,
            'site_id' => 1,
            'title' => 'Test Article',
            'status' => PageStatus::DRAFT->value,
            'contributor_id' => 7,
            'resubmission_count' => 0,
        ];
        $page = new Page(array_merge($defaults, $attributes));
        $page->exists = true;
        return $page;
    }

    public function test_submit_transitions_on_hold_to_waiting_approval(): void
    {
        $page = $this->makePage(['id' => 1, 'contributor_id' => 7, 'status' => PageStatus::ON_HOLD->value, 'site_id' => 1]);
        $updated = $this->makePage(['id' => 1, 'status' => PageStatus::WAITING_APPROVAL->value, 'site_id' => 1]);

        $this->pageRepository->shouldReceive('find')->andReturn($page, $updated);
        $this->pageRepository->shouldReceive('update')->once();
        $this->activityRepository->shouldReceive('record')->once();
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        $this->service->submitForReview(1, 7);
        $this->assertTrue(true);
    }

    public function test_submit_throws_for_non_owner(): void
    {
        $page = $this->makePage(['id' => 1, 'contributor_id' => 99]);

        $this->pageRepository->shouldReceive('find')->andReturn($page);
        $this->pageRepository->shouldNotReceive('update');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->submitForReview(1, 7); // contributor 7, but page owned by 99
    }

    // -------------------------------------------------------------------------
    // approve()
    // -------------------------------------------------------------------------

    public function test_submit_throws_for_already_published_page(): void
    {
        $page = $this->makePage(['id' => 1, 'contributor_id' => 7, 'status' => PageStatus::PUBLISHED->value]);

        $this->pageRepository->shouldReceive('find')->andReturn($page);
        $this->pageRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->submitForReview(1, 7);
    }

    public function test_approve_transitions_to_published_and_emits_event(): void
    {
        $page = $this->makePage(['id' => 1, 'status' => PageStatus::WAITING_APPROVAL->value, 'contributor_id' => 7, 'site_id' => 1]);
        $published = $this->makePage(['id' => 1, 'status' => PageStatus::PUBLISHED->value, 'contributor_id' => 7, 'site_id' => 1]);

        $this->pageRepository->shouldReceive('find')->andReturn($page, $published);
        $this->pageRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, $data) => $data['status'] === PageStatus::PUBLISHED->value && $data['approved_by'] === 55);
        $this->activityRepository->shouldReceive('record')->once()
            ->withArgs(fn($siteId, $userId, $type) => $type === ActivityEventType::ArticlePublished);
        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($e) => $e instanceof ArticleApprovedEvent && $e->adminId === 55);

        $result = $this->service->approve(1, adminId: 55);

        $this->assertEquals(PageStatus::PUBLISHED->value, $result->status);
    }

    public function test_approve_throws_when_page_not_awaiting_approval(): void
    {
        $page = $this->makePage(['id' => 1, 'status' => PageStatus::DRAFT->value]);

        $this->pageRepository->shouldReceive('find')->andReturn($page);
        $this->pageRepository->shouldNotReceive('update');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->approve(1, 55);
    }

    // -------------------------------------------------------------------------
    // reject()
    // -------------------------------------------------------------------------

    public function test_approve_throws_when_page_not_found(): void
    {
        $this->pageRepository->shouldReceive('find')->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->approve(999, 55);
    }

    public function test_reject_transitions_to_on_hold_with_reason(): void
    {
        $page = $this->makePage(['id' => 1, 'status' => PageStatus::WAITING_APPROVAL->value, 'contributor_id' => 7, 'site_id' => 1]);
        $onHold = $this->makePage(['id' => 1, 'status' => PageStatus::ON_HOLD->value, 'contributor_id' => 7, 'site_id' => 1]);

        $this->pageRepository->shouldReceive('find')->andReturn($page, $onHold);
        $this->pageRepository->shouldReceive('update')
            ->once()
            ->withArgs(function ($id, $data): bool {
                return $data['status'] === PageStatus::ON_HOLD->value
                    && $data['rejection_reason'] === RejectionReason::Quality->value
                    && $data['rejection_notes'] === 'Needs more depth.';
            });
        $this->activityRepository->shouldReceive('record')->once();
        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($e) => $e instanceof ArticleRejectedEvent && $e->reason === RejectionReason::Quality);

        $this->service->reject(1, 55, RejectionReason::Quality, 'Needs more depth.');
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // resubmit()
    // -------------------------------------------------------------------------

    public function test_reject_throws_when_page_not_awaiting_approval(): void
    {
        $page = $this->makePage(['id' => 1, 'status' => PageStatus::PUBLISHED->value]);

        $this->pageRepository->shouldReceive('find')->andReturn($page);
        $this->pageRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->reject(1, 55, RejectionReason::Quality);
    }

    public function test_resubmit_increments_resubmission_count(): void
    {
        $page = $this->makePage([
            'id' => 1,
            'status' => PageStatus::ON_HOLD->value,
            'contributor_id' => 7,
            'site_id' => 1,
            'resubmission_count' => 1,
        ]);
        $resubmitted = $this->makePage(['id' => 1, 'status' => PageStatus::WAITING_APPROVAL->value, 'site_id' => 1]);

        $this->pageRepository->shouldReceive('find')->andReturn($page, $resubmitted);
        $this->pageRepository->shouldReceive('update')
            ->once()
            ->withArgs(fn($id, $data) => $data['resubmission_count'] === 2);
        $this->activityRepository->shouldReceive('record')->once();
        $this->eventDispatcher->shouldReceive('dispatch')->once()
            ->withArgs(fn($e) => $e instanceof ArticleSubmittedForReviewEvent);

        $this->service->resubmit(1, 7);
        $this->assertTrue(true);
    }

    public function test_resubmit_throws_for_non_on_hold_page(): void
    {
        $page = $this->makePage(['id' => 1, 'contributor_id' => 7, 'status' => PageStatus::DRAFT->value]);

        $this->pageRepository->shouldReceive('find')->andReturn($page);
        $this->pageRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->resubmit(1, 7);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_resubmit_throws_for_non_owner(): void
    {
        $page = $this->makePage(['id' => 1, 'contributor_id' => 99, 'status' => PageStatus::ON_HOLD->value]);

        $this->pageRepository->shouldReceive('find')->andReturn($page);
        $this->pageRepository->shouldNotReceive('update');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->resubmit(1, 7);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->activityRepository = Mockery::mock(ActivityRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new ArticleApprovalService(
            $this->pageRepository,
            $this->activityRepository,
            $this->eventDispatcher,
            $this->databaseMock,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}