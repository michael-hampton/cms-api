<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\OnboardingStepStatus;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Models\Contract;
use App\Models\ContributorProfile;
use App\Models\Site;
use App\Models\User;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContributorOnboardingRepository;
use App\Repositories\OpenCollab\ContributorOnboardingStepRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\GuidelinesRepository;
use App\Services\OpenCollab\ContributorAgeValidationService;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ContributorOnboardingService.
 *
 * The core rule under test:
 *   A step is complete only when:
 *     1. the site requires that step
 *     2. the step row status === 'completed'
 *     3. domain validation still passes
 *
 * Tests are grouped by the method they exercise, then by scenario.
 */
class ContributorOnboardingServiceTest extends TestCase
{
    private ContributorOnboardingService $service;

    /** @var ContributorProfileRepository&MockInterface */
    private MockInterface $profileRepo;

    /** @var ContributorOnboardingStepRepository&MockInterface */
    private MockInterface $stepRepo;

    /** @var ContractRepository&MockInterface */
    private MockInterface $contractRepo;

    /** @var GuidelinesRepository&MockInterface */
    private MockInterface $guidelinesRepo;

    private ContributorAgeValidationService $ageService;
    private ContributorOnboardingRepository $contributorOnboardingRepository;

    // ── Fixtures ──────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileRepo    = Mockery::mock(ContributorProfileRepository::class);
        $this->stepRepo       = Mockery::mock(ContributorOnboardingStepRepository::class);
        $this->contractRepo   = Mockery::mock(ContractRepository::class);
        $this->guidelinesRepo = Mockery::mock(GuidelinesRepository::class);
        $this->contributorOnboardingRepository = Mockery::mock(ContributorOnboardingRepository::class);
        $this->ageService     = new ContributorAgeValidationService();

        // Default: step repo reports no rows exist for any step.
        $this->stepRepo->shouldReceive('getStatus')->andReturn(null)->byDefault();
        $this->stepRepo->shouldReceive('markInvalidated')->byDefault();

        $this->service = new ContributorOnboardingService(
            profileRepository:         $this->profileRepo,
            onboardingStepRepository:  $this->stepRepo,
            contractRepository:        $this->contractRepo,
            guidelinesRepository:      $this->guidelinesRepo,
            ageValidationService:      $this->ageService,
            contributorOnboardingRepository:  $this->contributorOnboardingRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // completeStep()
    // =========================================================================

    // ── step applicability ────────────────────────────────────────────────────

    public function test_complete_step_throws_for_unknown_step_key(): void
    {
        $site = $this->makeSite();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown onboarding step/');

        $this->service->completeStep(1, $site, 'not_a_real_step');
    }

    public function test_complete_step_throws_when_step_not_applicable_for_site(): void
    {
        // Site does not require contracts.
        $site = $this->makeSite(['require_contracts' => false]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($this->makeProfile());
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not applicable/');

        $this->service->completeStep(1, $site, 'contract');
    }

    public function test_complete_step_throws_when_payment_not_applicable_for_site(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not applicable/');

        $this->service->completeStep(1, $site, 'payment');
    }

    // ── domain validation before marking ─────────────────────────────────────

    public function test_complete_step_throws_when_profile_bio_missing(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $profile = $this->makeProfile(['bio' => '']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/domain validation failed/');

        $this->service->completeStep(1, $site, 'profile');
    }

    public function test_complete_step_throws_when_payment_not_setup(): void
    {
        $site = $this->makeSite(['require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);

        $this->profileRepo->shouldReceive('isPaymentSetup')->with(1)->andReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/domain validation failed/');

        $this->service->completeStep(1, $site, 'payment');
    }

    public function test_complete_step_throws_when_contract_not_signed(): void
    {
        $site     = $this->makeSite(['require_payment_setup' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $contract = $this->makeContract(['id' => 5, 'version' => 1]);

        $this->contractRepo->shouldReceive('latestPublishedForSite')->with($site->id)->andReturn($contract);
        $this->contractRepo->shouldReceive('hasSigned')->with(1, 5)->andReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/domain validation failed/');

        $this->service->completeStep(1, $site, 'contract');
    }

    public function test_complete_step_throws_when_guidelines_not_acknowledged(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_age_verification' => false, 'guidelines_version' => 3]);

        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->with(1, $site->id)->andReturn(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/domain validation failed/');

        $this->service->completeStep(1, $site, 'guidelines');
    }

    public function test_complete_step_throws_when_age_not_verified(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'minimum_contributor_age' => 18]);

        $profile = $this->makeProfile(['date_of_birth' => null]);
        $profile->date_of_birth = null;

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/domain validation failed/');

        $this->service->completeStep(1, $site, 'age_verification');
    }

    public function test_complete_step_throws_when_contributor_is_underage(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'minimum_contributor_age' => 18]);
        $dob     = (new DateTimeImmutable('-16 years', new DateTimeZone('UTC')))->format('Y-m-d');
        $profile = $this->makeProfile(['date_of_birth' => $dob]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/domain validation failed/');

        $this->service->completeStep(1, $site, 'age_verification');
    }

    // ── successful completion ─────────────────────────────────────────────────

    public function test_complete_step_marks_completed_when_domain_valid_for_profile(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $profile = $this->makeProfile(['bio' => 'A valid bio with enough length.']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $this->contributorOnboardingRepository->shouldReceive('syncStatus')
            ->once()
            ->with(1, $site, false)
            ->andReturn(true);

        $this->stepRepo->shouldReceive('markCompleted')
            ->once()
            ->with(1, $site->id, 'profile', null);

        $this->service->completeStep(1, $site, 'profile');
        $this->assertTrue(true);
    }

    public function test_complete_step_marks_completed_with_meta_for_contract(): void
    {
        $site = $this->makeSite([
            'require_payment_setup'    => false,
            'require_guidelines_ack'   => false,
            'require_age_verification' => false,
        ]);

        $contract = $this->makeContract([
            'id' => 7,
            'version' => 2,
        ]);

        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->bio = 'This is a valid contributor bio.';

        $this->contractRepo
            ->shouldReceive('latestPublishedForSite')
            ->twice()
            ->with($site->id)
            ->andReturn($contract);

        $this->contractRepo
            ->shouldReceive('hasSigned')
            ->twice()
            ->with(1, 7)
            ->andReturn(true);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->once()
            ->with(1)
            ->andReturn($profile);

        $this->stepRepo
            ->shouldReceive('markCompleted')
            ->once()
            ->with(1, $site->id, 'contract', [
                'contract_id' => 7,
                'contract_version' => 2,
            ]);

        $this->stepRepo
            ->shouldReceive('getStatus')
            ->with(1, $site->id, 'profile')
            ->once()
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->stepRepo
            ->shouldReceive('getStatus')
            ->with(1, $site->id, 'contract')
            ->once()
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->contributorOnboardingRepository
            ->shouldReceive('syncStatus')
            ->once()
            ->with(1, $site, true);

        $this->service->completeStep(1, $site, 'contract', [
            'contract_id' => 7,
            'contract_version' => 2,
        ]);

        $this->assertTrue(true);
    }

    public function test_complete_step_marks_completed_for_payment(): void
    {
        $site = $this->makeSite([
            'require_contracts'        => false,
            'require_guidelines_ack'   => false,
            'require_age_verification' => false,
        ]);

        $profile = $this->makeProfile([
            'bio' => 'A valid bio with enough length.',
        ]);

        $this->profileRepo
            ->shouldReceive('isPaymentSetup')
            ->twice()
            ->with(1)
            ->andReturn(true);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->once()
            ->with(1)
            ->andReturn($profile);

        $this->mockStepStatuses(1, $site, [
            'profile' => OnboardingStepStatus::Completed->value,
            'payment' => OnboardingStepStatus::Completed->value,
        ]);

        $this->stepRepo
            ->shouldReceive('markCompleted')
            ->once()
            ->with(1, $site->id, 'payment', null);

        $this->contributorOnboardingRepository
            ->shouldReceive('syncStatus')
            ->once()
            ->with(1, $site, true);

        $this->service->completeStep(1, $site, 'payment');

        $this->assertTrue(true);
    }

    public function test_complete_step_marks_completed_for_guidelines(): void
    {
        $site = $this->makeSite([
            'require_payment_setup'    => false,
            'require_contracts'        => false,
            'require_age_verification' => false,
            'guidelines_version'       => 2,
        ]);

        $profile = $this->makeProfile([
            'bio' => 'A valid bio with enough length.',
        ]);

        $this->guidelinesRepo
            ->shouldReceive('latestAcknowledgedVersion')
            ->twice()
            ->with(1, $site->id)
            ->andReturn(2);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->once()
            ->with(1)
            ->andReturn($profile);

        $this->mockStepStatuses(1, $site, [
            'profile'    => OnboardingStepStatus::Completed->value,
            'guidelines' => OnboardingStepStatus::Completed->value,
        ]);

        $this->stepRepo
            ->shouldReceive('markCompleted')
            ->once()
            ->with(1, $site->id, 'guidelines', null);

        $this->contributorOnboardingRepository
            ->shouldReceive('syncStatus')
            ->once()
            ->with(1, $site, true);

        $this->service->completeStep(1, $site, 'guidelines');

        $this->assertTrue(true);
    }

    public function test_complete_step_marks_completed_for_age_verification(): void
    {
        $site = $this->makeSite([
            'require_payment_setup'     => false,
            'require_contracts'         => false,
            'require_guidelines_ack'    => false,
            'minimum_contributor_age'   => 18,
        ]);

        $dob = (new DateTimeImmutable('-25 years', new DateTimeZone('UTC')))->format('Y-m-d');

        $profile = $this->makeProfile([
            'bio'           => 'A valid bio with enough length.',
            'date_of_birth' => $dob,
        ]);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->times(3)
            ->with(1)
            ->andReturn($profile);

        $this->mockStepStatuses(1, $site, [
            'profile'          => OnboardingStepStatus::Completed->value,
            'age_verification' => OnboardingStepStatus::Completed->value,
        ]);

        $this->stepRepo
            ->shouldReceive('markCompleted')
            ->once()
            ->with(1, $site->id, 'age_verification', null);

        $this->contributorOnboardingRepository
            ->shouldReceive('syncStatus')
            ->once()
            ->with(1, $site, true);

        $this->service->completeStep(1, $site, 'age_verification');

        $this->assertTrue(true);
    }

    public function test_complete_step_calls_sync_status_after_marking(): void
    {
        $site = $this->makeSite([
            'require_payment_setup'    => false,
            'require_contracts'        => false,
            'require_guidelines_ack'   => false,
            'require_age_verification' => false,
        ]);

        $profile = $this->makeProfile([
            'bio' => 'A valid bio with enough length.',
        ]);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->twice()
            ->with(1)
            ->andReturn($profile);

        $this->stepRepo
            ->shouldReceive('markCompleted')
            ->once()
            ->with(1, $site->id, 'profile', null);

        $this->stepRepo
            ->shouldReceive('getStatus')
            ->once()
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->contributorOnboardingRepository
            ->shouldReceive('syncStatus')
            ->once()
            ->with(1, $site, true);

        $this->service->completeStep(1, $site, 'profile');

        $this->assertTrue(true);
    }

    // =========================================================================
    // invalidateStep() / invalidateStepForAllContributors()
    // =========================================================================

    public function test_invalidate_step_throws_for_unknown_step(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->invalidateStep(1, 10, 'not_real');
    }

    public function test_invalidate_step_delegates_to_repository(): void
    {
        $this->stepRepo->shouldReceive('markInvalidated')
            ->once()
            ->with(1, 10, 'contract');

        $this->service->invalidateStep(1, 10, 'contract');
        $this->assertTrue(true);
    }

    public function test_invalidate_step_for_all_contributors_throws_for_unknown_step(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->invalidateStepForAllContributors(10, 'bad_step');
    }

    public function test_invalidate_step_for_all_contributors_delegates_and_returns_count(): void
    {
        $this->stepRepo->shouldReceive('bulkInvalidateCompletedStep')
            ->once()
            ->with(10, 'guidelines')
            ->andReturn(5);

        $count = $this->service->invalidateStepForAllContributors(10, 'guidelines');

        $this->assertSame(5, $count);
    }

    // =========================================================================
    // pendingSteps() — dual-check: row status AND domain validation
    // =========================================================================

    // ── no row exists ─────────────────────────────────────────────────────────

    public function test_pending_steps_returns_all_applicable_steps_when_no_rows_exist(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);

        // No step rows exist (default mock returns null for getStatus).
        $this->profileRepo->shouldReceive('findByUserId')->andReturn(null);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertCount(1, $pending);
        $this->assertSame('profile', $pending[0]['step']);
    }

    public function test_pending_steps_returns_step_with_pending_status_when_no_row(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn(null);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertSame(OnboardingStepStatus::Pending->value, $pending[0]['status']);
    }

    // ── row exists but step not completed ─────────────────────────────────────

    public function test_pending_steps_reports_in_progress_status_from_row(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::InProgress->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn(null);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertSame(OnboardingStepStatus::InProgress->value, $pending[0]['status']);
    }

    public function test_pending_steps_reports_invalidated_status_from_row(): void
    {
        $site     = $this->makeSite(['require_payment_setup' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $contract = $this->makeContract(['id' => 5, 'version' => 2]);

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'contract')
            ->andReturn(OnboardingStepStatus::Invalidated->value);

        // Profile step: completed row + valid domain.
        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $profile = $this->makeProfile();
        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->contractRepo->shouldReceive('latestPublishedForSite')->andReturn($contract);
        $this->contractRepo->shouldReceive('hasSigned')->with(1, 5)->andReturn(false);

        $pending = $this->service->pendingSteps(1, $site);

        $contractStep = collect($pending)->firstWhere('step', 'contract');
        $this->assertNotNull($contractStep);
        $this->assertSame(OnboardingStepStatus::Invalidated->value, $contractStep['status']);
    }

    // ── row completed + domain passes → step is done ──────────────────────────

    public function test_pending_steps_excludes_step_when_row_completed_and_domain_passes(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $profile = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertEmpty($pending);
    }

    public function test_pending_steps_returns_empty_when_all_steps_completed_and_valid(): void
    {
        $site     = $this->makeSite(['guidelines_version' => 2]);
        $profile  = $this->makeProfile();
        $contract = $this->makeContract(['id' => 3, 'version' => 1]);

        foreach (['profile', 'payment', 'contract', 'guidelines', 'age_verification'] as $step) {
            $this->stepRepo->shouldReceive('getStatus')
                ->with(1, $site->id, $step)
                ->andReturn(OnboardingStepStatus::Completed->value);
        }

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->contractRepo->shouldReceive('latestPublishedForSite')->andReturn($contract);
        $this->contractRepo->shouldReceive('hasSigned')->andReturn(true);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(2);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertEmpty($pending);
    }

    // ── row completed + domain fails → stale row invalidated ──────────────────

    public function test_pending_steps_invalidates_stale_contract_row_when_new_contract_published(): void
    {
        // Scenario: user completed contract step for contract v1.
        // Admin publishes contract v2. User has NOT signed v2.
        // pendingSteps() should detect the stale row and invalidate it.
        $site        = $this->makeSite(['require_payment_setup' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $newContract = $this->makeContract(['id' => 6, 'version' => 2]);
        $profile     = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'contract')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->contractRepo->shouldReceive('latestPublishedForSite')->andReturn($newContract);
        $this->contractRepo->shouldReceive('hasSigned')->with(1, 6)->andReturn(false);

        // The stale row must be invalidated.
        $this->stepRepo->shouldReceive('markInvalidated')
            ->once()
            ->with(1, $site->id, 'contract');

        $pending = $this->service->pendingSteps(1, $site);

        $contractStep = collect($pending)->firstWhere('step', 'contract');
        $this->assertNotNull($contractStep);
        $this->assertSame(OnboardingStepStatus::Invalidated->value, $contractStep['status']);
    }

    public function test_pending_steps_invalidates_stale_guidelines_row_when_version_bumped(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_age_verification' => false, 'guidelines_version' => 3]);
        $profile = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'guidelines')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        // User only acknowledged version 1, but site is now on version 3.
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $this->stepRepo->shouldReceive('markInvalidated')
            ->once()
            ->with(1, $site->id, 'guidelines');

        $pending = $this->service->pendingSteps(1, $site);

        $guidelinesStep = collect($pending)->firstWhere('step', 'guidelines');
        $this->assertNotNull($guidelinesStep);
        $this->assertSame(OnboardingStepStatus::Invalidated->value, $guidelinesStep['status']);
    }

    public function test_pending_steps_invalidates_stale_payment_row_when_payment_revoked(): void
    {
        $site    = $this->makeSite(['require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $profile = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'payment')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        // Payment was revoked since completion.
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(false);

        $this->stepRepo->shouldReceive('markInvalidated')
            ->once()
            ->with(1, $site->id, 'payment');

        $pending = $this->service->pendingSteps(1, $site);

        $paymentStep = collect($pending)->firstWhere('step', 'payment');
        $this->assertNotNull($paymentStep);
        $this->assertSame(OnboardingStepStatus::Invalidated->value, $paymentStep['status']);
    }

    public function test_pending_steps_invalidates_stale_age_row_when_dob_removed(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'minimum_contributor_age' => 18]);
        $profile = $this->makeProfile(['date_of_birth' => null]);
        $profile->date_of_birth = null;

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'age_verification')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $this->stepRepo->shouldReceive('markInvalidated')
            ->once()
            ->with(1, $site->id, 'age_verification');

        $pending = $this->service->pendingSteps(1, $site);

        $ageStep = collect($pending)->firstWhere('step', 'age_verification');
        $this->assertNotNull($ageStep);
        $this->assertSame(OnboardingStepStatus::Invalidated->value, $ageStep['status']);
    }

    public function test_pending_steps_does_not_call_mark_invalidated_when_domain_still_valid(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $profile = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        // markInvalidated must NOT be called for a non-stale step.
        $this->stepRepo->shouldNotReceive('markInvalidated');

        $pending = $this->service->pendingSteps(1, $site);
        $this->assertEmpty($pending);
    }

    // ── step applicability is respected ──────────────────────────────────────

    public function test_pending_steps_excludes_steps_not_required_by_site(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $profile = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $pending = $this->service->pendingSteps(1, $site);

        $stepNames = array_column($pending, 'step');
        $this->assertNotContains('payment', $stepNames);
        $this->assertNotContains('contract', $stepNames);
        $this->assertNotContains('guidelines', $stepNames);
        $this->assertNotContains('age_verification', $stepNames);
    }

    // ── structured response keys ──────────────────────────────────────────────

    public function test_each_pending_step_has_required_keys(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn(null);

        foreach ($this->service->pendingSteps(1, $site) as $step) {
            $this->assertArrayHasKey('step',   $step);
            $this->assertArrayHasKey('status', $step);
            $this->assertArrayHasKey('reason', $step);
            $this->assertArrayHasKey('meta',   $step);
            $this->assertIsString($step['step']);
            $this->assertIsString($step['status']);
            $this->assertIsString($step['reason']);
            $this->assertIsArray($step['meta']);
        }
    }

    public function test_pending_steps_reason_is_non_empty_for_each_pending_step(): void
    {
        $site = $this->makeSite();

        $this->profileRepo->shouldReceive('findByUserId')->andReturn(null);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(false);
        $this->contractRepo->shouldReceive('latestPublishedForSite')->andReturn($this->makeContract());
        $this->contractRepo->shouldReceive('hasSigned')->andReturn(false);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(0);

        foreach ($this->service->pendingSteps(1, $site) as $step) {
            $this->assertNotEmpty($step['reason'], "Step [{$step['step']}] has an empty reason.");
        }
    }

    // =========================================================================
    // completedSteps()
    // =========================================================================

    public function test_completed_steps_returns_only_applicable_completed_steps(): void
    {
        // Site requires profile, guidelines (no payment, no contract, no age).
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_age_verification' => false, 'guidelines_version' => 1]);
        $profile = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')->with(1, $site->id, 'profile')->andReturn(OnboardingStepStatus::Completed->value);
        $this->stepRepo->shouldReceive('getStatus')->with(1, $site->id, 'guidelines')->andReturn(OnboardingStepStatus::Completed->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $completed = $this->service->completedSteps(1, $site);

        $this->assertContains('profile', $completed);
        $this->assertContains('guidelines', $completed);
        $this->assertNotContains('payment', $completed);
        $this->assertNotContains('contract', $completed);
        $this->assertNotContains('age_verification', $completed);
    }

    public function test_completed_steps_excludes_steps_that_are_pending_in_step_table(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $profile = $this->makeProfile(['bio' => '']);

        // Row for profile exists but status is in_progress, not completed.
        $this->stepRepo->shouldReceive('getStatus')->with(1, $site->id, 'profile')->andReturn(OnboardingStepStatus::InProgress->value);
        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $completed = $this->service->completedSteps(1, $site);

        $this->assertNotContains('profile', $completed);
    }

    public function test_completed_steps_excludes_stale_completed_rows(): void
    {
        // Scenario: payment row says completed but isPaymentSetup now returns false.
        $site    = $this->makeSite(['require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $profile = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')->with(1, $site->id, 'profile')->andReturn(OnboardingStepStatus::Completed->value);
        $this->stepRepo->shouldReceive('getStatus')->with(1, $site->id, 'payment')->andReturn(OnboardingStepStatus::Completed->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(false);

        $this->stepRepo->shouldReceive('markInvalidated')->byDefault();

        $completed = $this->service->completedSteps(1, $site);

        $this->assertNotContains('payment', $completed);
    }

    // =========================================================================
    // markStepInProgress()
    // =========================================================================

    public function test_mark_step_in_progress_throws_for_unknown_step(): void
    {
        $site = $this->makeSite();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown onboarding step/');

        $this->service->markStepInProgress(1, $site, 'bogus');
    }

    public function test_mark_step_in_progress_throws_when_step_not_applicable(): void
    {
        $site = $this->makeSite(['require_contracts' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not applicable/');

        $this->service->markStepInProgress(1, $site, 'contract');
    }

    public function test_mark_step_in_progress_delegates_to_repository(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);

        $this->stepRepo->shouldReceive('markInProgress')
            ->once()
            ->with(1, $site->id, 'profile');

        $this->service->markStepInProgress(1, $site, 'profile');
        $this->assertTrue(true);
    }

    // =========================================================================
    // completeProfileStep() — backwards-compat wrapper
    // =========================================================================

    public function test_complete_profile_step_returns_error_when_bio_missing(): void
    {
        $site    = $this->makeSite();
        $profile = $this->makeProfile(['bio' => '']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $result = $this->service->completeProfileStep(1, $site);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('bio', $result['errors']);
    }

    public function test_complete_profile_step_returns_error_when_bio_too_short(): void
    {
        $site    = $this->makeSite();
        $profile = $this->makeProfile(['bio' => 'Short.']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $result = $this->service->completeProfileStep(1, $site);

        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('bio', $result['errors']);
    }

    public function test_complete_profile_step_calls_complete_step_on_success(): void
    {
        $site = $this->makeSite([
            'require_payment_setup'    => false,
            'require_contracts'        => false,
            'require_guidelines_ack'   => false,
            'require_age_verification' => false,
        ]);

        $profile = $this->makeProfile([
            'bio' => 'A valid bio that is long enough.',
        ]);

        $this->profileRepo
            ->shouldReceive('findByUserId')
            ->atLeast()->once()->with(1)
            ->andReturn($profile);

        $this->stepRepo
            ->shouldReceive('markCompleted')
            ->once()
            ->with(1, $site->id, 'profile', null);

        $this->stepRepo
            ->shouldReceive('getStatus')
            ->atLeast()->once()
            ->with(1, $site->id, 'profile')
            ->andReturn(OnboardingStepStatus::Completed->value);

        $this->contributorOnboardingRepository
            ->shouldReceive('syncStatus')
            ->once()
            ->with(1, $site, true);

        $result = $this->service->completeProfileStep(1, $site);

        $this->assertTrue($result['ok']);
    }

    // =========================================================================
    // isComplete()
    // =========================================================================

    public function test_is_complete_returns_true_when_no_pending_steps(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $profile = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')->with(1, $site->id, 'profile')->andReturn(OnboardingStepStatus::Completed->value);
        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $this->assertTrue($this->service->isComplete(1, $site));
    }

    public function test_is_complete_returns_false_when_any_step_pending(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn(null);

        $this->assertFalse($this->service->isComplete(1, $site));
    }

    // =========================================================================
    // requireComplete()
    // =========================================================================

    public function test_require_complete_throws_when_steps_pending(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn(null);

        $this->expectException(OnboardingIncompleteException::class);

        $this->service->requireComplete(1, $site);
    }

    public function test_require_complete_exception_carries_pending_steps(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn(null);

        try {
            $this->service->requireComplete(1, $site);
            $this->fail('Expected OnboardingIncompleteException');
        } catch (OnboardingIncompleteException $e) {
            $steps = $e->getPendingSteps();
            $this->assertNotEmpty($steps);
            $this->assertArrayHasKey('step',   $steps[0]);
            $this->assertArrayHasKey('status', $steps[0]);
            $this->assertArrayHasKey('reason', $steps[0]);
            $this->assertArrayHasKey('meta',   $steps[0]);
        }
    }

    public function test_require_complete_does_not_throw_when_all_steps_done(): void
    {
        $site    = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);
        $profile = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')->with(1, $site->id, 'profile')->andReturn(OnboardingStepStatus::Completed->value);
        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);

        $this->service->requireComplete(1, $site);

        $this->assertTrue(true);
    }

    // =========================================================================
    // Contract domain — no published contract edge case
    // =========================================================================

    public function test_contract_step_is_complete_when_no_published_contract_exists(): void
    {
        // No published contract on the site → nothing to sign → domain passes.
        $site = $this->makeSite(['require_payment_setup' => false, 'require_guidelines_ack' => false, 'require_age_verification' => false]);

        $profile = $this->makeProfile();

        $this->stepRepo->shouldReceive('getStatus')->with(1, $site->id, 'profile')->andReturn(OnboardingStepStatus::Completed->value);
        $this->stepRepo->shouldReceive('getStatus')->with(1, $site->id, 'contract')->andReturn(OnboardingStepStatus::Completed->value);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->contractRepo->shouldReceive('latestPublishedForSite')->andReturn(null);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertEmpty(array_filter($pending, fn($p) => $p['step'] === 'contract'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSite(array $attributes = []): Site
    {
        $defaults = [
            'id'                       => 10,
            'require_payment_setup'    => true,
            'require_contracts'        => true,
            'require_guidelines_ack'   => true,
            'guidelines_version'       => 1,
            'require_age_verification' => true,
            'minimum_contributor_age'  => 18,
        ];

        $site = Mockery::mock(Site::class)->makePartial();
        $site->exists = true;
        $site->fill(array_merge($defaults, $attributes));

        return $site;
    }

    private function makeProfile(array $attributes = []): ContributorProfile
    {
        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->bio            = $attributes['bio'] ?? 'A valid contributor bio with enough length.';
        $profile->date_of_birth  = $attributes['date_of_birth']
            ?? (new DateTimeImmutable('-20 years', new DateTimeZone('UTC')))->format('Y-m-d');

        return $profile;
    }

    private function makeContract(array $attributes = []): Contract
    {
        $contract = Mockery::mock(Contract::class)->makePartial();
        $contract->id      = $attributes['id'] ?? 1;
        $contract->version = $attributes['version'] ?? 1;
        return $contract;
    }

    private function mockStepStatuses(int $userId, Site $site, array $statuses): void
    {
        foreach ($statuses as $step => $status) {
            $this->stepRepo
                ->shouldReceive('getStatus')
                ->with($userId, $site->id, $step)
                ->once()
                ->andReturn($status);
        }
    }
}