<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Models\Contract;
use App\Models\ContributorProfile;
use App\Models\Site;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\GuidelinesRepository;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class ContributorOnboardingServiceTest extends FunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private $profileRepo;
    private $contractRepo;
    private $guidelinesRepo;
    private ContributorOnboardingService $service;

    // ── pendingSteps() — structured response ──────────────────────────────────

    public function test_pending_steps_returns_profile_step_when_bio_missing(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false]);
        $profile = $this->makeProfile(['bio' => '']);

        $this->profileRepo->shouldReceive('findByUserId')->once()->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertCount(1, $pending);
        $this->assertEquals('profile', $pending[0]['step']);
        $this->assertNotEmpty($pending[0]['reason']);
        $this->assertIsArray($pending[0]['meta']);
    }

    public function test_pending_steps_returns_payment_step_when_not_setup(): void
    {
        $site = $this->makeSite(['require_payment_setup' => true, 'require_contracts' => false, 'require_guidelines_ack' => false]);
        $profile = $this->makeProfile(['bio' => 'ok']);

        $this->profileRepo->shouldReceive('findByUserId')->once()->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->once()->andReturn(false);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertCount(1, $pending);
        $this->assertEquals('payment', $pending[0]['step']);
        $this->assertNotEmpty($pending[0]['reason']);
    }

    public function test_pending_steps_returns_contract_step_when_not_signed(): void
    {
        $site = $this->makeSite(['id' => 10, 'require_payment_setup' => false, 'require_contracts' => true, 'require_guidelines_ack' => false]);
        $profile = $this->makeProfile(['bio' => 'ok']);
        $contract = $this->makeContract(['id' => 5, 'version' => 2]);

        $this->profileRepo->shouldReceive('findByUserId')->once()->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->once()->with(10)->andReturn($contract);
        $this->contractRepo->shouldReceive('hasSigned')->once()->with(1, 5)->andReturn(false);
        //$this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->once()->with(1, 10)->andReturn(1);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertCount(1, $pending);
        $this->assertEquals('contract', $pending[0]['step']);
        $this->assertNotEmpty($pending[0]['reason']);
        $this->assertArrayHasKey('contract_id', $pending[0]['meta']);
        $this->assertEquals(5, $pending[0]['meta']['contract_id']);
    }

    public function test_pending_steps_returns_guidelines_step_when_not_acknowledged(): void
    {
        $site = $this->makeSite(['id' => 10, 'require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => true, 'guidelines_version' => 3]);
        $profile = $this->makeProfile(['bio' => 'ok']);

        $this->profileRepo->shouldReceive('findByUserId')->once()->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->once()->with(1, 10)->andReturn(1);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertCount(1, $pending);
        $this->assertEquals('guidelines', $pending[0]['step']);
        $this->assertArrayHasKey('required_version', $pending[0]['meta']);
        $this->assertEquals(3, $pending[0]['meta']['required_version']);
    }

    public function test_pending_steps_returns_empty_when_all_complete(): void
    {
        $site = $this->makeSite(['id' => 10, 'require_payment_setup' => true, 'require_contracts' => true, 'require_guidelines_ack' => true, 'guidelines_version' => 2]);
        $profile = $this->makeProfile(['bio' => 'ok']);
        $contract = $this->makeContract(['id' => 5]);

        $this->profileRepo->shouldReceive('findByUserId')->once()->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->once()->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->once()->andReturn($contract);
        $this->contractRepo->shouldReceive('hasSigned')->once()->andReturn(true);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->once()->andReturn(2);

        $this->assertEquals([], $this->service->pendingSteps(1, $site));
    }

    public function test_pending_steps_every_entry_has_required_keys(): void
    {
        $site = $this->makeSite(['require_payment_setup' => true, 'require_contracts' => true, 'id' => 10]);
        $profile = $this->makeProfile(['bio' => '']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(false);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        foreach ($this->service->pendingSteps(1, $site) as $step) {
            $this->assertArrayHasKey('step', $step, 'each entry must have step');
            $this->assertArrayHasKey('reason', $step, 'each entry must have reason');
            $this->assertArrayHasKey('meta', $step, 'each entry must have meta');
            $this->assertIsString($step['step']);
            $this->assertIsString($step['reason']);
            $this->assertIsArray($step['meta']);
        }
    }

    // ── NEW: acceptance criteria — invalidation on contract change ─────────────

    public function test_invalidates_onboarding_when_contract_changes(): void
    {
        // Scenario: user has completed onboarding, then a new contract is published.
        // isComplete() must return false and pendingSteps() must include 'contract'.
        $site = $this->makeSite(['id' => 10, 'require_payment_setup' => true, 'require_contracts' => true, 'require_guidelines_ack' => true, 'guidelines_version' => 1]);
        $profile = $this->makeProfile(['bio' => 'ok']);

        // New contract (id=6) has been published — user only signed id=5.
        $newContract = $this->makeContract(['id' => 6, 'version' => 2]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn($newContract);
        $this->contractRepo->shouldReceive('hasSigned')->with(1, 6)->andReturn(false);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $this->assertFalse($this->service->isComplete(1, $site));

        $pending = $this->service->pendingSteps(1, $site);

        $stepNames = array_column($pending, 'step');
        $this->assertContains('contract', $stepNames);
    }

    public function test_invalidates_onboarding_when_guidelines_version_increases(): void
    {
        // Scenario: user acknowledged v1, site is now on v2.
        $site = $this->makeSite(['id' => 10, 'require_payment_setup' => true, 'require_contracts' => true, 'require_guidelines_ack' => true, 'guidelines_version' => 2]);
        $profile = $this->makeProfile(['bio' => 'ok']);
        $contract = $this->makeContract(['id' => 5]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn($contract);
        $this->contractRepo->shouldReceive('hasSigned')->andReturn(true);
        // User only acknowledged version 1, site is now on version 2.
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $this->assertFalse($this->service->isComplete(1, $site));

        $pending = $this->service->pendingSteps(1, $site);

        $stepNames = array_column($pending, 'step');
        $this->assertContains('guidelines', $stepNames);
    }

    public function test_no_db_recalculation_needed_for_correctness(): void
    {
        // pendingSteps() derives truth at runtime — even with a stale status snapshot,
        // the result is always accurate. This test proves that by checking two different
        // contract states without touching any status column.
        $site = $this->makeSite(['id' => 10, 'require_payment_setup' => false, 'require_contracts' => true, 'require_guidelines_ack' => false]);
        $profile = $this->makeProfile(['bio' => 'ok']);

        $oldContract = $this->makeContract(['id' => 5]);
        $newContract = $this->makeContract(['id' => 6]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        // First: user has signed the current contract → complete.
        $this->contractRepo->shouldReceive('latestForSite')->once()->andReturn($oldContract);
        $this->contractRepo->shouldReceive('hasSigned')->with(1, 5)->once()->andReturn(true);
        $this->assertTrue($this->service->isComplete(1, $site));

        // Then: new contract published → immediately incomplete, no DB status update needed.
        $this->contractRepo->shouldReceive('latestForSite')->once()->andReturn($newContract);
        $this->contractRepo->shouldReceive('hasSigned')->with(1, 6)->once()->andReturn(false);
        $this->assertFalse($this->service->isComplete(1, $site));
    }

    // ── completedSteps() — derived from requirements ──────────────────────────

    public function test_completed_steps_excludes_steps_not_required_by_site(): void
    {
        // Site does not require payment or contracts → only profile and guidelines exist.
        $site = $this->makeSite(['id' => 10, 'require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => true, 'guidelines_version' => 1]);
        $profile = $this->makeProfile(['bio' => 'ok']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $completed = $this->service->completedSteps(1, $site);

        $this->assertContains('profile', $completed);
        $this->assertContains('guidelines', $completed);
        // These steps are not required — must not appear as completed.
        $this->assertNotContains('payment', $completed);
        $this->assertNotContains('contract', $completed);
    }

    public function test_completed_steps_excludes_pending_steps(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false, 'id' => 10]);
        $profile = $this->makeProfile(['bio' => '']); // profile is pending

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $completed = $this->service->completedSteps(1, $site);

        $this->assertNotContains('profile', $completed);
    }

    // ── requireComplete() ─────────────────────────────────────────────────────

    public function test_require_complete_throws_when_steps_pending(): void
    {
        $site = $this->makeSite(['require_payment_setup' => true, 'require_contracts' => false, 'require_guidelines_ack' => false]);
        $profile = $this->makeProfile(['bio' => 'Hello']);

        $this->profileRepo->shouldReceive('findByUserId')->once()->with(1)->andReturn($profile);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(null);
        $this->profileRepo->shouldReceive('isPaymentSetup')->once()->with(1)->andReturn(false);

        $this->expectException(OnboardingIncompleteException::class);
        $this->service->requireComplete(1, $site);
    }

    public function test_require_complete_exception_carries_structured_pending_steps(): void
    {
        $site = $this->makeSite(['require_payment_setup' => true, 'require_contracts' => false, 'require_guidelines_ack' => false]);
        $profile = $this->makeProfile(['bio' => 'Hello']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(false);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(null);

        try {
            $this->service->requireComplete(1, $site);
            $this->fail('Expected OnboardingIncompleteException');
        } catch (OnboardingIncompleteException $e) {
            $steps = $e->getPendingSteps();
            $this->assertNotEmpty($steps);
            $this->assertArrayHasKey('step', $steps[0]);
            $this->assertArrayHasKey('reason', $steps[0]);
            $this->assertArrayHasKey('meta', $steps[0]);
        }
    }

    public function test_require_complete_does_not_throw_when_fully_compliant(): void
    {
        $site = $this->makeSite(['id' => 10, 'require_payment_setup' => true, 'require_contracts' => true, 'require_guidelines_ack' => true, 'guidelines_version' => 2]);
        $profile = $this->makeProfile(['bio' => 'Full Bio']);
        $contract = $this->makeContract(['id' => 5]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn($contract);
        $this->contractRepo->shouldReceive('hasSigned')->andReturn(true);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(2);

        $this->service->requireComplete(1, $site);
        $this->assertTrue(true);
    }

    // ── isComplete() ──────────────────────────────────────────────────────────

    public function test_is_complete_returns_true_when_all_conditions_met(): void
    {
        $site = $this->makeSite(['id' => 10, 'require_payment_setup' => true, 'require_contracts' => true, 'require_guidelines_ack' => true, 'guidelines_version' => 2]);
        $profile = $this->makeProfile(['bio' => 'Full Bio']);
        $contract = $this->makeContract(['id' => 5]);

        $this->profileRepo->shouldReceive('findByUserId')->once()->with(1)->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->once()->with(1)->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->once()->with(10)->andReturn($contract);
        $this->contractRepo->shouldReceive('hasSigned')->once()->with(1, 5)->andReturn(true);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->once()->with(1, 10)->andReturn(2);

        $this->assertTrue($this->service->isComplete(1, $site));
    }

    public function test_is_complete_returns_false_when_steps_pending(): void
    {
        $site = $this->makeSite(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false]);
        $profile = $this->makeProfile(['bio' => '']);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);
        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $this->assertFalse($this->service->isComplete(1, $site));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeSite(array $attributes = []): Site
    {
        $defaults = [
            'id' => 10,
            'require_payment_setup' => true,
            'require_contracts' => true,
            'require_guidelines_ack' => true,
            'guidelines_version' => 1,
        ];
        $site = new Site(array_merge($defaults, $attributes));
        $site->exists = true;
        return $site;
    }

    private function makeProfile(array $attributes = []): ContributorProfile
    {
        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->bio = $attributes['bio'] ?? 'ok';
        return $profile;
    }

    private function makeContract(array $attributes = []): Contract
    {
        $contract = Mockery::mock(Contract::class)->makePartial();
        $contract->id = $attributes['id'] ?? 1;
        $contract->version = $attributes['version'] ?? 1;
        return $contract;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->profileRepo = Mockery::mock(ContributorProfileRepository::class);
        $this->contractRepo = Mockery::mock(ContractRepository::class);
        $this->guidelinesRepo = Mockery::mock(GuidelinesRepository::class);

        $this->service = new ContributorOnboardingService(
            $this->profileRepo,
            $this->contractRepo,
            $this->guidelinesRepo,
        );
    }
}