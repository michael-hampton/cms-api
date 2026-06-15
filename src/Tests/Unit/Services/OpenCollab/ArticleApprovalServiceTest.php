<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\DTO\OpenCollab\ImageBlockValidationResult;
use App\Enums\OpenCollab\ActivityEventType;
use App\Enums\OpenCollab\ModerationActionType;
use App\Enums\OpenCollab\RejectionReason;
use App\Enums\Pages\PageStatus;
use App\Events\OpenCollab\ArticleSubmittedForReviewEvent;
use App\Events\OpenCollab\ChangesRequestedEvent;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Database\Database;
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
use App\Services\OpenCollab\ImageBlockValidator;
use App\Services\OpenCollab\Moderation\Governance\ContentGovernanceGate;
use App\Services\OpenCollab\Moderation\ModerationAuditService;
use App\Services\OpenCollab\Moderation\ModerationQueueService;
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
        $this->queueService = Mockery::mock(ModerationQueueService::class);
        $this->auditService = Mockery::mock(ModerationAuditService::class);
        $this->governanceGate = Mockery::mock(ContentGovernanceGate::class);
        $this->database = Mockery::mock(Database::class);
        $this->imageBlockValidator = Mockery::mock(ImageBlockValidator::class);

        $this->database
            ->shouldReceive('transaction')
            ->byDefault()
            ->andReturnUsing(
                static fn(callable $callback) => $callback()
            );

        $this->imageBlockValidator
            ->shouldReceive('validateBlocks')
            ->byDefault()
            ->andReturn(new ImageBlockValidationResult(
                passes: true,
                errors: [],
            ));

        $this->service = new ArticleApprovalService(
            $this->pageService,
            $this->activityRepository,
            $this->eventDispatcher,
            $this->policy,
            $this->siteRepository,
            $this->notificationDispatcher,
            $this->userRepository,
            $this->queueService,
            $this->auditService,
            $this->governanceGate,
            $this->database,
            $this->imageBlockValidator,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_submit_checks_policy_then_delegates_page_transition_to_page_service(): void
    {
        $page = $this->page([
            'status' => PageStatus::DRAFT->value,
        ]);

        $submitted = $this->page([
            'status' => PageStatus::WAITING_APPROVAL->value,
        ]);

        $site = Mockery::mock(Site::class);

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->siteRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canSubmitForReview')
            ->once()
            ->with(7, $site)
            ->andReturn(true);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type(\Closure::class))
            ->andReturnUsing(
                static fn(callable $callback) => $callback()
            );

        $this->pageService
            ->shouldReceive('submitPageForReview')
            ->once()
            ->with(1, 7)
            ->andReturn($submitted);

        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                7,
                ActivityEventType::ArticleUpdated,
                [
                    'page_id' => 1,
                    'action' => 'submitted_for_review',
                ],
            );

        $this->queueService
            ->shouldReceive('enqueueForSubmission')
            ->once()
            ->with($submitted, 7, false);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(
                static fn(object $event): bool =>
                    $event instanceof ArticleSubmittedForReviewEvent
                    && $event->contributorId === 7
            );

        $result = $this->service->submitForReview(1, 7);

        $this->assertSame($submitted, $result);
    }

    public function test_submit_throws_when_policy_blocks(): void
    {
        $page = $this->page();
        $site = Mockery::mock(Site::class);

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->siteRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canSubmitForReview')
            ->once()
            ->with(7, $site)
            ->andReturn(false);

        $this->database->shouldNotReceive('transaction');
        $this->pageService->shouldNotReceive('submitPageForReview');
        $this->activityRepository->shouldNotReceive('record');
        $this->queueService->shouldNotReceive('enqueueForSubmission');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(OnboardingIncompleteException::class);

        $this->service->submitForReview(1, 7);
    }

    public function test_submit_throws_for_non_owner(): void
    {
        $page = $this->page([
            'contributor_id' => 99,
        ]);

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->siteRepository->shouldNotReceive('find');
        $this->policy->shouldNotReceive('canSubmitForReview');
        $this->database->shouldNotReceive('transaction');
        $this->pageService->shouldNotReceive('submitPageForReview');
        $this->queueService->shouldNotReceive('enqueueForSubmission');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->submitForReview(1, 7);
    }

    public function test_submit_throws_when_page_does_not_exist(): void
    {
        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturnNull();

        $this->siteRepository->shouldNotReceive('find');
        $this->policy->shouldNotReceive('canSubmitForReview');
        $this->database->shouldNotReceive('transaction');
        $this->queueService->shouldNotReceive('enqueueForSubmission');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->submitForReview(1, 7);
    }

    public function test_submit_throws_when_site_does_not_exist(): void
    {
        $page = $this->page([
            'site_id' => 42,
        ]);

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->siteRepository
            ->shouldReceive('find')
            ->once()
            ->with(42)
            ->andReturnNull();

        $this->policy->shouldNotReceive('canSubmitForReview');
        $this->database->shouldNotReceive('transaction');
        $this->pageService->shouldNotReceive('submitPageForReview');
        $this->queueService->shouldNotReceive('enqueueForSubmission');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Site [42] not found.');

        $this->service->submitForReview(1, 7);
    }

    public function test_approve_checks_governance_then_updates_page_queue_activity_and_audit(): void
    {
        $published = $this->page([
            'status' => PageStatus::PUBLISHED->value,
        ]);

        $this->governanceGate
            ->shouldReceive('assertCanApprove')
            ->once()
            ->with(1, 55);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type(\Closure::class))
            ->andReturnUsing(
                static fn(callable $callback) => $callback()
            );

        $this->pageService
            ->shouldReceive('approvePage')
            ->once()
            ->with(1, 55)
            ->andReturn($published);

        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                7,
                ActivityEventType::ArticlePublished,
                [
                    'page_id' => 1,
                    'approved_by' => 55,
                ],
            );

        $this->queueService
            ->shouldReceive('markApproved')
            ->once()
            ->with(1, 1);

        $this->auditService
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                1,
                55,
                ModerationActionType::Approved,
            );

        $result = $this->service->approve(1, 55);

        $this->assertSame($published, $result);
    }

    public function test_approve_performs_no_writes_when_governance_check_fails(): void
    {
        $exception = new \RuntimeException('Governance check failed.');

        $this->governanceGate
            ->shouldReceive('assertCanApprove')
            ->once()
            ->with(1, 55)
            ->andThrow($exception);

        $this->database->shouldNotReceive('transaction');
        $this->pageService->shouldNotReceive('approvePage');
        $this->activityRepository->shouldNotReceive('record');
        $this->queueService->shouldNotReceive('markApproved');
        $this->auditService->shouldNotReceive('record');

        $this->expectExceptionObject($exception);

        $this->service->approve(1, 55);
    }

    public function test_reject_updates_page_queue_activity_and_audit(): void
    {
        $rejected = $this->page([
            'status' => PageStatus::REJECTED->value,
        ]);

        $this->pageService
            ->shouldReceive('rejectPage')
            ->once()
            ->with(
                1,
                55,
                RejectionReason::Quality->value,
                'Needs more depth.',
            )
            ->andReturn($rejected);

        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                7,
                ActivityEventType::ArticleUpdated,
                [
                    'page_id' => 1,
                    'action' => 'rejected',
                    'reason' => RejectionReason::Quality->value,
                    'rejected_by' => 55,
                ],
            );

        $this->queueService
            ->shouldReceive('markRejected')
            ->once()
            ->with(1, 1);

        $this->auditService
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                1,
                55,
                ModerationActionType::Rejected,
                null,
                null,
                RejectionReason::Quality->value,
                'Needs more depth.',
            );

        $result = $this->service->reject(
            1,
            55,
            RejectionReason::Quality,
            'Needs more depth.',
        );

        $this->assertSame($rejected, $result);
    }

    public function test_reject_supports_null_notes(): void
    {
        $rejected = $this->page([
            'status' => PageStatus::REJECTED->value,
        ]);

        $this->pageService
            ->shouldReceive('rejectPage')
            ->once()
            ->with(
                1,
                55,
                RejectionReason::Quality->value,
                null,
            )
            ->andReturn($rejected);

        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                7,
                ActivityEventType::ArticleUpdated,
                [
                    'page_id' => 1,
                    'action' => 'rejected',
                    'reason' => RejectionReason::Quality->value,
                    'rejected_by' => 55,
                ],
            );

        $this->queueService
            ->shouldReceive('markRejected')
            ->once()
            ->with(1, 1);

        $this->auditService
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                1,
                55,
                ModerationActionType::Rejected,
                null,
                null,
                RejectionReason::Quality->value,
                null,
            );

        $result = $this->service->reject(
            1,
            55,
            RejectionReason::Quality,
        );

        $this->assertSame($rejected, $result);
    }

    public function test_resubmit_checks_policy_then_updates_page_queue_and_activity(): void
    {
        $page = $this->page([
            'status' => PageStatus::REJECTED->value,
            'resubmission_count' => 1,
        ]);

        $resubmitted = $this->page([
            'status' => PageStatus::WAITING_APPROVAL->value,
            'resubmission_count' => 2,
        ]);

        $site = Mockery::mock(Site::class);

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->siteRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canSubmitForReview')
            ->once()
            ->with(7, $site)
            ->andReturn(true);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type(\Closure::class))
            ->andReturnUsing(
                static fn(callable $callback) => $callback()
            );

        $this->pageService
            ->shouldReceive('resubmitPageForReview')
            ->once()
            ->with(1, 7)
            ->andReturn($resubmitted);

        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                7,
                ActivityEventType::ArticleUpdated,
                [
                    'page_id' => 1,
                    'action' => 'resubmitted',
                    'resubmission_count' => 2,
                ],
            );

        $this->queueService
            ->shouldReceive('enqueueForSubmission')
            ->once()
            ->with($resubmitted, 7, true);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(
                static fn(object $event): bool =>
                    $event instanceof ArticleSubmittedForReviewEvent
                    && $event->contributorId === 7
            );

        $result = $this->service->resubmit(1, 7);

        $this->assertSame($resubmitted, $result);
    }

    public function test_resubmit_throws_for_non_owner(): void
    {
        $page = $this->page([
            'contributor_id' => 99,
            'status' => PageStatus::REJECTED->value,
        ]);

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->siteRepository->shouldNotReceive('find');
        $this->policy->shouldNotReceive('canSubmitForReview');
        $this->database->shouldNotReceive('transaction');
        $this->pageService->shouldNotReceive('resubmitPageForReview');
        $this->queueService->shouldNotReceive('enqueueForSubmission');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->resubmit(1, 7);
    }

    public function test_resubmit_throws_when_policy_blocks(): void
    {
        $page = $this->page([
            'status' => PageStatus::REJECTED->value,
        ]);

        $site = Mockery::mock(Site::class);

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->siteRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canSubmitForReview')
            ->once()
            ->with(7, $site)
            ->andReturn(false);

        $this->database->shouldNotReceive('transaction');
        $this->pageService->shouldNotReceive('resubmitPageForReview');
        $this->activityRepository->shouldNotReceive('record');
        $this->queueService->shouldNotReceive('enqueueForSubmission');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(OnboardingIncompleteException::class);

        $this->service->resubmit(1, 7);
    }

    public function test_request_changes_updates_page_queue_activity_and_audit_then_dispatches_event(): void
    {
        $page = $this->page([
            'status' => PageStatus::ON_HOLD->value,
        ]);

        $this->pageService
            ->shouldReceive('requestChangesForPage')
            ->once()
            ->with(1, 55, 'Add supporting evidence.')
            ->andReturn($page);

        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                7,
                ActivityEventType::ArticleUpdated,
                [
                    'page_id' => 1,
                    'action' => 'changes_requested',
                    'requested_by' => 55,
                ],
            );

        $this->queueService
            ->shouldReceive('markChangesRequested')
            ->once()
            ->with(1, 1);

        $this->auditService
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                1,
                55,
                ModerationActionType::ChangesRequested,
                null,
                null,
                null,
                'Add supporting evidence.',
            );

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(
                static fn(object $event): bool =>
                    $event instanceof ChangesRequestedEvent
            );

        $result = $this->service->requestChanges(
            1,
            55,
            'Add supporting evidence.',
        );

        $this->assertSame($page, $result);
    }

    public function test_request_changes_does_not_dispatch_event_when_transaction_fails(): void
    {
        $exception = new \RuntimeException('Transition failed.');

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type(\Closure::class))
            ->andReturnUsing(
                static fn(callable $callback) => $callback()
            );

        $this->pageService
            ->shouldReceive('requestChangesForPage')
            ->once()
            ->with(1, 55, 'Add supporting evidence.')
            ->andThrow($exception);

        $this->activityRepository->shouldNotReceive('record');
        $this->queueService->shouldNotReceive('markChangesRequested');
        $this->auditService->shouldNotReceive('record');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectExceptionObject($exception);

        $this->service->requestChanges(
            1,
            55,
            'Add supporting evidence.',
        );
    }

    public function test_submit_does_not_dispatch_event_when_transaction_fails(): void
    {
        $page = $this->page();
        $site = Mockery::mock(Site::class);
        $exception = new \RuntimeException('Submission failed.');

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->siteRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canSubmitForReview')
            ->once()
            ->with(7, $site)
            ->andReturn(true);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type(\Closure::class))
            ->andReturnUsing(
                static fn(callable $callback) => $callback()
            );

        $this->pageService
            ->shouldReceive('submitPageForReview')
            ->once()
            ->with(1, 7)
            ->andThrow($exception);

        $this->activityRepository->shouldNotReceive('record');
        $this->queueService->shouldNotReceive('enqueueForSubmission');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectExceptionObject($exception);

        $this->service->submitForReview(1, 7);
    }

    public function test_pending_review_delegates_to_page_service(): void
    {
        $collection = Collection::make([
            $this->page(),
        ]);

        $this->pageService
            ->shouldReceive('pendingReviewForSite')
            ->once()
            ->with(1)
            ->andReturn($collection);

        $result = $this->service->pendingReviewForSite(1);

        $this->assertSame($collection, $result);
    }

    public function test_approve_delegates_to_page_service_and_records_activity(): void
    {
        $published = $this->page([
            'status' => PageStatus::PUBLISHED->value,
        ]);

        $this->governanceGate
            ->shouldReceive('assertCanApprove')
            ->once()
            ->with(1, 55);

        $this->pageService
            ->shouldReceive('approvePage')
            ->once()
            ->with(1, 55)
            ->andReturn($published);

        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                7,
                ActivityEventType::ArticlePublished,
                [
                    'page_id' => 1,
                    'approved_by' => 55,
                ],
            );

        $this->queueService
            ->shouldReceive('markApproved')
            ->once()
            ->with(1, 1);

        $this->auditService
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                1,
                55,
                ModerationActionType::Approved,
            );

        $result = $this->service->approve(1, 55);

        $this->assertSame($published, $result);
    }

    public function test_reject_delegates_to_page_service_and_records_activity(): void
    {
        $rejected = $this->page([
            'status' => PageStatus::REJECTED->value,
        ]);

        $this->pageService
            ->shouldReceive('rejectPage')
            ->once()
            ->with(
                1,
                55,
                RejectionReason::Quality->value,
                'Needs more depth.',
            )
            ->andReturn($rejected);

        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                7,
                ActivityEventType::ArticleUpdated,
                [
                    'page_id' => 1,
                    'action' => 'rejected',
                    'reason' => RejectionReason::Quality->value,
                    'rejected_by' => 55,
                ],
            );

        $this->queueService
            ->shouldReceive('markRejected')
            ->once()
            ->with(1, 1);

        $this->auditService
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                1,
                55,
                ModerationActionType::Rejected,
                null,
                null,
                RejectionReason::Quality->value,
                'Needs more depth.',
            );

        $result = $this->service->reject(
            1,
            55,
            RejectionReason::Quality,
            'Needs more depth.',
        );

        $this->assertSame($rejected, $result);
    }

    public function test_resubmit_checks_policy_then_delegates_to_page_service(): void
    {
        $page = $this->page([
            'status' => PageStatus::REJECTED->value,
            'resubmission_count' => 1,
        ]);

        $resubmitted = $this->page([
            'status' => PageStatus::WAITING_APPROVAL->value,
            'resubmission_count' => 2,
        ]);

        $site = Mockery::mock(Site::class);

        $this->pageService
            ->shouldReceive('findPage')
            ->once()
            ->with(1)
            ->andReturn($page);

        $this->siteRepository
            ->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($site);

        $this->policy
            ->shouldReceive('canSubmitForReview')
            ->once()
            ->with(7, $site)
            ->andReturn(true);

        $this->pageService
            ->shouldReceive('resubmitPageForReview')
            ->once()
            ->with(1, 7)
            ->andReturn($resubmitted);

        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->with(
                1,
                7,
                ActivityEventType::ArticleUpdated,
                [
                    'page_id' => 1,
                    'action' => 'resubmitted',
                    'resubmission_count' => 2,
                ],
            );

        $this->queueService
            ->shouldReceive('enqueueForSubmission')
            ->once()
            ->with($resubmitted, 7, true);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(
                static fn(object $event): bool =>
                    $event instanceof ArticleSubmittedForReviewEvent
                    && $event->contributorId === 7
            );

        $result = $this->service->resubmit(1, 7);

        $this->assertSame($resubmitted, $result);
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
