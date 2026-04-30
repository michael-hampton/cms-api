<?php
// src/Tests/Unit/Repositories/CampaignRepositoryTest.php

namespace App\Tests\Unit\Repositories\Cms;

use App\Enums\Campaigns\CampaignScheduleStatus;
use App\Models\Campaign;
use App\Repositories\Cms\CampaignRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class CampaignRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private CampaignRepository $repository;

    public function test_find_by_slug_returns_campaign(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Test Campaign',
            'slug' => 'test-campaign',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        $result = $this->repository->findBySlug('test-campaign', $this->siteId);

        $this->assertNotNull($result);
        $this->assertEquals($campaign->id, $result->id);
    }

    public function test_get_active_campaigns_returns_only_active(): void
    {
        $newsletter = $this->createNewsletter();

        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Active Campaign',
            'slug' => 'active',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Inactive Campaign',
            'slug' => 'inactive',
            'newsletter_id' => $newsletter->id,
            'is_active' => false
        ]);

        $result = $this->repository->getActiveCampaigns($this->siteId);

        $this->assertCount(1, $result);
        $this->assertEquals('active', $result->first()->slug);
    }

    public function test_get_active_campaigns_filters_by_date_range(): void
    {
        $newsletter = $this->createNewsletter();

        // Future campaign
        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Future Campaign',
            'slug' => 'future',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        // Current campaign
        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Current Campaign',
            'slug' => 'current',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        // Ended campaign
        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Ended Campaign',
            'slug' => 'ended',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        $result = $this->repository->getActiveCampaigns($this->siteId);

        $this->assertCount(1, $result);
        $this->assertEquals('current', $result->first()->slug);
    }

    public function test_clone_for_site_creates_copy(): void
    {
        $newsletter = $this->createNewsletter();
        $targetSite = $this->createSite();

        $original = Campaign::create([
            'site_id' => $targetSite->id,
            'name' => 'Original Campaign',
            'slug' => 'original',
            'description' => 'Test description',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'gates_premium_content' => true,
            'tracking_params' => ['utm_source' => 'test']
        ]);

        $cloned = $this->repository->cloneForSite($original->id, $targetSite->id);

        $this->assertNotNull($cloned);
        $this->assertEquals($targetSite->id, $cloned->site_id);
        $this->assertStringContainsString('Copy', $cloned->name);
        $this->assertEquals('original-1', $cloned->slug);
        $this->assertNull($cloned->newsletter_id); // Newsletter not copied
        $this->assertFalse($cloned->is_active); // Starts inactive
        $this->assertTrue($cloned->gates_premium_content);
        $this->assertEquals(['utm_source' => 'test'], $cloned->tracking_params);
    }

    public function test_clone_handles_duplicate_slug(): void
    {
        $newsletter = $this->createNewsletter();
        $targetSite = $this->createSite();

        $original = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Original',
            'slug' => 'test-slug',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        // Create existing campaign with same slug in target site
        Campaign::create([
            'site_id' => $targetSite->id,
            'name' => 'Existing',
            'slug' => 'test-slug',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        $cloned = $this->repository->cloneForSite($original->id, $targetSite->id);

        $this->assertNotNull($cloned);
        $this->assertEquals('test-slug-1', $cloned->slug);
    }

    public function test_get_subscriber_count_returns_correct_count(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Test Campaign',
            'slug' => 'test',
            'newsletter_id' => $newsletter->id,
            'is_active' => true
        ]);

        // Create subscribers
        for ($i = 0; $i < 3; $i++) {
            $this->createSubscriber([
                'campaign_id' => $campaign->id,
                'email' => "test{$i}@example.com"
            ]);
        }

        $count = $this->repository->getSubscriberCount($campaign->id);

        $this->assertEquals(3, $count);
    }

    public function test_aggregate_stats_returns_correct_counts(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Stats Campaign',
            'slug' => 'stats-campaign',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
        ]);

        $variantA = \App\Models\CampaignVariant::create([
            'campaign_id' => $campaign->id,
            'key' => 'A',
            'weight' => 60,
        ]);
        $variantB = \App\Models\CampaignVariant::create([
            'campaign_id' => $campaign->id,
            'key' => 'B',
            'weight' => 40,
        ]);

        $member = $this->createMember();

        \App\Models\CampaignDelivery::create(['campaign_id' => $campaign->id, 'variant_id' => $variantA->id, 'member_id' => $member->id, 'channel' => 'email', 'token' => uniqid()]);
        \App\Models\CampaignDelivery::create(['campaign_id' => $campaign->id, 'variant_id' => $variantA->id, 'member_id' => $member->id, 'channel' => 'email', 'token' => uniqid()]);
        \App\Models\CampaignDelivery::create(['campaign_id' => $campaign->id, 'variant_id' => $variantA->id, 'member_id' => $member->id, 'channel' => 'email', 'token' => uniqid()]);
        \App\Models\CampaignDelivery::create(['campaign_id' => $campaign->id, 'variant_id' => $variantB->id, 'member_id' => $member->id, 'channel' => 'email', 'token' => uniqid()]);

        \App\Models\CampaignEvent::create(['campaign_id' => $campaign->id, 'variant_id' => $variantA->id, 'member_id' => $member->id, 'event_type' => 'open']);
        \App\Models\CampaignEvent::create(['campaign_id' => $campaign->id, 'variant_id' => $variantA->id, 'member_id' => $member->id, 'event_type' => 'open']);
        \App\Models\CampaignEvent::create(['campaign_id' => $campaign->id, 'variant_id' => $variantA->id, 'member_id' => $member->id, 'event_type' => 'click']);
        \App\Models\CampaignEvent::create(['campaign_id' => $campaign->id, 'variant_id' => $variantB->id, 'member_id' => $member->id, 'event_type' => 'open']);

        $variants = \App\Framework\Support\Collection::make([$variantA, $variantB]);
        $stats = $this->repository->aggregateStats($campaign->id, $variants);

        $byKey = array_column($stats, null, 'key');

        $this->assertArrayHasKey('A', $byKey);
        $this->assertArrayHasKey('B', $byKey);

        $a = $byKey['A'];
        $this->assertEquals(3, $a['deliveries']);
        $this->assertEquals(2, $a['opens']);
        $this->assertEquals(1, $a['clicks']);
        $this->assertEquals(66.67, $a['open_rate']);
        $this->assertEquals(33.33, $a['click_rate']);

        $b = $byKey['B'];
        $this->assertEquals(1, $b['deliveries']);
        $this->assertEquals(1, $b['opens']);
        $this->assertEquals(0, $b['clicks']);
        $this->assertEquals(100, $b['open_rate']);
        $this->assertEquals(0, $b['click_rate']);
    }

    public function test_aggregate_stats_returns_zeros_for_campaign_with_no_events(): void
    {
        $newsletter = $this->createNewsletter();

        $campaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Empty Campaign',
            'slug' => 'empty-campaign',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
        ]);

        $variant = \App\Models\CampaignVariant::create([
            'campaign_id' => $campaign->id,
            'key' => 'A',
            'weight' => 100,
        ]);

        $variants = \App\Framework\Support\Collection::make([$variant]);
        $stats = $this->repository->aggregateStats($campaign->id, $variants);

        $this->assertCount(1, $stats);
        $this->assertEquals('A', $stats[0]['key']);
        $this->assertEquals(0, $stats[0]['deliveries']);
        $this->assertEquals(0, $stats[0]['opens']);
        $this->assertEquals(0, $stats[0]['clicks']);
        $this->assertEquals(0, $stats[0]['open_rate']);
        $this->assertEquals(0, $stats[0]['click_rate']);
    }

    public function test_it_finds_only_due_scheduled_campaigns(): void
    {
        $newsletter = $this->createNewsletter();

        $now = date('Y-m-d H:i:s');

        // ✅ should be returned (past)
        $dueCampaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Due Campaign',
            'slug' => 'due',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'schedule_status' => CampaignScheduleStatus::Scheduled->value,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ]);

        // ❌ should NOT be returned (future)
        $futureCampaign = Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Future Campaign',
            'slug' => 'future',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'schedule_status' => CampaignScheduleStatus::Scheduled->value,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        ]);

        // ❌ wrong status
        Campaign::create([
            'site_id' => $this->siteId,
            'name' => 'Wrong Status Campaign',
            'slug' => 'wrong-status',
            'newsletter_id' => $newsletter->id,
            'is_active' => true,
            'schedule_status' => CampaignScheduleStatus::Sent->value,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ]);

        $result = $this->repository->findDueForDispatch();

        $this->assertCount(1, $result);
        $this->assertEquals($dueCampaign->id, $result->first()->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CampaignRepository();
    }
}