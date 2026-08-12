<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\ViolationAction;
use App\Enums\OpenCollab\ViolationSeverity;
use App\Enums\OpenCollab\ViolationType;
use App\Events\OpenCollab\ViolationRecordedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Models\ContributorViolation;
use App\Models\User;
use App\Repositories\OpenCollab\ViolationRepository;
use App\Services\OpenCollab\ViolationService;
use App\Services\User\UserLifecycleServiceInterface;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class ViolationServiceTest extends UnitTestCase
{
    private ViolationService $service;
    private MockInterface $violationRepository;
    private MockInterface $userLifecycle;
    private MockInterface $eventDispatcher;
    private MockInterface $databaseMock;
    private MockInterface $logger;
    private NotificationDispatcher $notificationDispatcher;

    // -------------------------------------------------------------------------
    // record()
    // -------------------------------------------------------------------------

    public function test_low_severity_below_threshold_records_warning(): void
    {
        $this->userLifecycle->shouldReceive('findById')->with(7)->andReturn($this->makeUser());
        $this->violationRepository->shouldReceive('unresolvedCountBySeverity')
            ->with(7, 1, ViolationSeverity::Low)
            ->andReturn(2); // 3 after this one — threshold is 5
        $this->violationRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn($data) => $data['action_taken'] === ViolationAction::Warning->value)
            ->andReturn($this->makeViolation(['action_taken' => ViolationAction::Warning->value]));
        $this->userLifecycle->shouldNotReceive('deactivateContributor'); // no deactivation on warning
        $this->eventDispatcher->shouldReceive('dispatch')->once()
            ->withArgs(fn($e) => $e instanceof ViolationRecordedEvent);

        $violation = $this->service->record(7, 1, ViolationType::Quality, ViolationSeverity::Low, 'reason', 55);

        $this->assertEquals(ViolationAction::Warning->value, $violation->action_taken);
    }

    private function makeUser(): User
    {
        $user = new User(['id' => 7, 'name' => 'Test', 'email' => 'test@example.com', 'is_active' => true]);
        $user->exists = true;
        return $user;
    }

    private function makeViolation(array $attributes = []): ContributorViolation
    {
        $defaults = [
            'id' => 1,
            'user_id' => 7,
            'site_id' => 1,
            'type' => ViolationType::Quality->value,
            'severity' => ViolationSeverity::Low->value,
            'reason' => 'Test reason',
            'action_taken' => ViolationAction::Warning->value,
            'created_by' => 55,
            'resolved_at' => null,
        ];
        $violation = new ContributorViolation(array_merge($defaults, $attributes));
        $violation->exists = true;
        return $violation;
    }

    public function test_low_severity_at_threshold_records_suspension_and_deactivates(): void
    {
        $this->userLifecycle->shouldReceive('findById')->with(7)->andReturn($this->makeUser());
        $this->violationRepository->shouldReceive('unresolvedCountBySeverity')
            ->with(7, 1, ViolationSeverity::Low)
            ->andReturn(4); // 5 after this one — hits threshold
        $this->violationRepository->shouldReceive('create')
            ->once()
            ->withArgs(fn($data) => $data['action_taken'] === ViolationAction::Suspension->value)
            ->andReturn($this->makeViolation(['action_taken' => ViolationAction::Suspension->value]));
        $this->userLifecycle->shouldReceive('deactivateContributor')
            ->with(7, 55, 'reason')
            ->once();
        $this->logger->shouldReceive('info')->once();
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        $violation = $this->service->record(7, 1, ViolationType::Quality, ViolationSeverity::Low, 'reason', 55);

        $this->assertEquals(ViolationAction::Suspension->value, $violation->action_taken);
    }

    public function test_medium_severity_at_threshold_suspends(): void
    {
        $this->userLifecycle->shouldReceive('findById')->andReturn($this->makeUser());
        $this->violationRepository->shouldReceive('unresolvedCountBySeverity')
            ->andReturn(2); // 3 after this one — threshold for medium
        $this->violationRepository->shouldReceive('create')
            ->withArgs(fn($data) => $data['action_taken'] === ViolationAction::Suspension->value)
            ->andReturn($this->makeViolation(['action_taken' => ViolationAction::Suspension->value]));
        $this->userLifecycle->shouldReceive('deactivateContributor')->once();
        $this->logger->shouldReceive('info')->once();
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        $violation = $this->service->record(7, 1, ViolationType::Spam, ViolationSeverity::Medium, 'reason', 55);

        $this->assertEquals(ViolationAction::Suspension->value, $violation->action_taken);
    }

    public function test_high_severity_always_bans_immediately(): void
    {
        $this->userLifecycle->shouldReceive('findById')->andReturn($this->makeUser());
        $this->violationRepository->shouldReceive('unresolvedCountBySeverity')
            ->andReturn(0); // first violation, but high severity → immediate ban
        $this->violationRepository->shouldReceive('create')
            ->withArgs(fn($data) => $data['action_taken'] === ViolationAction::Ban->value)
            ->andReturn($this->makeViolation(['action_taken' => ViolationAction::Ban->value]));
        $this->userLifecycle->shouldReceive('deactivateContributor')
            ->with(7, 55, 'reason')
            ->once();
        $this->logger->shouldReceive('info')->once();
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        $violation = $this->service->record(7, 1, ViolationType::Plagiarism, ViolationSeverity::High, 'reason', 55);

        $this->assertEquals(ViolationAction::Ban->value, $violation->action_taken);
    }

    public function test_admin_override_action_is_respected(): void
    {
        $this->userLifecycle->shouldReceive('findById')->andReturn($this->makeUser());
        // Even though unresolved count would only warrant a warning,
        // the admin explicitly requests a ban
        $this->violationRepository->shouldReceive('unresolvedCountBySeverity')->andReturn(0);
        $this->violationRepository->shouldReceive('create')
            ->withArgs(fn($data) => $data['action_taken'] === ViolationAction::Ban->value)
            ->andReturn($this->makeViolation(['action_taken' => ViolationAction::Ban->value]));
        $this->userLifecycle->shouldReceive('deactivateContributor')->once(); // ban deactivates account
        $this->logger->shouldReceive('info')->once();
        $this->eventDispatcher->shouldReceive('dispatch')->once();

        $violation = $this->service->record(
            7, 1, ViolationType::Other, ViolationSeverity::Low, 'reason', 55,
            actionOverride: ViolationAction::Ban,
        );

        $this->assertEquals(ViolationAction::Ban->value, $violation->action_taken);
    }

    // -------------------------------------------------------------------------
    // resolve()
    // -------------------------------------------------------------------------

    public function test_record_throws_when_user_not_found(): void
    {
        $this->userLifecycle->shouldReceive('findById')->andReturn(null);
        $this->violationRepository->shouldNotReceive('create');

        $this->expectException(\InvalidArgumentException::class);

        $this->service->record(999, 1, ViolationType::Spam, ViolationSeverity::Low, 'reason', 55);
    }

    public function test_record_dispatches_violation_recorded_event(): void
    {
        $this->userLifecycle->shouldReceive('findById')->andReturn($this->makeUser());
        $this->violationRepository->shouldReceive('unresolvedCountBySeverity')->andReturn(0);
        $violation = $this->makeViolation();
        $this->violationRepository->shouldReceive('create')->andReturn($violation);
        $this->eventDispatcher->shouldReceive('dispatch')
            ->once()
            ->withArgs(fn($e) => $e instanceof ViolationRecordedEvent && $e->violation === $violation);

        $this->service->record(7, 1, ViolationType::Other, ViolationSeverity::Low, 'reason', 55);
        $this->assertTrue(true);
    }

    public function test_resolve_reactivates_account_when_no_other_active_violations(): void
    {
        $violation = $this->makeViolation(['id' => 3, 'action_taken' => ViolationAction::Suspension->value]);

        $this->violationRepository->shouldReceive('find')->with(3)->andReturn($violation, $violation);
        $this->violationRepository->shouldReceive('update')->once()
            ->withArgs(fn($id, $data) => isset($data['resolved_at']));
        $this->violationRepository->shouldReceive('hasActiveBanForUser')->andReturn(false);
        $this->violationRepository->shouldReceive('hasActiveSuspensionForUser')->andReturn(false);
        $this->userLifecycle->shouldReceive('reactivateContributor')
            ->with($violation->user_id, 99, null)
            ->once();

        $this->service->resolve(3, adminId: 99);
        $this->assertTrue(true);
    }

    public function test_resolve_does_not_reactivate_when_other_active_ban_exists(): void
    {
        $violation = $this->makeViolation(['id' => 3]);

        $this->violationRepository->shouldReceive('find')->andReturn($violation, $violation);
        $this->violationRepository->shouldReceive('update')->once();
        $this->violationRepository->shouldReceive('hasActiveBanForUser')->andReturn(true);
        $this->violationRepository->shouldReceive('hasActiveSuspensionForUser')->andReturn(false);
        $this->userLifecycle->shouldNotReceive('reactivateContributor');

        $this->service->resolve(3, 99);
        $this->assertTrue(true);
    }

    public function test_resolve_does_not_reactivate_when_ban_remains_on_another_site(): void
    {
        $violation = $this->makeViolation(['id' => 3, 'site_id' => 1]);

        $this->violationRepository->shouldReceive('find')->andReturn($violation, $violation);
        $this->violationRepository->shouldReceive('update')->once();
        // Site 1 is clear after resolve, but another site still has an active ban.
        $this->violationRepository->shouldReceive('hasActiveBanForUser')
            ->with((int) $violation->user_id)
            ->once()
            ->andReturn(true);
        $this->violationRepository->shouldReceive('hasActiveSuspensionForUser')->andReturn(false);
        $this->userLifecycle->shouldNotReceive('reactivateContributor');

        $this->service->resolve(3, 99);
        $this->assertTrue(true);
    }

    // -------------------------------------------------------------------------
    // isBlocked()
    // -------------------------------------------------------------------------

    public function test_resolve_throws_when_already_resolved(): void
    {
        $resolved = $this->makeViolation(['resolved_at' => date('Y-m-d H:i:s')]);

        $this->violationRepository->shouldReceive('find')->andReturn($resolved);
        $this->violationRepository->shouldNotReceive('update');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/already resolved/i');

        $this->service->resolve(1, 99);
    }

    public function test_resolve_throws_when_violation_not_found(): void
    {
        $this->violationRepository->shouldReceive('find')->andReturn(null);

        $this->expectException(\InvalidArgumentException::class);

        $this->service->resolve(999, 99);
    }

    public function test_is_blocked_returns_true_when_active_ban(): void
    {
        $this->violationRepository->shouldReceive('hasActiveBan')->with(7, 1)->andReturn(true);

        $this->assertTrue($this->service->isBlocked(7, 1));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_is_blocked_returns_true_when_active_suspension(): void
    {
        $this->violationRepository->shouldReceive('hasActiveBan')->andReturn(false);
        $this->violationRepository->shouldReceive('hasActiveSuspension')->andReturn(true);

        $this->assertTrue($this->service->isBlocked(7, 1));
    }

    public function test_is_blocked_returns_false_when_no_active_violations(): void
    {
        $this->violationRepository->shouldReceive('hasActiveBan')->andReturn(false);
        $this->violationRepository->shouldReceive('hasActiveSuspension')->andReturn(false);

        $this->assertFalse($this->service->isBlocked(7, 1));
    }

    protected function setUp(): void
    {

        $this->violationRepository = Mockery::mock(ViolationRepository::class);
        $this->userLifecycle = Mockery::mock(UserLifecycleServiceInterface::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcher::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn(callable $cb) => $cb());
        $this->notificationDispatcher = Mockery::mock(NotificationDispatcher::class);

        $this->service = new ViolationService(
            $this->violationRepository,
            $this->userLifecycle,
            $this->eventDispatcher,
            $this->databaseMock,
            $this->logger,
            $this->notificationDispatcher
        );

        $this->notificationDispatcher->shouldReceive('dispatch')->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
