<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Models\Campaign;
use App\Repositories\MemberInsights\CampaignExecutionRepository;
use App\Services\MemberInsights\Campaigns\CampaignCooldownChecker;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CampaignCooldownCheckerTest extends TestCase
{
    private CampaignExecutionRepository|MockInterface $campaignExecutionRepository;
    private CampaignCooldownChecker $checker;

    public function test_is_eligible_when_no_prior_execution_exists(): void
    {
        $campaign = $this->makeCampaign(id: 1, cooldownHours: 48);

        $this->campaignExecutionRepository->shouldReceive('hasRecentExecution')
            ->once()
            ->with(99, 1, Mockery::type(\DateTimeInterface::class))
            ->andReturn(false);

        $this->assertTrue($this->checker->isEligible(memberId: 99, campaign: $campaign));
    }

    private function makeCampaign(int $id, int $cooldownHours): Campaign
    {
        $campaign = Mockery::mock(Campaign::class)->makePartial();
        $campaign->id = $id;
        $campaign->cooldown_hours = $cooldownHours;
        return $campaign;
    }

    public function test_is_not_eligible_when_within_cooldown_window(): void
    {
        $campaign = $this->makeCampaign(id: 1, cooldownHours: 48);

        $this->campaignExecutionRepository->shouldReceive('hasRecentExecution')
            ->once()
            ->with(99, 1, Mockery::type(\DateTimeInterface::class))
            ->andReturn(true);

        $this->assertFalse($this->checker->isEligible(memberId: 99, campaign: $campaign));
    }

    public function test_is_eligible_when_cooldown_has_expired(): void
    {
        $campaign = $this->makeCampaign(id: 1, cooldownHours: 48);

        $this->campaignExecutionRepository->shouldReceive('hasRecentExecution')
            ->once()
            ->with(99, 1, Mockery::type(\DateTimeInterface::class))
            ->andReturn(false);

        $this->assertTrue($this->checker->isEligible(memberId: 99, campaign: $campaign));
    }

    public function test_zero_cooldown_always_eligible_without_querying_db(): void
    {
        $campaign = $this->makeCampaign(id: 1, cooldownHours: 0);

        // The DB must NOT be queried when cooldown is 0
        $this->campaignExecutionRepository->shouldNotReceive('hasRecentExecution');

        $this->assertTrue($this->checker->isEligible(memberId: 99, campaign: $campaign));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->campaignExecutionRepository = Mockery::mock(CampaignExecutionRepository::class);
        $this->checker = new CampaignCooldownChecker($this->campaignExecutionRepository);
    }
}
