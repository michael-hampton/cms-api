<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Page;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\Pages\PageAuthorRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\Cms\Pages\PageService;
use App\Services\OpenCollab\ArticleApprovalService;
use App\Services\OpenCollab\ContributorPageService;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class ContributorPageServiceTest extends UnitTestCase
{
    private ContributorPageService $service;
    private MockInterface $pageService;
    private MockInterface $pageRepository;
    private MockInterface $articleApprovalService;
    private MockInterface $activityRepository;
    private MockInterface $authorRepository;
    private MockInterface $pageAuthorRepository;
    private MockInterface $userRepository;
    private MockInterface $database;
    private MockInterface $logger;

    public function test_create_saves_normal_contributor_article_as_draft(): void
    {
        $created = $this->makePage(['status' => 'draft']);

        $this->pageService
            ->shouldReceive('createPageWithAllData')
            ->once()
            ->withArgs(fn(array $data, int $siteId): bool =>
                $siteId === 1
                && $data['contributor_id'] === 7
                && $data['is_public_contribution'] === true
                && $data['suppress_workflow_notifications'] === true
                && $data['status'] === 'draft'
                && $data['forms']['meta']['status'] === 'draft'
            )
            ->andReturn($created);

        $this->articleApprovalService->shouldNotReceive('submitForReview');

        $result = $this->service->createPage([
            'forms' => [
                'main' => ['title' => 'Draft article'],
                'meta' => ['status' => 'draft'],
            ],
        ], 7, 1);

        $this->assertSame($created, $result);
    }

    public function test_create_requesting_approval_saves_draft_then_uses_article_approval_service(): void
    {
        $created = $this->makePage(['id' => 15, 'status' => 'draft']);
        $submitted = $this->makePage(['id' => 15, 'status' => 'waiting_approval']);

        $this->pageService
            ->shouldReceive('createPageWithAllData')
            ->once()
            ->withArgs(fn(array $data, int $siteId): bool =>
                $siteId === 1
                && $data['status'] === 'draft'
                && $data['forms']['meta']['status'] === 'draft'
            )
            ->andReturn($created);

        $this->articleApprovalService
            ->shouldReceive('submitForReview')
            ->once()
            ->with(15, 7)
            ->andReturn($submitted);

        $result = $this->service->createPage([
            'forms' => [
                'main' => ['title' => 'Submit me'],
                'meta' => ['status' => 'published'],
            ],
        ], 7, 1);

        $this->assertSame($submitted, $result);
        $this->assertSame('waiting_approval', $result->status);
    }

    public function test_explicit_request_approval_flag_uses_article_approval_service(): void
    {
        $created = $this->makePage(['id' => 20, 'status' => 'draft']);
        $submitted = $this->makePage(['id' => 20, 'status' => 'waiting_approval']);

        $this->pageService
            ->shouldReceive('createPageWithAllData')
            ->once()
            ->withArgs(fn(array $data): bool => $data['status'] === 'draft')
            ->andReturn($created);

        $this->articleApprovalService
            ->shouldReceive('submitForReview')
            ->once()
            ->with(20, 7)
            ->andReturn($submitted);

        $result = $this->service->createPage([
            'request_approval' => true,
            'forms' => [
                'main' => ['title' => 'Submit me'],
                'meta' => ['status' => 'draft'],
            ],
        ], 7, 1);

        $this->assertSame($submitted, $result);
    }

    public function test_update_requesting_approval_saves_draft_then_submits_for_review(): void
    {
        $existing = $this->makePage(['id' => 10, 'status' => 'draft']);
        $saved = $this->makePage(['id' => 10, 'status' => 'draft']);
        $submitted = $this->makePage(['id' => 10, 'status' => 'waiting_approval']);

        $this->pageRepository
            ->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($existing);

        $this->pageService
            ->shouldReceive('updatePageWithAllData')
            ->once()
            ->withArgs(fn(int $id, array $data, int $siteId, Page $page): bool =>
                $id === 10
                && $siteId === 1
                && $page === $existing
                && $data['status'] === 'draft'
                && $data['forms']['meta']['status'] === 'draft'
            )
            ->andReturn($saved);

        $this->articleApprovalService
            ->shouldReceive('submitForReview')
            ->once()
            ->with(10, 7)
            ->andReturn($submitted);

        $result = $this->service->updatePage(10, [
            'forms' => [
                'main' => ['title' => 'Updated'],
                'meta' => ['status' => 'published'],
            ],
        ], 7, 1);

        $this->assertSame($submitted, $result);
    }

    public function test_rejected_article_requesting_approval_uses_resubmit(): void
    {
        $existing = $this->makePage(['id' => 10, 'status' => 'rejected']);
        $saved = $this->makePage(['id' => 10, 'status' => 'rejected']);
        $submitted = $this->makePage(['id' => 10, 'status' => 'waiting_approval']);

        $this->pageRepository->shouldReceive('find')->with(10)->andReturn($existing);

        $this->pageService
            ->shouldReceive('updatePageWithAllData')
            ->once()
            ->withArgs(fn(int $id, array $data): bool =>
                $id === 10
                && $data['status'] === 'rejected'
                && $data['forms']['meta']['status'] === 'rejected'
            )
            ->andReturn($saved);

        $this->articleApprovalService->shouldNotReceive('submitForReview');
        $this->articleApprovalService
            ->shouldReceive('resubmit')
            ->once()
            ->with(10, 7)
            ->andReturn($submitted);

        $result = $this->service->updatePage(10, [
            'forms' => ['meta' => ['status' => 'published']],
        ], 7, 1);

        $this->assertSame($submitted, $result);
    }

    public function test_on_hold_article_requesting_approval_uses_resubmit(): void
    {
        $existing = $this->makePage(['id' => 10, 'status' => 'on_hold']);
        $saved = $this->makePage(['id' => 10, 'status' => 'on_hold']);
        $submitted = $this->makePage(['id' => 10, 'status' => 'waiting_approval']);

        $this->pageRepository->shouldReceive('find')->with(10)->andReturn($existing);
        $this->pageService->shouldReceive('updatePageWithAllData')->andReturn($saved);
        $this->articleApprovalService
            ->shouldReceive('resubmit')
            ->once()
            ->with(10, 7)
            ->andReturn($submitted);

        $result = $this->service->updatePage(10, [
            'request_approval' => true,
            'forms' => ['meta' => ['status' => 'draft']],
        ], 7, 1);

        $this->assertSame($submitted, $result);
    }

    public function test_update_without_approval_does_not_call_article_approval_service(): void
    {
        $existing = $this->makePage(['id' => 10, 'status' => 'draft']);
        $updated = $this->makePage(['id' => 10, 'status' => 'draft']);

        $this->pageRepository->shouldReceive('find')->with(10)->andReturn($existing);
        $this->pageService->shouldReceive('updatePageWithAllData')->once()->andReturn($updated);
        $this->articleApprovalService->shouldNotReceive('submitForReview');
        $this->articleApprovalService->shouldNotReceive('resubmit');

        $result = $this->service->updatePage(10, [
            'forms' => ['meta' => ['status' => 'draft']],
        ], 7, 1);

        $this->assertSame($updated, $result);
    }

    public function test_update_throws_when_contributor_does_not_own_page(): void
    {
        $this->pageRepository
            ->shouldReceive('find')
            ->with(10)
            ->andReturn($this->makePage(['id' => 10, 'contributor_id' => 99]));

        $this->pageService->shouldNotReceive('updatePageWithAllData');
        $this->articleApprovalService->shouldNotReceive('submitForReview');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->updatePage(10, [], 7, 1);
    }

    public function test_delete_succeeds_when_contributor_owns_page(): void
    {
        $existing = $this->makePage(['id' => 10]);

        $this->pageRepository->shouldReceive('find')->with(10)->andReturn($existing);
        $this->pageRepository->shouldReceive('delete')->once()->with(10);

        $this->service->deletePage(10, 7);
        $this->addToAssertionCount(1);
    }

    public function test_delete_throws_when_contributor_does_not_own_page(): void
    {
        $this->pageRepository
            ->shouldReceive('find')
            ->andReturn($this->makePage(['id' => 10, 'contributor_id' => 99]));
        $this->pageRepository->shouldNotReceive('delete');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->deletePage(10, 7);
    }

    public function test_create_wraps_its_writes_in_a_transaction(): void
    {
        $created = $this->makePage(['status' => 'draft']);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static fn(callable $callback) => $callback());

        $this->pageService->shouldReceive('createPageWithAllData')->once()->andReturn($created);

        $result = $this->service->createPage([
            'forms' => [
                'main' => ['title' => 'Draft article'],
                'meta' => ['status' => 'draft'],
            ],
        ], 7, 1);

        $this->assertSame($created, $result);
    }

    public function test_create_does_not_submit_for_review_when_the_page_write_fails(): void
    {
        $this->pageService
            ->shouldReceive('createPageWithAllData')
            ->once()
            ->andThrow(new \RuntimeException('DB unavailable'));

        $this->articleApprovalService->shouldNotReceive('submitForReview');
        $this->activityRepository->shouldNotReceive('record');

        $this->expectException(\RuntimeException::class);

        $this->service->createPage([
            'request_approval' => true,
            'forms' => [
                'main' => ['title' => 'Submit me'],
                'meta' => ['status' => 'draft'],
            ],
        ], 7, 1);
    }

    public function test_create_logs_but_does_not_fail_when_activity_recording_throws(): void
    {
        $created = $this->makePage(['status' => 'draft']);

        $this->pageService->shouldReceive('createPageWithAllData')->once()->andReturn($created);
        $this->activityRepository
            ->shouldReceive('record')
            ->once()
            ->andThrow(new \RuntimeException('activity log unavailable'));

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with('Failed to record contributor page activity.', Mockery::on(
                fn(array $context): bool => $context['site_id'] === 1 && $context['user_id'] === 7
            ));

        $result = $this->service->createPage([
            'forms' => [
                'main' => ['title' => 'Draft article'],
                'meta' => ['status' => 'draft'],
            ],
        ], 7, 1);

        $this->assertSame($created, $result);
    }

    public function test_create_logs_but_does_not_fail_when_guest_author_link_partially_fails(): void
    {
        $created = $this->makePage(['status' => 'draft']);
        $user = (new \ReflectionClass(\App\Models\User::class))->newInstanceWithoutConstructor();
        $user->id = 7;
        $user->email = 'contributor@example.com';
        $user->name = 'Contributor User';

        $this->pageService->shouldReceive('createPageWithAllData')->once()->andReturn($created);
        $this->userRepository->shouldReceive('find')->once()->with(7)->andReturn($user);
        $this->authorRepository->shouldReceive('findByEmail')->once()->with('contributor@example.com')->andReturnNull();
        $this->authorRepository->shouldReceive('isSlugTaken')->once()->andReturnFalse();
        $this->authorRepository->shouldReceive('create')->once()->andThrow(new \RuntimeException('author write failed'));

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with('Failed to attach guest author to contributor page.', Mockery::on(
                fn(array $context): bool => $context['contributor_id'] === 7
            ));

        $this->pageAuthorRepository->shouldNotReceive('link');

        $result = $this->service->createPage([
            'forms' => [
                'main' => ['title' => 'Draft article'],
                'meta' => ['status' => 'draft'],
            ],
        ], 7, 1);

        $this->assertSame($created, $result);
    }

    public function test_delete_wraps_its_writes_in_a_transaction(): void
    {
        $existing = $this->makePage(['id' => 10]);

        $this->pageRepository->shouldReceive('find')->with(10)->andReturn($existing);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static fn(callable $callback) => $callback());

        $this->pageRepository->shouldReceive('delete')->once()->with(10);

        $this->service->deletePage(10, 7);
        $this->addToAssertionCount(1);
    }

    private function makePage(array $attributes = []): Page
    {
        $page = new Page(array_merge([
            'id' => 1,
            'site_id' => 1,
            'title' => 'Test Article',
            'status' => 'draft',
            'contributor_id' => 7,
        ], $attributes));
        $page->exists = true;

        return $page;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageService = Mockery::mock(PageService::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->articleApprovalService = Mockery::mock(ArticleApprovalService::class);
        $this->activityRepository = Mockery::mock(ActivityRepository::class);
        $this->authorRepository = Mockery::mock(AuthorRepository::class);
        $this->pageAuthorRepository = Mockery::mock(PageAuthorRepository::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->database = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->activityRepository
            ->shouldReceive('record')
            ->andReturnNull()
            ->byDefault();
        $this->userRepository
            ->shouldReceive('find')
            ->andReturnNull()
            ->byDefault();
        $this->database
            ->shouldReceive('transaction')
            ->byDefault()
            ->andReturnUsing(static fn(callable $callback) => $callback());
        $this->logger->shouldIgnoreMissing();

        $this->service = new ContributorPageService(
            $this->pageService,
            $this->pageRepository,
            $this->articleApprovalService,
            $this->activityRepository,
            $this->authorRepository,
            $this->pageAuthorRepository,
            $this->userRepository,
            $this->database,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
