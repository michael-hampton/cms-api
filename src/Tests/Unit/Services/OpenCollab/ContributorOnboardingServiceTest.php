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

    public function test_pending_steps_returns_profile_when_bio_is_missing()
    {
        $site = new Site(['require_payment_setup' => false, 'require_contracts' => false, 'require_guidelines_ack' => false]);
        $user = Mockery::mock(ContributorProfile::class)->makePartial();
        $user->bio = '';

        $this->profileRepo->allows()->findByUserId(1)->andReturn($user);

        $pending = $this->service->pendingSteps(1, $site);
        $this->assertContains('profile', $pending);
    }

    public function test_require_complete_throws_exception_if_steps_pending()
    {
        $site = new Site(['require_payment_setup' => true]);

        $user = Mockery::mock(ContributorProfile::class)->makePartial();
        $user->bio = 'Hello';

        $this->profileRepo->allows()->findByUserId(1)->andReturn($user);
        $this->profileRepo->allows()->isPaymentSetup(1)->andReturn(false);

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
            'guidelines_version' => 2
        ]);

        $user = Mockery::mock(ContributorProfile::class)->makePartial();
        $user->bio = 'Full Bio';

        $contractor = Mockery::mock(Contract::class)->makePartial();
        $contractor->id = 1;

        $this->profileRepo->allows()->findByUserId(1)->andReturn($user);
        $this->profileRepo->allows()->isPaymentSetup(1)->andReturn(true);

        $this->contractRepo->allows()->latestForSite(10)->andReturn($contractor);
        $this->contractRepo->allows()->hasSigned(1, 5)->andReturn(true);

        $this->guidelinesRepo->allows()->latestAcknowledgedVersion(1, 10)->andReturn(2);

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