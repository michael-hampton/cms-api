<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Models\SubscriptionPremiumAccess;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriptionPlanPricingRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionListingService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionListingServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private SubscriptionListingService $service;
    private SubscriptionRepository $subscriptionRepository;
    private NewsletterRepository $newsletterRepository;

    public function testGetGroupedSubscriptionsGroupsByTypeAndStatus(): void
    {
        $member = $this->createMember();

        // Create active print subscription
        $activePrint = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Print Monthly',
            'status' => 'active',
            'delivery_type' => SubscriptionType::PRINTED->value,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        // Create active digital subscription
        $activeDigital = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Digital Monthly',
            'status' => 'active',
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 19.99,
            'currency' => 'USD'
        ]);

        // Create expired subscription
        $expired = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Old Plan',
            'status' => 'expired',
            'delivery_type' => SubscriptionType::PRINTED->value,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 25.00,
            'currency' => 'USD'
        ]);

        $grouped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        $this->assertArrayHasKey('active', $grouped);
        $this->assertArrayHasKey('expired', $grouped);
        $this->assertCount(1, $grouped['active']['print']);
        $this->assertCount(1, $grouped['active']['digital']);
        $this->assertCount(1, $grouped['expired']['print']);
    }

    public function testFormattedSubscriptionIncludesNewsletterAccess(): void
    {
        $member = $this->createMember();

        $newsletter = Newsletter::create([
            'title' => 'Insider Newsletter',
            'slug' => 'insider',
            'site_id' => $this->siteId,
            'active' => true,
            'interval' => 'weekly',
            'content' => '{}'
        ]);

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 39.99,
            'currency' => 'USD'
        ]);

        // Grant newsletter access
        SubscriptionPremiumAccess::create([
            'subscription_id' => $subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'is_active' => true,
            'granted_at' => date('Y-m-d H:i:s')
        ]);

        $grouped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        $this->assertNotEmpty($grouped['active']['digital']);
        $formatted = $grouped['active']['digital'][0];

        $this->assertArrayHasKey('newsletters', $formatted);
        $this->assertCount(1, $formatted['newsletters']);
        $this->assertEquals('Insider Newsletter', $formatted['newsletters'][0]['title']);
    }

    public function testCanRenewReturnsTrueForExpiredSubscription(): void
    {
        $member = $this->createMember();

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Expired Plan',
            'status' => 'expired',
            'delivery_type' => SubscriptionType::PRINTED->value,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 25.00,
            'currency' => 'USD'
        ]);

        $grouped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        $this->assertTrue($grouped['expired']['print'][0]['can_renew']);
    }

    public function testShouldShowRenewCTAReturnsFalseForActiveAutoRenew(): void
    {
        $member = $this->createMember();

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Auto Renew Plan',
            'status' => 'active',
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'auto_renew' => true,
            'price' => 19.99,
            'currency' => 'USD'
        ]);

        $grouped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        $this->assertFalse($grouped['active']['digital'][0]['should_show_renew']);
    }

    public function testGetSubscriptionSummaryReturnsCorrectCounts(): void
    {
        $member = $this->createMember();

        // Create 2 active
        for ($i = 0; $i < 2; $i++) {
            Subscription::create([
                'member_id' => $member->id,
                'site_id' => $this->siteId,
                'plan_name' => 'Active ' . $i,
                'status' => 'active',
                'delivery_type' => SubscriptionType::DIGITAL->value,
                'start_date' => date('Y-m-d H:i:s'),
                'price' => 19.99,
                'currency' => 'USD'
            ]);
        }

        // Create 1 expired
        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Expired',
            'status' => 'expired',
            'delivery_type' => SubscriptionType::PRINTED->value,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 25.00,
            'currency' => 'USD'
        ]);

        $summary = $this->service->getSubscriptionSummary($member->id, $this->siteId);

        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(2, $summary['active']);
        $this->assertEquals(1, $summary['expired']);
        $this->assertEquals(0, $summary['cancelled']);
    }

    public function testArchiveUrlIncludedForDigitalSubscriptions(): void
    {
        $member = $this->createMember();

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 19.99,
            'currency' => 'USD'
        ]);

        $grouped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        $this->assertNotNull($grouped['active']['digital'][0]['archive_url']);
        $this->assertStringContainsString('newsletters/archive', $grouped['active']['digital'][0]['archive_url']);
    }

    public function testPremiumAccessListIncludesAllActiveAccess(): void
    {
        $member = $this->createMember();

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium Bundle',
            'status' => 'active',
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 49.99,
            'currency' => 'USD'
        ]);

        // Grant multiple access types
        SubscriptionPremiumAccess::create([
            'subscription_id' => $subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'is_active' => true,
            'granted_at' => date('Y-m-d H:i:s')
        ]);

        SubscriptionPremiumAccess::create([
            'subscription_id' => $subscription->id,
            'premium_type' => 'archive',
            'premium_identifier' => 'full',
            'is_active' => true,
            'granted_at' => date('Y-m-d H:i:s')
        ]);

        $grouped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        $this->assertCount(2, $grouped['active']['digital'][0]['premium_access']);
    }

    public function test_global_account_lists_owned_subscriptions_across_sites_only(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();
        $otherSite = $this->createSite();

        $firstSubscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'First Publication',
            'status' => 'active',
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 10,
            'currency' => 'GBP',
        ]);
        $secondSubscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $otherSite->id,
            'plan_name' => 'Second Publication',
            'status' => 'active',
            'delivery_type' => SubscriptionType::PRINTED->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 20,
            'currency' => 'GBP',
        ]);
        Subscription::create([
            'member_id' => $otherMember->id,
            'site_id' => $otherSite->id,
            'plan_name' => 'Not Owned',
            'status' => 'active',
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 30,
            'currency' => 'GBP',
        ]);

        $global = $this->service->getGroupedSubscriptions($member->id);
        $scoped = $this->service->getGroupedSubscriptions($member->id, $this->siteId);

        $this->assertCount(2, $global['current']);
        $this->assertSame(
            ['First Publication', 'Second Publication'],
            array_values(array_unique(array_column($global['current'], 'plan_name')))
        );
        $this->assertCount(1, $scoped['current']);
        $this->assertSame('First Publication', $scoped['current'][0]['plan_name']);

        $byId = [];
        foreach ($global['current'] as $formatted) {
            $byId[$formatted['id']] = $formatted;
        }

        $this->assertSame(
            "/{$this->siteSlug}/member/subscriptions",
            $byId[$firstSubscription->id]['actions'][0]['url']
        );
        $this->assertSame(
            "/{$otherSite->slug}/member/subscriptions",
            $byId[$secondSubscription->id]['actions'][0]['url']
        );
        $this->assertContains(
            "/{$otherSite->slug}/member/subscriptions/{$secondSubscription->id}/issue-deliveries",
            array_column($byId[$secondSubscription->id]['management_links'], 'url')
        );
        $this->assertContains(
            "/{$otherSite->slug}/member/addresses",
            array_column($byId[$secondSubscription->id]['management_links'], 'url')
        );
    }

    public function test_newsletter_lookup_cannot_escape_subscription_site_scope(): void
    {
        $member = $this->createMember();
        $otherSite = $this->createSite();
        Newsletter::create([
            'title' => 'Wrong Site Newsletter',
            'slug' => 'shared-slug',
            'site_id' => $otherSite->id,
            'active' => true,
            'interval' => 'weekly',
            'content' => '{}',
        ]);
        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Scoped Access',
            'status' => 'active',
            'delivery_type' => SubscriptionType::DIGITAL->value,
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 10,
            'currency' => 'GBP',
        ]);
        SubscriptionPremiumAccess::create([
            'subscription_id' => $subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'shared-slug',
            'is_active' => true,
            'granted_at' => date('Y-m-d H:i:s'),
        ]);

        $formatted = $this->service->formatSubscriptionForListing($subscription);

        $this->assertSame([], $formatted['newsletters']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = new SubscriptionRepository();
        $this->newsletterRepository = new NewsletterRepository();

        $this->service = new SubscriptionListingService(
            $this->subscriptionRepository,
            $this->newsletterRepository,
            new SiteRepository(\App\Framework\Database\Database::getInstance()),
            new SubscriptionPlanPricingRepository(),
        );
    }
}
