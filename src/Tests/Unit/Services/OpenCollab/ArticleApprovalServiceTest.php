<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Enums\OpenCollab\RejectionReason;
use App\Enums\Pages\PageStatus;
use App\Events\OpenCollab\ArticleSubmittedForReviewEvent;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Collection;
use App\Models\Page;
use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\Cms\Pages\PageService;
use App\Services\OpenCollab\ArticleApprovalService;
use App\Services\OpenCollab\Policies\ContributorPolicy;
use Mockery;
use PHPUnit\Framework\TestCase;

class ArticleApprovalServiceTest extends TestCase
{
    private PageService $pageService;
    private ActivityRepository $activityRepository;
    private EventDispatcher $eventDispatcher;
    private ContributorPolicy $policy;
    private SiteRepository $siteRepository;
    private NotificationDispatcher $notificationDispatcher;
    private UserRepositoryInterface $userRepository;
    private ArticleApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageService = Mockery::mock(PageService::class);
        $this->activityRepository = Mockery::mock(ActivityRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->policy = Mockery::mock(ContributorPolicy::class);
        $this->siteRepository = Mockery::mock(SiteRepository::class);
        $this->notificationDispatcher = Mockery::mock(NotificationDispatcher::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);

        $this->service = new ArticleApprovalService(
            $this->pageService,
            $this->activityRepository,
            $this->eventDispatcher,
            $this->policy,
            $this->siteRepository,
            $this->notificationDispatcher,
            $this->userRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_submit_checks_policy_then_delegates_page_transition_to_page_service(): void
    {
        $page = $this->page(['status' => PageStatus::DRAFT->value]);
        $submitted = $this->page(['status' => PageStatus::WAITING_APPROVAL->value]);
        $site = Mockery::mock(Site::class);

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        $this->siteRepository->shouldReceive('find')->once()->with(1)->andReturn($site);
        $this->policy->shouldReceive('canSubmitForReview')->once()->with(7, $site)->andReturn(true);
        $this->pageService->shouldReceive('submitPageForReview')->once()->with(1, 7)->andReturn($submitted);
        $this->activityRepository->shouldReceive('record')
            ->once()
            ->with(1, 7, ActivityEventType::ArticleUpdated, ['page_id' => 1, 'action' => 'submitted_for_review']);
        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($event) => $event instanceof ArticleSubmittedForReviewEvent && $event->contributorId === 7);

        $result = $this->service->submitForReview(1, 7);

        $this->assertSame($submitted, $result);
    }

    public function test_submit_throws_when_policy_blocks(): void
    {
        $page = $this->page();
        $site = Mockery::mock(Site::class);

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        $this->siteRepository->shouldReceive('find')->once()->with(1)->andReturn($site);
        $this->policy->shouldReceive('canSubmitForReview')->once()->with(7, $site)->andReturn(false);
        $this->pageService->shouldNotReceive('submitPageForReview');

        $this->expectException(OnboardingIncompleteException::class);

        $this->service->submitForReview(1, 7);
    }

    public function test_submit_throws_for_non_owner(): void
    {
        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($this->page(['contributor_id' => 99]));
        $this->policy->shouldNotReceive('canSubmitForReview');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->submitForReview(1, 7);
    }

    public function test_approve_delegates_to_page_service_and_records_activity(): void
    {
        $published = $this->page(['status' => PageStatus::PUBLISHED->value]);

        $this->pageService->shouldReceive('approvePage')->once()->with(1, 55)->andReturn($published);
        $this->activityRepository->shouldReceive('record')
            ->once()
            ->with(1, 7, ActivityEventType::ArticlePublished, ['page_id' => 1, 'approved_by' => 55]);

        $this->assertSame($published, $this->service->approve(1, 55));
    }

    public function test_reject_delegates_to_page_service_and_records_activity(): void
    {
        $rejected = $this->page(['status' => PageStatus::REJECTED->value]);

        $this->pageService->shouldReceive('rejectPage')
            ->once()
            ->with(1, 55, RejectionReason::Quality->value, 'Needs more depth.')
            ->andReturn($rejected);
        $this->activityRepository->shouldReceive('record')
            ->once()
            ->with(1, 7, ActivityEventType::ArticleUpdated, [
                'page_id' => 1,
                'action' => 'rejected',
                'reason' => RejectionReason::Quality->value,
                'rejected_by' => 55,
            ]);

        $this->assertSame($rejected, $this->service->reject(1, 55, RejectionReason::Quality, 'Needs more depth.'));
    }

    public function test_resubmit_checks_policy_then_delegates_to_page_service(): void
    {
        $page = $this->page(['status' => PageStatus::REJECTED->value, 'resubmission_count' => 1]);
        $resubmitted = $this->page(['status' => PageStatus::WAITING_APPROVAL->value, 'resubmission_count' => 2]);
        $site = Mockery::mock(Site::class);

        $this->pageService->shouldReceive('findPage')->once()->with(1)->andReturn($page);
        $this->siteRepository->shouldReceive('find')->once()->with(1)->andReturn($site);
        $this->policy->shouldReceive('canSubmitForReview')->once()->with(7, $site)->andReturn(true);
        $this->pageService->shouldReceive('resubmitPageForReview')->once()->with(1, 7)->andReturn($resubmitted);
        $this->activityRepository->shouldReceive('record')
            ->once()
            ->with(1, 7, ActivityEventType::ArticleUpdated, [
                'page_id' => 1,
                'action' => 'resubmitted',
                'resubmission_count' => 2,
            ]);
        $this->eventDispatcher->shouldReceive('dispatch')->once()->withArgs(
            fn($event) => $event instanceof ArticleSubmittedForReviewEvent
        );

        $this->assertSame($resubmitted, $this->service->resubmit(1, 7));
    }

    public function test_pending_review_delegates_to_page_service(): void
    {
        $collection = Collection::make([$this->page()]);

        $this->pageService->shouldReceive('pendingReviewForSite')->once()->with(1)->andReturn($collection);

        $this->assertSame($collection, $this->service->pendingReviewForSite(1));
    }

    private function page(array $attributes = []): Page
    {
        $values = array_merge([
            'id' => 1,
            'site_id' => 1,
            'title' => 'Test Article',
            'status' => PageStatus::DRAFT->value,
            'contributor_id' => 7,
            'resubmission_count' => 0,
        ], $attributes);

        $page = Mockery::mock(Page::class);
        $page->shouldReceive('relationLoaded')->andReturn(false);
        foreach ($values as $key => $value) {
            $page->shouldReceive('getAttribute')->with($key)->andReturn($value)->byDefault();
        }

        return $page;
    }
}
