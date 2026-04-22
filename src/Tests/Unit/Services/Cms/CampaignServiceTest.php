<?php

namespace App\Tests\Unit\Services\Cms;

use App\DTO\Campaigns\SignupContext;
use App\Framework\Database\Database;
use App\Models\Campaign;
use App\Models\Newsletter;
use App\Models\Segment;
use App\Repositories\Cms\CampaignRepository;
use App\Repositories\Cms\CampaignSignupRepository;
use App\Repositories\MemberInsights\CampaignVariantRepository;
use App\Repositories\MemberInsights\SegmentRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Services\Cms\CampaignService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class CampaignServiceTest extends FunctionalTestCase
{
    private CampaignService $service;
    private CampaignRepository $campaignRepository;
    private NewsletterRepository $newsletterRepository;
    private CampaignSignupRepository $campaignSignupRepository;
    private SegmentRepository $segmentRepository;
    private CampaignVariantRepository $campaignVariantRepository;
    private Database $databaseMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->campaignRepository = Mockery::mock(CampaignRepository::class);
        $this->newsletterRepository = Mockery::mock(NewsletterRepository::class);
        $this->campaignSignupRepository = Mockery::mock(CampaignSignupRepository::class);
        $this->segmentRepository = Mockery::mock(SegmentRepository::class);
        $this->campaignVariantRepository = Mockery::mock(CampaignVariantRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new CampaignService(
            $this->campaignRepository,
            $this->newsletterRepository,
            $this->campaignSignupRepository,
            $this->segmentRepository,
            $this->campaignVariantRepository,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_campaign_for_signup_returns_active_campaign(): void
    {
        $campaign = $this->createMockCampaign([
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        $this->campaignRepository->shouldReceive('findValidForSignup')
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

        $this->campaignRepository->shouldReceive('findValidForSignup')
            ->with('inactive', $this->siteId)
            ->once()
            ->andReturn(null);

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

        $this->campaignRepository->shouldReceive('findValidForSignup')
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

        $this->assertTrue($result->success);
        $this->assertEquals(5, $result->newsletterId); // Campaign's newsletter used
        $this->assertEquals(1, $result->campaignId);
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

        $this->assertTrue($result->success);
        $this->assertEquals(10, $result->newsletterId);
        $this->assertNull($result->campaignId);
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

        $this->assertTrue($result->success);
        $this->assertEquals(1, $result->newsletterId);
        $this->assertNull($result->campaignId);
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

    public function test_track_campaign_signup_creates_record_and_increments_count(): void
    {
        $campaignId = 1;
        $siteId = 10;

        $campaign = Mockery::mock(Campaign::class)->makePartial();
        $campaign->id = $campaignId;
        $campaign->site_id = $siteId;

        // Campaign exists
        $this->campaignRepository->shouldReceive('find')
            ->with($campaignId)
            ->once()
            ->andReturn($campaign);

        // Transaction executes the closure immediately
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($closure) {
                $closure();
            });

        // Expect signup repository to create a record
        $this->campaignSignupRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) use ($campaignId, $siteId) {
                return $data['campaign_id'] === $campaignId
                    && $data['site_id'] === $siteId
                    && array_key_exists('user_id', $data)
                    && array_key_exists('email', $data);
            }));

        // Expect campaign signup count to increment
        $this->campaignRepository->shouldReceive('incrementSignupCount')
            ->once()
            ->with($campaignId);

        $signupContext = new SignupContext(
            '127.0.0.1',
            'PHPUnit',
            'https://example.com'
        );

        $result = $this->service->trackCampaignSignup($campaignId, 42, 'foo@example.com', $signupContext);
        $this->assertTrue($result['success']);
        $this->assertEquals(42, $result['user_id']);
        $this->assertEquals('foo@example.com', $result['email']);
        $this->assertEquals(1, $result['campaign_id']);
    }

    public function test_track_campaign_signup_no_campaign_does_nothing(): void
    {
        $campaignId = 999;

        $this->campaignRepository->shouldReceive('find')
            ->with($campaignId)
            ->once()
            ->andReturnNull();

        $this->campaignSignupRepository->shouldReceive('create')->never();
        $this->campaignRepository->shouldReceive('incrementSignupCount')->never();
        $this->databaseMock->shouldReceive('transaction')->never();

        $result = $this->service->trackCampaignSignup($campaignId, 42, 'foo@example.com');
        $this->assertFalse($result['success']);
    }

    public function test_create_persists_campaign_fields(): void
    {
        $payload = $this->payload();
        $campaign = $this->makeCampaign(3);

        $this->campaignRepository->allows('existsBySlugForSite')->with('win-back', 10)->andReturn(false);
        $this->segmentRepository->allows('find')->with(5)->andReturn(Mockery::mock(Segment::class)->makePartial());
        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $callback) => $callback());

        $this->campaignVariantRepository->expects('deleteForCampaign')->with(3)->once();

        $this->campaignRepository->expects('create')
            ->withArgs(fn(array $data) => $data['segment_id'] === 5 &&
                $data['cooldown_hours'] === 24 &&
                $data['priority'] === 90 &&
                !array_key_exists('variants', $data)  // variants must be stripped from campaign row
            )
            ->andReturn($campaign);

        $result = $this->service->create($payload, 10);

        $this->assertSame($campaign, $result);
    }

    public function test_create_persists_variants_in_same_transaction(): void
    {
        $payload = array_merge($this->payload(), [
            'variants' => [
                ['key' => 'A', 'weight' => 60, 'subject_line' => 'Hello A', 'template' => null],
                ['key' => 'B', 'weight' => 40, 'subject_line' => null, 'template' => null],
            ],
        ]);

        $campaign = $this->makeCampaign(3);

        $this->campaignRepository->allows('existsBySlugForSite')->andReturn(false);
        $this->segmentRepository->allows('find')->with(5)->andReturn(Mockery::mock(Segment::class)->makePartial());

        $this->databaseMock->allows('transaction')->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $this->campaignVariantRepository->expects('deleteForCampaign')->with(3)->once();
        $this->campaignVariantRepository->expects('create')->twice()->andReturn(Mockery::mock(\App\Models\CampaignVariant::class));

        $this->campaignRepository->expects('create')
            ->withArgs(fn(array $data) => !array_key_exists('variants', $data))
            ->andReturn($campaign);

        $result = $this->service->create($payload, 10);

        $this->assertSame($campaign, $result);
    }

    public function test_create_throws_when_variant_weights_dont_sum_to_100(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/weights must sum to 100/i');

        $payload = array_merge($this->payload(), [
            'variants' => [
                ['key' => 'A', 'weight' => 60],
                ['key' => 'B', 'weight' => 30], // 90 total, not 100
            ],
        ]);

        $this->campaignRepository->allows('existsBySlugForSite')->andReturn(false);
        $this->segmentRepository->allows('find')->with(5)->andReturn(Mockery::mock(Segment::class)->makePartial());

        $this->service->create($payload, 10);
    }

    public function test_create_throws_when_variant_keys_are_duplicate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unique/i');

        $payload = array_merge($this->payload(), [
            'variants' => [
                ['key' => 'A', 'weight' => 50],
                ['key' => 'A', 'weight' => 50], // duplicate
            ],
        ]);

        $this->campaignRepository->allows('existsBySlugForSite')->andReturn(false);
        $this->segmentRepository->allows('find')->with(5)->andReturn(Mockery::mock(Segment::class)->makePartial());

        $this->service->create($payload, 10);
    }

    public function test_update_replaces_variants_when_provided(): void
    {
        $campaign = $this->makeCampaign(3);
        $campaign->site_id = 10;

        $updatedCampaign = $this->makeCampaign(3);

        $this->campaignRepository->shouldReceive('findForSite')
            ->with(3, 10)
            ->andReturnValues([$campaign, $updatedCampaign]);

        $this->campaignRepository->allows('existsBySlugForSite')->andReturn(false);
        $this->campaignRepository->allows('update')->with(3, Mockery::any());
        $this->segmentRepository->allows('find')->with(5)->andReturn(Mockery::mock(Segment::class)->makePartial());

        $this->databaseMock->allows('transaction')->andReturnUsing(function (callable $callback) {
            return $callback();
        });

        $this->campaignVariantRepository->expects('deleteForCampaign')->with(3)->once();
        $this->campaignVariantRepository->expects('create')->twice()->andReturn(Mockery::mock(\App\Models\CampaignVariant::class));

        $result = $this->service->update(3, array_merge($this->payload(), [
            'variants' => [
                ['key' => 'A', 'weight' => 70],
                ['key' => 'B', 'weight' => 30],
            ],
        ]), 10);

        $this->assertSame($updatedCampaign, $result);
    }

    public function test_update_leaves_variants_untouched_when_not_provided(): void
    {
        $campaign = $this->makeCampaign(3);
        $campaign->site_id = 10;
        $updatedCampaign = $this->makeCampaign(3);

        $this->campaignRepository->allows('findForSite')->andReturn($campaign, $updatedCampaign);
        $this->campaignRepository->allows('existsBySlugForSite')->andReturn(false);
        $this->campaignRepository->allows('update');
        $this->segmentRepository->allows('find')->with(5)->andReturn(Mockery::mock(Segment::class)->makePartial());

        $this->databaseMock->allows('transaction')->andReturnUsing(fn(callable $cb) => $cb());

        $this->campaignVariantRepository->shouldNotReceive('deleteForCampaign');

        $result = $this->service->update(3, $this->payload(), 10);

        $this->assertNotNull($result);
    }

    public function test_create_throws_when_segment_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->campaignRepository->allows('existsBySlugForSite')->andReturn(false);
        $this->segmentRepository->allows('find')->with(5)->andReturn(null);

        $this->service->create($this->payload(), 10);
    }

    public function test_create_throws_when_invalid_template(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->campaignRepository->allows('existsBySlugForSite')->andReturn(false);
        $this->segmentRepository->allows('find')->with(5)->andReturn(Mockery::mock(Segment::class));

        $this->service->create(array_merge($this->payload(), ['template' => 'bac']), 10);
    }

    private function payload(): array
    {
        return [
            'name' => 'Win Back',
            'slug' => 'win-back',
            'segment_id' => 5,
            'channel' => 'email',
            'fallback_channels' => ['push'],
            'template' => 'App\\Mail\\Campaigns\\WeMissYouMail',
            'cooldown_hours' => 24,
            'priority' => 90,
        ];
    }

    private function makeCampaign(int $id): Campaign
    {
        $campaign = Mockery::mock(Campaign::class)->makePartial();
        $campaign->id = $id;
        $campaign->name = 'Win Back';
        return $campaign;
    }
}