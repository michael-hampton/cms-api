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
    private $service;

    public function test_pending_steps_returns_profile_when_bio_missing()
    {
        $site = new Site([
            'require_payment_setup' => false,
            'require_contracts' => false,
            'require_guidelines_ack' => false,
        ]);

        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->bio = '';

        $this->profileRepo->shouldReceive('findByUserId')->once()->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true); // guard

        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertEquals(['profile'], $pending);
    }

    public function test_pending_steps_returns_payment_when_not_setup()
    {
        $site = new Site([
            'require_payment_setup' => true,
            'require_contracts' => false,
            'require_guidelines_ack' => false,
        ]);

        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->bio = 'ok';

        $this->profileRepo->shouldReceive('findByUserId')->once()->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->once()->andReturn(false);

        $this->contractRepo->shouldReceive('latestForSite')->andReturn(null);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->andReturn(1);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertEquals(['payment'], $pending);
    }

    public function test_pending_steps_returns_contract_when_not_signed()
    {
        $site = new Site([
            'id' => 10,
            'require_payment_setup' => false,
            'require_contracts' => true,
            'require_guidelines_ack' => false,
        ]);

        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->bio = 'ok';

        $contract = Mockery::mock(Contract::class)->makePartial();
        $contract->id = 5;

        $this->profileRepo->shouldReceive('findByUserId')->once()->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);

        $this->contractRepo->shouldReceive('latestForSite')->once()->with(10)->andReturn($contract);
        $this->contractRepo->shouldReceive('hasSigned')->once()->with(1, 5)->andReturn(false);
        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->once()->with(1, 10)->andReturn(1);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertEquals(['contract'], $pending);
    }

    public function test_pending_steps_returns_guidelines_when_not_acknowledged()
    {
        $site = new Site([
            'id' => 10,
            'require_payment_setup' => false,
            'require_contracts' => false,
            'require_guidelines_ack' => true,
            'guidelines_version' => 2,
        ]);

        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->bio = 'ok';

        $this->profileRepo->shouldReceive('findByUserId')->once()->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->andReturn(true);

        $contract = Mockery::mock(Contract::class)->makePartial();
        $contract->id = 1;

        $this->contractRepo->shouldReceive('latestForSite')->andReturn($contract);

        $this->contractRepo->shouldReceive('hasSigned')->andReturn(true);

        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')
            ->once()
            ->with(1, 10)
            ->andReturn(null);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertEquals(['guidelines'], $pending);
    }

    public function test_pending_steps_returns_empty_when_all_complete()
    {
        $site = new Site([
            'id' => 10,
            'require_payment_setup' => true,
            'require_contracts' => true,
            'require_guidelines_ack' => true,
            'guidelines_version' => 2,
        ]);

        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->bio = 'ok';

        $contract = Mockery::mock(Contract::class)->makePartial();
        $contract->id = 5;

        $this->profileRepo->shouldReceive('findByUserId')->once()->andReturn($profile);
        $this->profileRepo->shouldReceive('isPaymentSetup')->once()->andReturn(true);

        $this->contractRepo->shouldReceive('latestForSite')->once()->andReturn($contract);
        $this->contractRepo->shouldReceive('hasSigned')->once()->andReturn(true);

        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')->once()->andReturn(2);

        $pending = $this->service->pendingSteps(1, $site);

        $this->assertEquals([], $pending);
    }



    public function test_require_complete_throws_exception_if_steps_pending()
    {
        $site = new Site([
            'id' => 10,
            'require_payment_setup' => true,
            'require_contracts' => false,        // 👈 disable
            'require_guidelines_ack' => false,   // 👈 disable
        ]);

        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->bio = 'Hello';

        $this->profileRepo->shouldReceive('findByUserId')
            ->once()
            ->with(1)
            ->andReturn($profile);

        $this->contractRepo->shouldReceive('latestForSite')
            ->andReturn(null);

        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')
            ->andReturn(null);

        $this->profileRepo->shouldReceive('isPaymentSetup')
            ->once()
            ->with(1)
            ->andReturn(false);

        $this->expectException(OnboardingIncompleteException::class);

        $this->service->requireComplete(1, $site);
    }

    public function test_is_complete_returns_true_when_all_conditions_met()
    {
        $site = new Site([
            'id' => 10,
            'require_payment_setup' => true,
            'require_contracts' => true,
            'require_guidelines_ack' => true,
            'guidelines_version' => 2,
        ]);

        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        $profile->bio = 'Full Bio';

        $contract = Mockery::mock(Contract::class)->makePartial();
        $contract->id = 5; // 👈 IMPORTANT: must match hasSigned()

        $this->profileRepo->shouldReceive('findByUserId')
            ->once()
            ->with(1)
            ->andReturn($profile);

        $this->profileRepo->shouldReceive('isPaymentSetup')
            ->once()
            ->with(1)
            ->andReturn(true);

        $this->contractRepo->shouldReceive('latestForSite')
            ->once()
            ->with(10)
            ->andReturn($contract);

        $this->contractRepo->shouldReceive('hasSigned')
            ->once()
            ->with(1, 5)
            ->andReturn(true);

        $this->guidelinesRepo->shouldReceive('latestAcknowledgedVersion')
            ->once()
            ->with(1, 10)
            ->andReturn(2);

        $this->assertTrue($this->service->isComplete(1, $site));
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
            $this->guidelinesRepo
        );
    }
}