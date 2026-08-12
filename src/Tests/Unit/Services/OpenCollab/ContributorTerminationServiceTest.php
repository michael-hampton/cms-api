<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutAuditAction;
use App\Enums\OpenCollab\PayoutStatus;
use App\Enums\Pages\PageStatus;
use App\Events\OpenCollab\ContributorAccountClosedEvent;
use App\Services\Authorization\AccessRevocationResult;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Collection;
use App\Framework\Support\Logger;
use App\Models\Page;
use App\Models\Payout;
use App\Models\User;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\OpenCollab\PayoutAuditRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\OpenCollabAuthorisationInterface;
use App\Services\OpenCollab\ContributorTerminationService;
use App\Services\User\UserLifecycleServiceInterface;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class ContributorTerminationServiceTest extends UnitTestCase
{
    private ContributorTerminationService $service;
    private MockInterface $userLifecycle;
    private MockInterface $authorisation;
    private MockInterface $payoutRepository;
    private MockInterface $payoutAuditRepository;
    private MockInterface $pageRepository;
    private MockInterface $eventDispatcher;
    private MockInterface $databaseMock;
    private MockInterface $logger;

    public function test_close_deactivates_account_when_no_other_site_access(): void
    {
        $user = $this->makeContributor();

        $this->userLifecycle->shouldReceive('findById')->andReturn($user);
        $this->authorisation->shouldReceive('revokeContributorAccess')->once()->andReturn(new AccessRevocationResult(true));
        $this->authorisation->shouldReceive('hasOtherContributorAccess')->with(7, 1)->andReturn(false);
        $this->userLifecycle->shouldReceive('deactivateContributor')
            ->with(7, 99, 'Violation of terms')
            ->once();
        $this->payoutRepository->shouldReceive('inFlightForContributor')->with(7, 1)->andReturn(new Collection([]));
        $this->pageRepository->shouldReceive('archiveUnpublishedContributorPages')->with(7, 1)->once()->andReturn(0);
        $this->logger->shouldReceive('info')->once();
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        $this->service->close(7, 1, 99, 'Violation of terms');
        $this->assertTrue(true);
    }

    public function test_close_does_not_deactivate_account_globally_when_user_has_other_site_access(): void
    {
        $user = $this->makeContributor();

        $this->userLifecycle->shouldReceive('findById')->andReturn($user);
        $this->authorisation->shouldReceive('revokeContributorAccess')->once()->andReturn(new AccessRevocationResult(true));
        // User still has access to another site — must NOT deactivate globally.
        $this->authorisation->shouldReceive('hasOtherContributorAccess')->with(7, 1)->andReturn(true);
        $this->userLifecycle->shouldNotReceive('deactivateContributor');
        $this->payoutRepository->shouldReceive('inFlightForContributor')->with(7, 1)->andReturn(new Collection([]));
        $this->pageRepository->shouldReceive('archiveUnpublishedContributorPages')->with(7, 1)->once()->andReturn(0);
        $this->logger->shouldReceive('info')->once();
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        $this->service->close(7, 1, 99, 'Violation of terms');
        $this->assertTrue(true);
    }

    public function test_close_revokes_site_access(): void
    {
        $user = $this->makeContributor();

        $this->userLifecycle->shouldReceive('findById')->andReturn($user);
        $this->authorisation->shouldReceive('revokeContributorAccess')->once()->andReturn(new AccessRevocationResult(true));
        $this->authorisation->shouldReceive('hasOtherContributorAccess')->andReturn(false);
        $this->userLifecycle->shouldReceive('deactivateContributor');
        $this->payoutRepository->shouldReceive('inFlightForContributor')->with(7, 1)->andReturn(new Collection([]));
        $this->pageRepository->shouldReceive('archiveUnpublishedContributorPages')->with(7, 1)->once()->andReturn(0);
        $this->logger->shouldReceive('info')->once();
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        $this->service->close(7, 1, 99, 'reason');
        $this->assertTrue(true);
    }

    public function test_close_cancels_pending_payouts_with_audit_entry(): void
    {
        $user = $this->makeContributor();
        $pendingPayout = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Pending->value]);
        $approvedPayout = $this->makePayout(['id' => 6, 'status' => PayoutStatus::Approved->value]);

        $this->userLifecycle->shouldReceive('findById')->andReturn($user);
        $this->authorisation->shouldReceive('revokeContributorAccess')->andReturn(new AccessRevocationResult(true));
        $this->authorisation->shouldReceive('hasOtherContributorAccess')->andReturn(false);
        $this->userLifecycle->shouldReceive('deactivateContributor');

        $this->payoutRepository->shouldReceive('inFlightForContributor')
            ->with(7, 1)
            ->andReturn(new Collection([$pendingPayout, $approvedPayout]));

        $this->payoutRepository->shouldReceive('update')
            ->with(5, Mockery::on(fn($d) => $d['status'] === PayoutStatus::Rejected->value))
            ->once();
        $this->payoutRepository->shouldReceive('update')
            ->with(6, Mockery::on(fn($d) => $d['status'] === PayoutStatus::Rejected->value))
            ->once();

        // Audit entry must be written for each cancelled payout.
        $this->payoutAuditRepository->shouldReceive('log')
            ->with(5, PayoutAuditAction::Declined, 99, Mockery::type('string'))
            ->once();
        $this->payoutAuditRepository->shouldReceive('log')
            ->with(6, PayoutAuditAction::Declined, 99, Mockery::type('string'))
            ->once();

        $this->pageRepository->shouldReceive('archiveUnpublishedContributorPages')->with(7, 1)->once()->andReturn(0);
        $this->logger->shouldReceive('info');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->service->close(7, 1, 99, 'reason');
        $this->assertTrue(true);
    }

    public function test_close_cancels_payouts_with_reason_that_includes_closure_reason(): void
    {
        $user = $this->makeContributor();
        $pendingPayout = $this->makePayout(['id' => 5, 'status' => PayoutStatus::Pending->value]);

        $this->userLifecycle->shouldReceive('findById')->andReturn($user);
        $this->authorisation->shouldReceive('revokeContributorAccess')->andReturn(new AccessRevocationResult(true));
        $this->authorisation->shouldReceive('hasOtherContributorAccess')->andReturn(false);
        $this->userLifecycle->shouldReceive('deactivateContributor');
        $this->payoutRepository->shouldReceive('inFlightForContributor')
            ->with(7, 1)
            ->andReturn(new Collection([$pendingPayout]));

        $this->payoutRepository->shouldReceive('update')
            ->once()
            ->withArgs(function ($id, array $data): bool {
                return str_contains($data['rejection_reason'], 'Account closed:')
                    && str_contains($data['rejection_reason'], 'Fraud detected');
            });
        $this->payoutAuditRepository->shouldReceive('log');

        $this->pageRepository->shouldReceive('archiveUnpublishedContributorPages')->with(7, 1)->once()->andReturn(0);
        $this->logger->shouldReceive('info');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->service->close(7, 1, 99, 'Fraud detected');
        $this->assertTrue(true);
    }

    public function test_close_archives_draft_and_on_hold_pages(): void
    {
        $user = $this->makeContributor();
        $draftPage = $this->makePage(['id' => 10, 'status' => PageStatus::DRAFT->value]);
        $onHoldPage = $this->makePage(['id' => 11, 'status' => PageStatus::ON_HOLD->value]);

        $this->userLifecycle->shouldReceive('findById')->andReturn($user);
        $this->authorisation->shouldReceive('revokeContributorAccess')->andReturn(new AccessRevocationResult(true));
        $this->authorisation->shouldReceive('hasOtherContributorAccess')->andReturn(false);
        $this->userLifecycle->shouldReceive('deactivateContributor');
        $this->payoutRepository->shouldReceive('inFlightForContributor')->with(7, 1)->andReturn(new Collection([]));

        $this->pageRepository->shouldReceive('archiveUnpublishedContributorPages')
            ->with(7, 1)
            ->once()
            ->andReturn(2);

        $this->logger->shouldReceive('info');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->service->close(7, 1, 99, 'reason');
        $this->assertTrue(true);
    }

    public function test_close_does_not_archive_published_pages(): void
    {
        $user = $this->makeContributor();
        $publishedPage = $this->makePage(['id' => 12, 'status' => PageStatus::PUBLISHED->value]);

        $this->userLifecycle->shouldReceive('findById')->andReturn($user);
        $this->authorisation->shouldReceive('revokeContributorAccess')->andReturn(new AccessRevocationResult(true));
        $this->authorisation->shouldReceive('hasOtherContributorAccess')->andReturn(false);
        $this->userLifecycle->shouldReceive('deactivateContributor');
        $this->payoutRepository->shouldReceive('inFlightForContributor')->with(7, 1)->andReturn(new Collection([]));

        $this->pageRepository->shouldReceive('archiveUnpublishedContributorPages')
            ->with(7, 1)
            ->once()
            ->andReturn(0);

        $this->logger->shouldReceive('info');
        $this->eventDispatcher->shouldReceive('dispatch');

        $this->service->close(7, 1, 99, 'reason');
        $this->assertTrue(true);
    }

    public function test_close_throws_when_user_not_found(): void
    {
        $this->userLifecycle->shouldReceive('findById')->andReturn(null);
        $this->userLifecycle->shouldNotReceive('deactivateContributor');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not found/i');

        $this->service->close(999, 1, 99, 'reason');
    }

    public function test_close_throws_when_user_is_not_contributor(): void
    {
        $user = new User(['id' => 7, 'is_contributor' => false]);
        $user->exists = true;

        $this->userLifecycle->shouldReceive('findById')->andReturn($user);
        $this->userLifecycle->shouldNotReceive('deactivateContributor');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a contributor/i');

        $this->service->close(7, 1, 99, 'reason');
    }

    public function test_close_dispatches_account_closed_event_with_fresh_user(): void
    {
        $staleUser = $this->makeContributor(); // is_active still true at load time
        $freshUser = $this->makeContributor(); // simulates the reloaded model

        // First find() returns stale, second returns fresh (post-update reload).
        $this->userLifecycle->shouldReceive('findById')
            ->with(7)
            ->andReturn($staleUser, $freshUser);

        $this->authorisation->shouldReceive('revokeContributorAccess')->andReturn(new AccessRevocationResult(true));
        $this->authorisation->shouldReceive('hasOtherContributorAccess')->andReturn(false);
        $this->userLifecycle->shouldReceive('deactivateContributor');
        $this->payoutRepository->shouldReceive('inFlightForContributor')->with(7, 1)->andReturn(new Collection([]));
        $this->pageRepository->shouldReceive('archiveUnpublishedContributorPages')->with(7, 1)->once()->andReturn(0);
        $this->logger->shouldReceive('info');

        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($event) use ($freshUser): bool {
                return $event instanceof ContributorAccountClosedEvent
                    && $event->contributor === $freshUser // must be the reloaded model
                    && $event->adminId === 99
                    && $event->reason === 'Terms violation';
            });

        $this->service->close(7, 1, 99, 'Terms violation');
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

    private function makePayout(array $attributes = []): Payout
    {
        $p = new Payout(array_merge(['id' => 1, 'user_id' => 7, 'amount' => 5000, 'status' => PayoutStatus::Pending->value], $attributes));
        $p->exists = true;
        return $p;
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


    protected function setUp(): void
    {

        $this->userLifecycle = Mockery::mock(UserLifecycleServiceInterface::class);
        $this->authorisation = Mockery::mock(OpenCollabAuthorisationInterface::class);
        $this->authorisation
            ->shouldReceive('revokeContributorAccess')
            ->andReturn(new AccessRevocationResult(true))
            ->byDefault();
        $this->payoutRepository = Mockery::mock(PayoutRepository::class);
        $this->payoutAuditRepository = Mockery::mock(PayoutAuditRepository::class);
        $this->pageRepository = Mockery::mock(PageRepository::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->service = new ContributorTerminationService(
            $this->userLifecycle,
            $this->authorisation,
            $this->payoutRepository,
            $this->payoutAuditRepository,
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
