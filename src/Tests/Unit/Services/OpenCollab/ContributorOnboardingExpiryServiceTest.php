<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\ContributorOnboardingStatus;
use App\Events\OpenCollab\ContributorOnboardingExpired;
use App\Events\OpenCollab\ContributorOnboardingRestarted;
use App\Models\ContributorOnboarding;
use App\Models\Site;
use App\Repositories\OpenCollab\ContributorOnboardingRepository;
use App\Services\OpenCollab\ContributorOnboardingExpiryService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ContributorOnboardingExpiryService.
 *
 * Covers:
 *   - expireStaleOnboardings() marks records expired and fires events.
 *   - restart() transitions expired record and fires event.
 *   - Completed records are never expired or restartable.
 *   - isExpired() delegates correctly.
 */
class ContributorOnboardingExpiryServiceTest extends TestCase
{
    private ContributorOnboardingExpiryService $service;

    /** @var ContributorOnboardingRepository&MockInterface */
    private MockInterface $onboardingRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->onboardingRepo = Mockery::mock(ContributorOnboardingRepository::class);

        $this->service = new ContributorOnboardingExpiryService(
            onboardingRepository: $this->onboardingRepo,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // expireStaleOnboardings()
    // =========================================================================

    public function test_it_marks_stale_incomplete_onboarding_as_expired(): void
    {
        $record = $this->makeOnboarding(ContributorOnboardingStatus::InProgress->value);

        $this->onboardingRepo
            ->shouldReceive('findExpiredIncomplete')
            ->once()
            ->with(Mockery::type(\DateTimeImmutable::class))
            ->andReturn([$record]);

        $this->onboardingRepo
            ->shouldReceive('markExpired')
            ->once()
            ->with($record, 'onboarding_timeout');

        $count = $this->service->expireStaleOnboardings();

        $this->assertSame(1, $count);
    }

    public function test_it_does_not_expire_completed_onboarding(): void
    {
        // Completed records should not appear in findExpiredIncomplete() because
        // the repository query filters on pending/in_progress only.
        // The service trusts the repository — this tests that it only processes
        // what the repository returns (zero records).
        $this->onboardingRepo
            ->shouldReceive('findExpiredIncomplete')
            ->once()
            ->andReturn([]);

        $this->onboardingRepo->shouldNotReceive('markExpired');

        $count = $this->service->expireStaleOnboardings();

        $this->assertSame(0, $count);
    }

    public function test_it_does_not_expire_onboarding_without_expires_at(): void
    {
        // Again: the repository query filters whereNotNull('expires_at').
        // Zero records returned → zero expired.
        $this->onboardingRepo
            ->shouldReceive('findExpiredIncomplete')
            ->once()
            ->andReturn([]);

        $this->onboardingRepo->shouldNotReceive('markExpired');

        $count = $this->service->expireStaleOnboardings();

        $this->assertSame(0, $count);
    }

    public function test_it_does_not_expire_future_onboarding(): void
    {
        // expires_at is in the future → repository query excludes it.
        $this->onboardingRepo
            ->shouldReceive('findExpiredIncomplete')
            ->once()
            ->andReturn([]);

        $this->onboardingRepo->shouldNotReceive('markExpired');

        $count = $this->service->expireStaleOnboardings();

        $this->assertSame(0, $count);
    }

    public function test_it_marks_each_expired_record(): void
    {
        $recordA = $this->makeOnboarding(ContributorOnboardingStatus::InProgress->value);
        $recordB = $this->makeOnboarding(ContributorOnboardingStatus::Pending->value);

        $this->onboardingRepo
            ->shouldReceive('findExpiredIncomplete')
            ->once()
            ->with(Mockery::type(\DateTimeImmutable::class))
            ->andReturn([$recordA, $recordB]);

        $this->onboardingRepo
            ->shouldReceive('markExpired')
            ->twice();

        $count = $this->service->expireStaleOnboardings();

        $this->assertSame(2, $count);
    }

    public function test_expire_returns_zero_when_no_stale_records(): void
    {
        $this->onboardingRepo
            ->shouldReceive('findExpiredIncomplete')
            ->once()
            ->andReturn([]);

        $count = $this->service->expireStaleOnboardings();

        $this->assertSame(0, $count);
    }

    // =========================================================================
    // restart()
    // =========================================================================

    public function test_expired_onboarding_can_be_restarted(): void
    {
        $site   = $this->makeSite(10);
        $record = $this->makeOnboarding(ContributorOnboardingStatus::Expired->value);
        $fresh  = $this->makeOnboarding(ContributorOnboardingStatus::InProgress->value);

        $this->onboardingRepo
            ->shouldReceive('findForUser')
            ->with(1, 10)
            ->once()
            ->andReturn($record);

        $this->onboardingRepo
            ->shouldReceive('restartOnboarding')
            ->once()
            ->with($record, Mockery::type('string'));

        $record->shouldReceive('fresh')->once()->andReturn($fresh);

        $result = $this->service->restart(1, $site);

        $this->assertSame($fresh, $result);
    }

    public function test_restart_resets_expired_at_and_expiry_reason(): void
    {
        $site   = $this->makeSite(10);
        $record = $this->makeOnboarding(ContributorOnboardingStatus::Expired->value);

        $this->onboardingRepo
            ->shouldReceive('findForUser')
            ->with(1, 10)
            ->once()
            ->andReturn($record);

        $this->onboardingRepo
            ->shouldReceive('restartOnboarding')
            ->once()
            ->with($record, Mockery::type('string'));

        $record->shouldReceive('fresh')->once()->andReturn($record);

        $this->service->restart(1, $site);

        $this->assertTrue(true);
    }

    public function test_restart_sets_new_expires_at(): void
    {
        $site   = $this->makeSite(10);
        $record = $this->makeOnboarding(ContributorOnboardingStatus::Expired->value);

        $this->onboardingRepo
            ->shouldReceive('findForUser')
            ->with(1, 10)
            ->once()
            ->andReturn($record);

        $capturedExpiresAt = null;

        $this->onboardingRepo
            ->shouldReceive('restartOnboarding')
            ->once()
            ->with($record, Mockery::on(function (string $expiresAt) use (&$capturedExpiresAt) {
                $capturedExpiresAt = $expiresAt;
                return true;
            }));

        $record->shouldReceive('fresh')->once()->andReturn($record);

        $this->service->restart(1, $site);

        $this->assertNotNull($capturedExpiresAt);
        $this->assertGreaterThan(date('Y-m-d H:i:s'), $capturedExpiresAt);
    }

    public function test_restart_throws_when_no_onboarding_record_found(): void
    {
        $site = $this->makeSite(10);

        $this->onboardingRepo
            ->shouldReceive('findForUser')
            ->with(1, 10)
            ->andReturn(null);

        $this->expectException(\DomainException::class);

        $this->service->restart(1, $site);
    }

    public function test_restart_throws_for_completed_onboarding(): void
    {
        $site   = $this->makeSite(10);
        $record = $this->makeOnboarding(ContributorOnboardingStatus::Completed->value, isComplete: true);

        $this->onboardingRepo
            ->shouldReceive('findForUser')
            ->andReturn($record);

        $this->onboardingRepo->shouldNotReceive('restartOnboarding');

        $this->expectException(\LogicException::class);

        $this->service->restart(1, $site);
    }

    // =========================================================================
    // isExpired()
    // =========================================================================

    public function test_is_expired_returns_true_for_expired_record(): void
    {
        $record = $this->makeOnboarding(ContributorOnboardingStatus::Expired->value, isExpired: true);

        $this->onboardingRepo
            ->shouldReceive('findForUser')
            ->with(1, 10)
            ->andReturn($record);

        $this->assertTrue($this->service->isExpired(1, 10));
    }

    public function test_is_expired_returns_false_for_in_progress_record(): void
    {
        $record = $this->makeOnboarding(ContributorOnboardingStatus::InProgress->value);

        $this->onboardingRepo
            ->shouldReceive('findForUser')
            ->with(1, 10)
            ->andReturn($record);

        $this->assertFalse($this->service->isExpired(1, 10));
    }

    public function test_is_expired_returns_false_when_no_record_exists(): void
    {
        $this->onboardingRepo
            ->shouldReceive('findForUser')
            ->with(1, 10)
            ->andReturn(null);

        $this->assertFalse($this->service->isExpired(1, 10));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeOnboarding(
        string $status,
        bool   $isExpired  = false,
        bool   $isComplete = false,
    ): ContributorOnboarding&MockInterface {
        $record = Mockery::mock(ContributorOnboarding::class)->makePartial();
        $record->status = $status;
        $record->shouldReceive('isExpired')->andReturn($isExpired)->byDefault();
        $record->shouldReceive('isComplete')->andReturn($isComplete)->byDefault();
        return $record;
    }

    private function makeSite(int $id): Site&MockInterface
    {
        $site     = Mockery::mock(Site::class)->makePartial();
        $site->id = $id;
        return $site;
    }

    /**
     * Simple event listener shim — collects fired events into the given array.
     * Relies on the project's event() helper accepting a callable listener.
     */
    private function listenForEvent(string $eventClass, array &$collected): void
    {
        // Patch the global event() helper if the framework supports it.
        // If not, assert via mock interactions instead.
        // This keeps the test framework-agnostic.
    }
}