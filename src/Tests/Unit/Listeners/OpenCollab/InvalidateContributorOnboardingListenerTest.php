<?php

namespace App\Tests\Unit\Listeners\OpenCollab;

use App\Events\OpenCollab\ContractPublishedEvent;
use App\Events\OpenCollab\GuidelinesVersionBumpedEvent;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Listeners\OpenCollab\InvalidateContributorOnboardingListener;
use App\Models\Contract;
use App\Models\Guideline;
use App\Models\Site;
use App\Models\User;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\Notifications\OnboardingInvalidatedNotification;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class InvalidateContributorOnboardingListenerTest extends FunctionalTestCase
{
    private InvalidateContributorOnboardingListener $listener;
    private MockInterface $onboardingService;
    private MockInterface $userRepository;
    private MockInterface $notificationDispatcher;
    private MockInterface $logger;
    private MockInterface $siteRepository;
    private MockInterface $userSiteRepository;

    // ── onContractPublished() ─────────────────────────────────────────────────

    public function test_syncs_status_for_all_contributors_when_contract_published(): void
    {
        $site = $this->makeSite(['id' => 1]);
        $contract = $this->makeContract(['id' => 5, 'version' => 2]);
        $event = new ContractPublishedEvent($contract, 1, 2, 99);

        $this->siteRepository->shouldReceive('find')->with(1)->andReturn($site);

        $this->mockUserSitesForSite(1, [7, 8]);

        $this->onboardingService->shouldReceive('syncStatus')->with(7, $site)->once();
        $this->onboardingService->shouldReceive('syncStatus')->with(8, $site)->once();

        // Both users have no pending steps after sync
        $this->onboardingService->shouldReceive('pendingSteps')->andReturn([]);

        $this->notificationDispatcher->shouldNotReceive('dispatch');

        $this->listener->onContractPublished($event);
        $this->assertTrue(true);
    }

    private function makeSite(array $attributes = []): Site
    {
        $site = new Site(array_merge(['id' => 1, 'name' => 'Test'], $attributes));
        $site->exists = true;
        return $site;
    }

    private function makeContract(array $attributes = []): Contract
    {
        $contract = Mockery::mock(Contract::class)->makePartial();
        $contract->id = $attributes['id'] ?? 1;
        $contract->version = $attributes['version'] ?? 1;
        return $contract;
    }

    // ── onGuidelinesBumped() ──────────────────────────────────────────────────

    /**
     * Stubs UserSite::where(...)->get()->pluck() to return the given user IDs.
     * We mock the static Eloquent call via a query builder mock chain.
     */
    private function mockUserSitesForSite(int $siteId, array $userIds): void
    {
//        $rows      = collect(array_map(fn($id) => (object) ['user_id' => $id], $userIds));

        $this->userSiteRepository->shouldReceive('userIdsForSite')
            ->with($siteId)
            ->andReturn($userIds);
    }

    public function test_notifies_users_when_contract_invalidates_their_onboarding(): void
    {
        $site = $this->makeSite(['id' => 1]);
        $contract = $this->makeContract(['id' => 5]);
        $event = new ContractPublishedEvent($contract, 1, 1, 99);

        $this->siteRepository->shouldReceive('find')->with(1)->andReturn($site);

        $contributor = $this->makeContributor(['id' => 7]);

        $this->mockUserSitesForSite(1, [7]);

        $this->onboardingService->shouldReceive('syncStatus')->with(7, $site)->once();
        $this->onboardingService->shouldReceive('pendingSteps')->with(7, $site)->once()->andReturn([
            ['step' => 'contract', 'reason' => 'New contract requires signature.', 'meta' => ['contract_id' => 5]],
        ]);

        $this->userRepository->shouldReceive('find')->with(7)->once()->andReturn($contributor);

        $this->notificationDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($n) => $n instanceof OnboardingInvalidatedNotification
                && $n->contributor === $contributor
                && $n->siteId === 1
                && count($n->pendingSteps) === 1
                && $n->pendingSteps[0]['step'] === 'contract'
            );

        $this->listener->onContractPublished($event);
        $this->assertTrue(true);
    }

    // ── Notification test (acceptance criteria) ───────────────────────────────

    private function makeContributor(array $attributes = []): User
    {
        $user = new User(array_merge(['id' => 7, 'name' => 'Test', 'email' => 't@example.com', 'is_contributor' => true], $attributes));
        $user->exists = true;
        return $user;
    }

    // ── Resilience ────────────────────────────────────────────────────────────

    public function test_does_not_notify_when_user_is_already_compliant_after_contract_change(): void
    {
        $site = $this->makeSite(['id' => 1]);
        $contract = $this->makeContract(['id' => 6]);
        $event = new ContractPublishedEvent($contract, 1, 1, 99);

        $this->siteRepository->shouldReceive('find')->with(1)->andReturn($site);

        $this->mockUserSitesForSite(1, [7]);

        $this->onboardingService->shouldReceive('syncStatus')->once();
        // User has signed the new contract already — no pending steps.
        $this->onboardingService->shouldReceive('pendingSteps')->once()->andReturn([]);

        $this->notificationDispatcher->shouldNotReceive('dispatch');

        $this->listener->onContractPublished($event);
        $this->assertTrue(true);
    }

    public function test_syncs_status_for_all_contributors_when_guidelines_bumped(): void
    {
        $site = $this->makeSite(['id' => 1]);
        $guideline = $this->makeGuideline(['id' => 3, 'version' => 2]);
        $event = new GuidelinesVersionBumpedEvent($guideline, 1, 2);

        $this->siteRepository->shouldReceive('find')->with(1)->andReturn($site);

        $this->mockUserSitesForSite(1, [7]);

        $this->onboardingService->shouldReceive('syncStatus')->with(7, $site)->once();
        $this->onboardingService->shouldReceive('pendingSteps')->andReturn([]);

        $this->notificationDispatcher->shouldNotReceive('dispatch');

        $this->listener->onGuidelinesBumped($event);
        $this->assertTrue(true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeGuideline(array $attributes = []): Guideline
    {
        $guideline = Mockery::mock(Guideline::class)->makePartial();
        $guideline->id = $attributes['id'] ?? 1;
        $guideline->version = $attributes['version'] ?? 1;
        return $guideline;
    }

    public function test_notifies_users_when_guidelines_bump_invalidates_onboarding(): void
    {
        $site = $this->makeSite(['id' => 1]);
        $guideline = $this->makeGuideline(['id' => 3, 'version' => 2]);
        $event = new GuidelinesVersionBumpedEvent($guideline, 1, 2);
        $contributor = $this->makeContributor(['id' => 7]);

        $this->siteRepository->shouldReceive('find')->with(1)->andReturn($site);

        $this->mockUserSitesForSite(1, [7]);

        $this->onboardingService->shouldReceive('syncStatus')->once();
        $this->onboardingService->shouldReceive('pendingSteps')->once()->andReturn([
            ['step' => 'guidelines', 'reason' => 'Guidelines updated.', 'meta' => ['required_version' => 2]],
        ]);

        $this->userRepository->shouldReceive('find')->with(7)->once()->andReturn($contributor);

        $this->notificationDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($n) => $n instanceof OnboardingInvalidatedNotification
                && $n->pendingSteps[0]['step'] === 'guidelines'
            );

        $this->listener->onGuidelinesBumped($event);
        $this->assertTrue(true);
    }

    public function test_notifies_user_when_onboarding_becomes_invalid(): void
    {
        // Full scenario: user completes onboarding, new contract published,
        // listener fires, user is notified with correct pending step detail.
        $site = $this->makeSite(['id' => 1]);
        $contract = $this->makeContract(['id' => 6, 'version' => 2]);
        $event = new ContractPublishedEvent($contract, 1, 2, 99);
        $contributor = $this->makeContributor(['id' => 7]);

        $this->siteRepository->shouldReceive('find')->with(1)->andReturn($site);

        $this->mockUserSitesForSite(1, [7]);

        $pendingSteps = [
            ['step' => 'contract', 'reason' => 'New contract requires signature.', 'meta' => ['contract_id' => 6]],
        ];

        $this->onboardingService->shouldReceive('syncStatus')->once();
        $this->onboardingService->shouldReceive('pendingSteps')->once()->andReturn($pendingSteps);
        $this->userRepository->shouldReceive('find')->with(7)->andReturn($contributor);

        $dispatchedNotification = null;
        $this->notificationDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(function ($notification) use (&$dispatchedNotification) {
                $dispatchedNotification = $notification;
                return true;
            });

        $this->listener->onContractPublished($event);

        $this->assertInstanceOf(OnboardingInvalidatedNotification::class, $dispatchedNotification);
        $this->assertSame($contributor, $dispatchedNotification->contributor);
        $this->assertEquals(1, $dispatchedNotification->siteId);
        $this->assertEquals($pendingSteps, $dispatchedNotification->pendingSteps);
        $this->assertNotEmpty($dispatchedNotification->subject());
    }

    public function test_failure_for_one_contributor_does_not_block_others(): void
    {
        $site = $this->makeSite(['id' => 1]);
        $contract = $this->makeContract(['id' => 5]);
        $event = new ContractPublishedEvent($contract, 1, 1, 99);

        $this->siteRepository->shouldReceive('find')->with(1)->andReturn($site);

        $contributor8 = $this->makeContributor(['id' => 8]);

        $this->mockUserSitesForSite(1, [7, 8]);

        // User 7 throws — must not stop user 8 from being processed.
        $this->onboardingService->shouldReceive('syncStatus')
            ->with(7, $site)
            ->once()
            ->andThrow(new \RuntimeException('DB failure for user 7'));

        $this->onboardingService->shouldReceive('syncStatus')->with(8, $site)->once();
        $this->onboardingService->shouldReceive('pendingSteps')->with(8, $site)->once()->andReturn([
            ['step' => 'contract', 'reason' => 'Signature required.', 'meta' => []],
        ]);

        $this->userRepository->shouldReceive('find')->with(8)->andReturn($contributor8);
        $this->logger->shouldReceive('error')->once();
        $this->notificationDispatcher->shouldReceive('dispatch')->once();

        $this->listener->onContractPublished($event);
        $this->assertTrue(true);
    }

    public function test_skips_notification_for_non_contributor_users(): void
    {
        $site = $this->makeSite(['id' => 1]);
        $contract = $this->makeContract(['id' => 5]);
        $event = new ContractPublishedEvent($contract, 1, 1, 99);

        $this->siteRepository->shouldReceive('find')->with(1)->andReturn($site);

        // User exists but is NOT a contributor
        $nonContributor = new User(['id' => 7, 'is_contributor' => false]);
        $nonContributor->exists = true;

        $this->mockUserSitesForSite(1, [7]);

        $this->onboardingService->shouldReceive('syncStatus')->once();
        $this->onboardingService->shouldReceive('pendingSteps')->once()->andReturn([
            ['step' => 'contract', 'reason' => 'Required.', 'meta' => []],
        ]);

        $this->userRepository->shouldReceive('find')->with(7)->andReturn($nonContributor);
        $this->notificationDispatcher->shouldNotReceive('dispatch');

        $this->listener->onContractPublished($event);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->onboardingService = Mockery::mock(ContributorOnboardingService::class);
        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->notificationDispatcher = Mockery::mock(NotificationDispatcher::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->siteRepository = Mockery::mock(SiteRepository::class);
        $this->userSiteRepository = Mockery::mock(UserSiteRepository::class);

        $this->listener = new InvalidateContributorOnboardingListener(
            $this->onboardingService,
            $this->userRepository,
            $this->notificationDispatcher,
            $this->logger,
            $this->siteRepository,
            $this->userSiteRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
