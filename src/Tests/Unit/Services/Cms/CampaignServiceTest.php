<?php

namespace App\Tests\Unit\Services\Cms;

use App\Models\Campaign;
use App\Models\Newsletter;
use App\Repositories\Cms\CampaignRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Cms\CampaignService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class CampaignServiceTest extends FunctionalTestCase
{
    private CampaignService $service;
    private $campaignRepository;
    private $newsletterRepository;

    public function test_get_campaign_for_signup_returns_active_campaign(): void
    {
        $campaign = $this->createMockCampaign([
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        $this->campaignRepository->shouldReceive('findBySlug')
            ->with('test-campaign', $this->siteId)
            ->once()
            ->andReturn($campaign);

        $result = $this->service->getCampaignForSignup('test-campaign', $this->siteId);

        $this->assertNotNull($result);
        $this->assertEquals($campaign->id, $result->id);
    }

    private function createMockCampaign(array $attributes, bool $mockIsActive = false)
    {
        $defaults = [
            'id' => 1,
            'site_id' => $this->siteId,
            'name' => 'Test Campaign',
            'slug' => 'test-campaign',
            'is_active' => true,
            'gates_premium_content' => false,
            'newsletter_id' => null,
            'start_date' => null,
            'end_date' => null
        ];

        $data = array_merge($defaults, $attributes);

        $campaign = Mockery::mock(Campaign::class)->makePartial();

        foreach ($data as $key => $value) {
            $campaign->$key = $value;
        }

        if ($mockIsActive) {
            $isActive = !$campaign->end_date && $campaign->end_date < time();
            $campaign->shouldReceive('isActive')->andReturn($isActive);
            $campaign->shouldReceive('hasEnded')->andReturn(!$isActive);
        } else {
            $campaign->shouldReceive('isActive')->andReturn($data['is_active']);
            $campaign->shouldReceive('hasEnded')->andReturn(false);
        }

        $campaign->shouldReceive('gatesPremiumContent')
            ->andReturn($data['gates_premium_content']);

        return $campaign;
    }

    public function test_get_campaign_for_signup_returns_null_for_inactive(): void
    {
        $campaign = $this->createMockCampaign([
            'is_active' => false
        ]);

        $this->campaignRepository->shouldReceive('findBySlug')
            ->with('inactive', $this->siteId)
            ->once()
            ->andReturn($campaign);

        $result = $this->service->getCampaignForSignup('inactive', $this->siteId);

        $this->assertNull($result);
    }

    public function test_validate_campaign_returns_errors_for_ended(): void
    {
        $campaign = $this->createMockCampaign([
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ], true);

        $result = $this->service->validateCampaign($campaign);

        $this->assertFalse($result['valid']);
        $this->assertContains('Campaign has ended', $result['errors']);
    }

    public function test_resolve_campaign_prioritizes_campaign_over_newsletter(): void
    {
        $campaign = $this->createMockCampaign([
            'id' => 1,
            'newsletter_id' => 5,
            'is_active' => true
        ]);

        $this->campaignRepository->shouldReceive('findBySlug')
            ->with('test-campaign', $this->siteId)
            ->once()
            ->andReturn($campaign);

        $this->newsletterRepository->shouldReceive('find')
            ->with(5)
            ->once()
            ->andReturn($this->createMockNewsletter(['id' => 5, 'active' => true]));

        $result = $this->service->resolveCampaignOrNewsletter(
            'test-campaign',
            10, // Different newsletter ID
            $this->siteId
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['newsletter_id']); // Campaign's newsletter used
        $this->assertEquals(1, $result['campaign_id']);
    }

    private function createMockNewsletter(array $attributes)
    {
        $defaults = [
            'id' => 1,
            'active' => true
        ];

        $data = array_merge($defaults, $attributes);

        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        foreach ($data as $key => $value) {
            $newsletter->$key = $value;
        }

        return $newsletter;
    }

    public function test_resolve_uses_newsletter_id_when_no_campaign(): void
    {
        $this->campaignRepository->shouldReceive('findBySlug')
            ->with(null, $this->siteId)
            ->never();

        $result = $this->service->resolveCampaignOrNewsletter(
            null,
            10,
            $this->siteId
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['newsletter_id']);
        $this->assertNull($result['campaign_id']);
    }

    public function test_resolve_uses_default_newsletter_when_nothing_provided(): void
    {
        $defaultNewsletter = $this->createMockNewsletter(['id' => 1]);

        $this->newsletterRepository->shouldReceive('getDefaultNewsletterForSite')
            ->with($this->siteId)
            ->once()
            ->andReturn($defaultNewsletter);

        $result = $this->service->resolveCampaignOrNewsletter(
            null,
            null,
            $this->siteId
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['newsletter_id']);
        $this->assertNull($result['campaign_id']);
    }

    public function test_can_access_premium_content_returns_true_for_gating_campaign(): void
    {
        $campaign = $this->createMockCampaign([
            'id' => 1,
            'site_id' => $this->siteId,
            'gates_premium_content' => true,
            'is_active' => true
        ]);

        $this->campaignRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($campaign);

        $result = $this->service->canAccessPremiumContent(1, $this->siteId);

        $this->assertTrue($result);
    }

    public function test_can_access_premium_content_returns_false_for_non_gating(): void
    {
        $campaign = $this->createMockCampaign([
            'id' => 1,
            'site_id' => $this->siteId,
            'gates_premium_content' => false,
            'is_active' => true
        ]);

        $this->campaignRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($campaign);

        $result = $this->service->canAccessPremiumContent(1, $this->siteId);

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaignRepository = Mockery::mock(CampaignRepository::class);
        $this->newsletterRepository = Mockery::mock(NewsletterRepository::class);

        $this->service = new CampaignService(
            $this->campaignRepository,
            $this->newsletterRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}