<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Events\OpenCollab\PagePublishedByContributorEvent;
use App\Exceptions\OpenCollab\UnauthorisedPageAccessException;
use App\Framework\Events\EventDispatcher;
use App\Models\Author;
use App\Models\Page;
use App\Models\User;
use App\Repositories\Cms\AuthorRepository;
use App\Repositories\Cms\Pages\PageAuthorRepository;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Services\Cms\Pages\PageService;
use App\Services\OpenCollab\ContributorPageService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class ContributorPageServiceTest extends FunctionalTestCase
{
    private ContributorPageService $service;
    private MockInterface $pageService;
    private MockInterface $pageRepository;
    private MockInterface $eventDispatcher;
    private MockInterface $activityRepository;
    private MockInterface $authorRepository;
    private MockInterface $pageAuthorRepository;
    private MockInterface $userRepository;

    public function test_create_injects_contributor_id_and_delegates_to_page_service(): void
    {
        $requestData = ['site_id' => 1, 'forms' => ['main' => ['title' => 'My Article']]];
        $createdPage = $this->makePage(['contributor_id' => 7, 'status' => 'draft']);

        $this->pageService
            ->shouldReceive('createPageWithAllData')
            ->once()
            ->withArgs(function (array $data, int $siteId): bool {
                return $data['contributor_id'] === 7
                    && $data['is_public_contribution'] === true
                    && $siteId === 1;
            })
            ->andReturn($createdPage);

        $page = $this->service->createPage($requestData, 7, 1);

        $this->assertSame($createdPage, $page);
    }

    public function test_create_resolves_contributor_via_user_repository_not_static_call(): void
    {
        $createdPage = $this->makePage(['id' => 10, 'contributor_id' => 7]);
        $user = $this->makeUser(['id' => 7, 'email' => 'contrib@example.com', 'name' => 'Jane']);
        $author = $this->makeAuthor(['id' => 3, 'email' => 'contrib@example.com']);

        $this->pageService->shouldReceive('createPageWithAllData')->andReturn($createdPage);

        // Must use userRepository->find(), never a static User::find()
        $this->userRepository->shouldReceive('find')->with(7)->once()->andReturn($user);
        $this->authorRepository->shouldReceive('findByEmail')->with('contrib@example.com')->andReturn($author);
        $this->pageAuthorRepository->shouldReceive('link')->with(10, 3)->once();

        $this->service->createPage(['site_id' => 1, 'forms' => ['main' => ['title' => 'T']]], 7, 1);
        $this->assertTrue(true);
    }

    public function test_create_creates_guest_author_when_none_exists_for_email(): void
    {
        $createdPage = $this->makePage(['id' => 10, 'contributor_id' => 7]);
        $user = $this->makeUser(['id' => 7, 'email' => 'new@example.com', 'name' => 'New User']);
        $newAuthor = $this->makeAuthor(['id' => 99, 'status' => 'guest']);

        $this->pageService->shouldReceive('createPageWithAllData')->andReturn($createdPage);
        $this->userRepository->shouldReceive('find')->andReturn($user);
        $this->authorRepository->shouldReceive('findByEmail')->andReturn(null);

        // Slug uniqueness check via repository, not Author::where()
        $this->authorRepository->shouldReceive('isSlugTaken')->andReturn(false);

        $this->authorRepository->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                return $data['is_guest'] === true
                    && str_starts_with($data['slug'], 'guest-')
                    && $data['email'] === 'new@example.com';
            })
            ->andReturn($newAuthor);

        $this->pageAuthorRepository->shouldReceive('link')->with(10, 99)->once();

        $this->service->createPage(['site_id' => 1, 'forms' => ['main' => ['title' => 'T']]], 7, 1);
        $this->assertTrue(true);
    }

    public function test_create_reuses_existing_author_and_does_not_create_new_one(): void
    {
        $createdPage = $this->makePage(['id' => 10, 'contributor_id' => 7]);
        $user = $this->makeUser(['id' => 7, 'email' => 'exists@example.com']);
        $existingAuthor = $this->makeAuthor(['id' => 5, 'status' => 'active']);

        $this->pageService->shouldReceive('createPageWithAllData')->andReturn($createdPage);
        $this->userRepository->shouldReceive('find')->andReturn($user);
        $this->authorRepository->shouldReceive('findByEmail')->andReturn($existingAuthor);
        $this->authorRepository->shouldNotReceive('create');
        $this->authorRepository->shouldNotReceive('isSlugTaken');
        $this->pageAuthorRepository->shouldReceive('link')->with(10, 5)->once();

        $this->service->createPage(['site_id' => 1, 'forms' => ['main' => ['title' => 'T']]], 7, 1);
        $this->assertTrue(true);
    }

    public function test_create_reuses_existing_author_when_one_exists_for_email(): void
    {
        $createdPage = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'draft']);
        $existingAuthor = $this->makeAuthor(['id' => 5, 'status' => 'active']);

        $this->pageService->shouldReceive('createPageWithAllData')->andReturn($createdPage);

        // Author already exists — must NOT create a new one
        $this->authorRepository->shouldReceive('findByEmail')->andReturn($existingAuthor);
        $this->authorRepository->shouldNotReceive('create');

        $this->service->createPage(['site_id' => 1, 'forms' => ['main' => ['title' => 'T']]], 7, 1);
        $this->assertTrue(true);
    }

    public function test_create_does_not_throw_when_guest_author_attachment_fails(): void
    {
        $createdPage = $this->makePage(['contributor_id' => 7, 'status' => 'draft']);

        $this->pageService->shouldReceive('createPageWithAllData')->andReturn($createdPage);

        // Author repository throws — must NOT propagate
        $this->authorRepository->shouldReceive('findByEmail')
            ->andThrow(new \RuntimeException('DB error'));

        // Should not throw — failure is non-critical
        $page = $this->service->createPage(['site_id' => 1, 'forms' => ['main' => ['title' => 'T']]], 7, 1);

        $this->assertSame($createdPage, $page);
    }

    private function makePage(array $attributes = []): Page
    {
        $defaults = [
            'id' => 1,
            'site_id' => 1,
            'title' => 'Test Article',
            'status' => 'draft',
            'contributor_id' => 7,
        ];

        $page = new Page(array_merge($defaults, $attributes));
        $page->exists = true;
        return $page;
    }

    // -------------------------------------------------------------------------
    // createPage()
    // -------------------------------------------------------------------------

    public function test_update_succeeds_when_contributor_owns_page(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'draft']);
        $updatedPage = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'draft']);

        $this->pageRepository
            ->shouldReceive('find')
            ->with(10)
            ->once()
            ->andReturn($existing);

        $this->pageService
            ->shouldReceive('updatePageWithAllData')
            ->once()
            ->andReturn($updatedPage);

        $this->eventDispatcher->shouldNotReceive('dispatch');

        $result = $this->service->updatePage(10, ['site_id' => 1], 7, 1);

        $this->assertSame($updatedPage, $result);
    }

    // -------------------------------------------------------------------------
    // updatePage()
    // -------------------------------------------------------------------------

    public function test_update_throws_when_contributor_does_not_own_page(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 99]); // owned by someone else

        $this->pageRepository->shouldReceive('find')->andReturn($existing);

        $this->pageService->shouldNotReceive('updatePageWithAllData');
        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->updatePage(10, ['site_id' => 1], 7, 1); // contributor_id = 7
    }

    public function test_update_throws_when_page_not_found(): void
    {
        $this->pageRepository->shouldReceive('find')->andReturn(null);

        $this->pageService->shouldNotReceive('updatePageWithAllData');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->updatePage(999, ['site_id' => 1], 7, 1);
    }

    public function test_emits_published_event_when_page_transitions_from_draft_to_published(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'draft']);
        $updatedPage = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'published']);

        $this->pageRepository->shouldReceive('find')->andReturn($existing);
        $this->pageService->shouldReceive('updatePageWithAllData')->andReturn($updatedPage);

        $this->eventDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($event): bool {
                return $event instanceof PagePublishedByContributorEvent
                    && $event->contributorId === 7;
            });

        $this->service->updatePage(10, ['site_id' => 1], 7, 1);
        $this->assertTrue(true);
    }

    public function test_does_not_emit_event_when_already_published_page_is_updated(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'published']);
        $updatedPage = $this->makePage(['id' => 10, 'contributor_id' => 7, 'status' => 'published']);

        $this->pageRepository->shouldReceive('find')->andReturn($existing);
        $this->pageService->shouldReceive('updatePageWithAllData')->andReturn($updatedPage);

        $this->eventDispatcher->shouldNotReceive('dispatch');

        $this->service->updatePage(10, ['site_id' => 1], 7, 1);
        $this->assertTrue(true);
    }

    public function test_delete_succeeds_when_contributor_owns_page(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 7]);

        $this->pageRepository->shouldReceive('find')->with(10)->andReturn($existing);

        $this->pageRepository
            ->shouldReceive('delete')
            ->with(10)
            ->once();

        $this->service->deletePage(10, 7);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // deletePage()
    // -------------------------------------------------------------------------

    public function test_delete_throws_when_contributor_does_not_own_page(): void
    {
        $existing = $this->makePage(['id' => 10, 'contributor_id' => 99]);

        $this->pageRepository->shouldReceive('find')->andReturn($existing);
        $this->pageRepository->shouldNotReceive('delete');

        $this->expectException(UnauthorisedPageAccessException::class);

        $this->service->deletePage(10, 7);
        $this->assertTrue(true);
    }

    private function makeAuthor(array $attributes = []): Author
    {
        $defaults = [
            'id' => 1,
            'name' => 'Test Author',
            'email' => 'contributor@example.com',
            'slug' => 'guest-test-author',
            'status' => 'guest',
        ];
        $author = new Author(array_merge($defaults, $attributes));
        $author->exists = true;
        return $author;
    }

    private function makeUser(array $attributes = []): User
    {
        $defaults = [
            'id' => 7,
            'name' => 'Test User',
            'email' => 'contributor@example.com',
        ];
        $user = new User(array_merge($defaults, $attributes));
        $user->exists = true;
        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->pageService = Mockery::mock(PageService::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->activityRepository = Mockery::mock(ActivityRepository::class);
        $this->authorRepository = Mockery::mock(AuthorRepository::class);
        $this->pageAuthorRepository = Mockery::mock(PageAuthorRepository::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);


        $this->service = new ContributorPageService(
            $this->pageService,
            $this->pageRepository,
            $this->eventDispatcher,
            $this->activityRepository,
            $this->authorRepository,
            $this->pageAuthorRepository,
            $this->userRepository,
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}