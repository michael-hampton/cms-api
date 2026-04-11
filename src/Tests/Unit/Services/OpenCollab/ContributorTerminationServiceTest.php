<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Enums\Pages\PageStatus;
use App\Events\OpenCollab\ContributorAccountClosedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Page;
use App\Models\Payout;
use App\Models\User;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\ContributorTerminationService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class ContributorTerminationServiceTest extends FunctionalTestCase
{
    private ContributorTerminationService $service;
    private MockInterface $userRepository;
    private MockInterface $userSiteRepository;
    private MockInterface $payoutRepository;
    private MockInterface $pageRepository;
    private MockInterface $eventDispatcher;
    private MockInterface $databaseMock;
    private MockInterface $logger;

    public function test_close_deactivates_account_and_revokes_site_access(): void
    {
        $user = $this->makeContributor();

        $this->userRepository->shouldReceive('find')->with(7)->andReturn($user);
        $this->userRepository->shouldReceive('update')
            ->with(7, ['is_active' => false])
            ->once();
        $this->userSiteRepository->shouldReceive('revoke')
            ->with(7, 1)
            ->once();
        $this->payoutRepository->shouldReceive('forContributor')
            ->andReturn(new Collection([]));
        $this->pageRepository->shouldReceive('query')->andReturn(
            $this->makeEmptyQueryMock()
        );
        $this->logger->shouldReceive('info')->once();
        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($e) => $e instanceof ContributorAccountClosedEvent && $e->adminId === 99);

        $this->service->close(7, 1, 99, 'Violation of terms');
        $this->assertTrue(true);
    }

    private function makeContributor(): User
    {
        $user = new User(['id' => 7, 'name' => 'Test Contributor', 'email' => 't@example.com', 'is_contributor' => true]);
        $user->exists = true;
        return $user;
    }

    private function makeEmptyQueryMock(): MockInterface
    {
        return $this->makeQueryMockReturning([]);
    }

    private function makeQueryMockReturning(array $pages): MockInterface
    {
        $query = Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('whereIn')->andReturnSelf();
        $query->shouldReceive('get')->andReturn(new Collection($pages));
        return $query;
    }

    public function test_close_cancels_pending_payouts(): void
    {
        $user = $this->makeContributor();
        $pendingPayout = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Pending->value]);
        $approvedPayout = $this->makePayout(['id' => 6, 'status' => PayoutStatus::Approved->value]);
        $paidPayout = $this->makePayout(['id' => 7, 'status' => PayoutStatus::Paid->value]);

        $this->userRepository->shouldReceive('find')->andReturn($user);
        $this->userRepository->shouldReceive('update');
        $this->userSiteRepository->shouldReceive('revoke');
        $this->payoutRepository->shouldReceive('forContributor')
            ->andReturn(new Collection([$pendingPayout, $approvedPayout, $paidPayout]));

        // Only pending and approved should be cancelled — paid should not be touched
        $this->payoutRepository->shouldReceive('update')
            ->with(5, Mockery::on(fn($d) => $d['status'] === PayoutStatus::Rejected->value))
            ->once();
        $this->payoutRepository->shouldReceive('update')
            ->with(6, Mockery::on(fn($d) => $d['status'] === PayoutStatus::Rejected->value))
            ->once();
        $this->payoutRepository->shouldNotReceive('update')
            ->with(7, Mockery::any());

        $this->pageRepository->shouldReceive('query')->andReturn($this->makeEmptyQueryMock());
        $this->logger->shouldReceive('info');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->service->close(7, 1, 99, 'reason');
        $this->assertTrue(true);
    }

    private function makePayout(array $attributes = []): Payout
    {
        $p = new Payout(array_merge(['id' => 1, 'user_id' => 7, 'amount' => 5000, 'status' => PayoutStatus::Pending->value], $attributes));
        $p->exists = true;
        return $p;
    }

    public function test_close_archives_draft_and_on_hold_pages(): void
    {
        $user = $this->makeContributor();
        $draftPage = $this->makePage(['id' => 10, 'status' => PageStatus::DRAFT->value]);
        $onHoldPage = $this->makePage(['id' => 11, 'status' => PageStatus::ON_HOLD->value]);

        $this->userRepository->shouldReceive('find')->andReturn($user);
        $this->userRepository->shouldReceive('update');
        $this->userSiteRepository->shouldReceive('revoke');
        $this->payoutRepository->shouldReceive('forContributor')->andReturn(new Collection([]));

        $queryMock = $this->makeQueryMockReturning([$draftPage, $onHoldPage]);
        $this->pageRepository->shouldReceive('query')->andReturn($queryMock);
        $this->pageRepository->shouldReceive('update')
            ->with(10, ['status' => PageStatus::ARCHIVED->value])
            ->once();
        $this->pageRepository->shouldReceive('update')
            ->with(11, ['status' => PageStatus::ARCHIVED->value])
            ->once();

        $this->logger->shouldReceive('info');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->service->close(7, 1, 99, 'reason');
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makePage(array $attributes = []): Page
    {
        $p = new Page(array_merge(['id' => 1, 'site_id' => 1, 'contributor_id' => 7, 'status' => PageStatus::DRAFT->value], $attributes));
        $p->exists = true;
        return $p;
    }

    public function test_close_does_not_archive_published_pages(): void
    {
        $user = $this->makeContributor();
        $publishedPage = $this->makePage(['id' => 12, 'status' => PageStatus::PUBLISHED->value]);

        $this->userRepository->shouldReceive('find')->andReturn($user);
        $this->userRepository->shouldReceive('update');
        $this->userSiteRepository->shouldReceive('revoke');
        $this->payoutRepository->shouldReceive('forContributor')->andReturn(new Collection([]));

        // Query only returns non-published pages per the archivable status filter
        $queryMock = $this->makeQueryMockReturning([]);
        $this->pageRepository->shouldReceive('query')->andReturn($queryMock);
        $this->pageRepository->shouldNotReceive('update')
            ->with(12, Mockery::any());

        $this->logger->shouldReceive('info');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->service->close(7, 1, 99, 'reason');
        $this->assertTrue(true);
    }

    public function test_close_throws_when_user_not_found(): void
    {
        $this->userRepository->shouldReceive('find')->andReturn(null);
        $this->userRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->service->close(999, 1, 99, 'reason');
    }

    public function test_close_throws_when_user_is_not_contributor(): void
    {
        $user = new User(['id' => 7, 'is_contributor' => false]);
        $user->exists = true;

        $this->userRepository->shouldReceive('find')->andReturn($user);
        $this->userRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a contributor/i');

        $this->service->close(7, 1, 99, 'reason');
    }

    public function test_close_dispatches_account_closed_event(): void
    {
        $user = $this->makeContributor();

        $this->userRepository->shouldReceive('find')->andReturn($user);
        $this->userRepository->shouldReceive('update');
        $this->userSiteRepository->shouldReceive('revoke');
        $this->payoutRepository->shouldReceive('forContributor')->andReturn(new Collection([]));
        $this->pageRepository->shouldReceive('query')->andReturn($this->makeEmptyQueryMock());
        $this->logger->shouldReceive('info');

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($event) use ($user): bool {
                return $event instanceof ContributorAccountClosedEvent
                    && $event->contributor->id === $user->id
                    && $event->adminId === 99
                    && $event->reason === 'Terms violation';
            });

        $this->service->close(7, 1, 99, 'Terms violation');
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->userSiteRepository = Mockery::mock(UserSiteRepository::class);
        $this->payoutRepository = Mockery::mock(PayoutRepository::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new ContributorTerminationService(
            $this->userRepository,
            $this->userSiteRepository,
            $this->payoutRepository,
            $this->pageRepository,
            $this->eventDispatcher,
            $this->databaseMock,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}