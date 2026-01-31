<?php

namespace App\Tests\Unit\Services\Cms\Pages;

use App\Models\EditorialOverride;
use App\Models\Member;
use App\Models\Model;
use App\Models\Page;
use App\Models\PageMetadata;
use App\Models\Subscription;
use App\Models\SubscriptionWindow;
use App\Services\Cms\Pages\ArticleAccessService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ArticleAccessServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private ArticleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ArticleAccessService();
    }

    public function testGuestCanViewFreeContent()
    {
        $page = $this->createPageWithAccess('free');

        $result = $this->service->canView($page, null);

        $this->assertTrue($result['can_view']);
        $this->assertNull($result['reason']);
    }

    // === GUEST ACCESS TESTS ===

    private function createPageWithAccess(string $accessLevel, ?string $publishedAt = null): Page
    {
        $page = Page::create([
            'site_id' => $this->siteId,
            'title' => 'Test Page ' . uniqid(),
            'slug' => 'test-' . uniqid(),
            'status' => 'published',
            'published_at' => $publishedAt ?? date('Y-m-d H:i:s')
        ]);

        PageMetadata::create([
            'page_id' => $page->id,
            'visibility' => $accessLevel
        ]);

        return $page->load(['metadata']);
    }

    public function testGuestCannotViewMemberContent()
    {
        $page = $this->createPageWithAccess('member');

        $result = $this->service->canView($page, null);

        $this->assertFalse($result['can_view']);
        $this->assertEquals('member_required', $result['reason']);
    }

    public function testGuestCannotViewPremiumContent()
    {
        $page = $this->createPageWithAccess('premium');

        $result = $this->service->canView($page, null);

        $this->assertFalse($result['can_view']);
        $this->assertEquals('subscription_required', $result['reason']);
    }

    // === GUEST WITH EDITORIAL OVERRIDE ===

    public function testGuestCanViewPremiumContentWithGlobalOverride()
    {
        $page = $this->createPageWithAccess('premium');

        // Create global editorial override
        EditorialOverride::create([
            'page_id' => $page->id,
            'member_id' => null, // Global
            'override_access_level' => 'free',
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        $result = $this->service->canView($page, null);

        $this->assertTrue($result['can_view']);
        $this->assertEquals('editorial_override', $result['reason']);
    }

    // === MEMBER ACCESS TESTS ===

    public function testMemberCanViewFreeContent()
    {
        $member = $this->createMember(['site_id' => $this->siteId]);
        $page = $this->createPageWithAccess('free');

        $result = $this->service->canView($page, $member);

        $this->assertTrue($result['can_view']);
    }

    public function testMemberCanViewMemberContent()
    {
        $member = $this->createMember();
        $page = $this->createPageWithAccess('member');

        $result = $this->service->canView($page, $member);

        $this->assertTrue($result['can_view']);
        $this->assertEquals('member_authenticated', $result['reason']);
    }

    public function testMemberCannotViewPremiumContentWithoutSubscription()
    {
        $member = $this->createMember();
        $page = $this->createPageWithAccess('premium');

        $result = $this->service->canView($page, $member);

        $this->assertFalse($result['can_view']);
    }

    // === MEMBER WITH EDITORIAL OVERRIDE ===

    public function testMemberCanViewPremiumContentWithUserSpecificOverride()
    {
        $member = $this->createMember();
        $page = $this->createPageWithAccess('premium');

        EditorialOverride::create([
            'page_id' => $page->id,
            'member_id' => $member->id,
            'override_access_level' => 'free',
            'starts_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        $result = $this->service->canView($page, $member);

        $this->assertTrue($result['can_view']);
        $this->assertEquals('editorial_override', $result['reason']);
    }

    public function testMemberCannotViewWithExpiredOverride()
    {
        $member = $this->createMember();
        $page = $this->createPageWithAccess('premium');

        EditorialOverride::create([
            'page_id' => $page->id,
            'member_id' => $member->id,
            'override_access_level' => 'free',
            'starts_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'ends_at' => date('Y-m-d H:i:s', strtotime('-1 day')) // Expired
        ]);

        $result = $this->service->canView($page, $member);

        $this->assertFalse($result['can_view']);
    }

    // === ACTIVE SUBSCRIPTION TESTS ===

    public function testActivePaidSubscriberCanViewAllPremiumContent()
    {
        $member = $this->createMember();
        $this->createActiveSubscription($member, 'paid');
        $page = $this->createPageWithAccess('premium');

        $result = $this->service->canView($page, $member, $this->siteId);

        $this->assertTrue($result['can_view']);
        $this->assertEquals('active_paid_subscription', $result['reason']);
    }

    private function createActiveSubscription(Member $member, string $type): Model
    {
        return Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'type' => $type,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);
    }

    // === HISTORICAL PAID SUBSCRIPTION TESTS ===

    public function testActiveTrialSubscriberCanViewAllPremiumContent()
    {
        $member = $this->createMember();
        $this->createActiveSubscription($member, 'trial');
        $page = $this->createPageWithAccess('premium');

        $result = $this->service->canView($page, $member, $this->siteId);

        $this->assertTrue($result['can_view']);
        $this->assertEquals('active_trial', $result['reason']);
    }

    public function testExpiredPaidSubscriberCanViewContentPublishedDuringSubscription()
    {
        $member = $this->createMember();

        // Subscription from Jan 1-31, 2025
        $this->createExpiredSubscription($member, 'paid', '2025-01-01', '2025-01-31');

        // Page published during subscription (Jan 15)
        $page = $this->createPageWithAccess('premium', '2025-01-15 12:00:00');

        $result = $this->service->canView($page, $member);

        $this->assertTrue($result['can_view']);
        $this->assertEquals('historical_subscription_window', $result['reason']);
    }

    private function createExpiredSubscription(Member $member, string $type, string $start, string $end): Model
    {
        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'status' => 'expired',
            'type' => $type,
            'start_date' => $start,
            'end_date' => $end,
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        // Create window ONLY for paid subscriptions
        if ($type === 'paid') {
            SubscriptionWindow::create([
                'member_id' => $member->id,
                'subscription_id' => $subscription->id,
                'site_id' => $this->siteId,
                'window_start' => $start,
                'window_end' => $end,
                'type' => 'paid'
            ]);
        }

        return $subscription;
    }

    // === TRIAL SUBSCRIPTION TESTS ===

    public function testExpiredPaidSubscriberCannotViewContentPublishedAfterSubscription()
    {
        $member = $this->createMember();

        // Subscription ended Jan 31
        $this->createExpiredSubscription($member, 'paid', '2025-01-01', '2025-01-31');

        // Page published Feb 15 (after subscription)
        $page = $this->createPageWithAccess('premium', '2025-02-15 12:00:00');

        $result = $this->service->canView($page, $member);

        $this->assertFalse($result['can_view']);
        $this->assertEquals('published_after_subscription', $result['reason']);
    }

    // === MULTIPLE SUBSCRIPTION WINDOWS ===

    public function testExpiredPaidSubscriberCannotViewContentPublishedBeforeSubscription()
    {
        $member = $this->createMember();

        // Subscription started Jan 1
        $this->createExpiredSubscription($member, 'paid', '2025-01-01', '2025-01-31');

        // Page published Dec 15 (before subscription)
        $page = $this->createPageWithAccess('premium', '2024-12-15 12:00:00');

        $result = $this->service->canView($page, $member);

        $this->assertFalse($result['can_view']);
        $this->assertEquals('published_before_subscription', $result['reason']);
    }

    // === BULK ENRICHMENT TESTS ===

    public function testExpiredTrialSubscriberLosesAccessToAllContent()
    {
        $member = $this->createMember();

        // Trial from Jan 1-31
        $this->createExpiredSubscription($member, 'trial', '2025-01-01', '2025-01-31');

        // Page published during trial
        $page = $this->createPageWithAccess('premium', '2025-01-15 12:00:00');

        $result = $this->service->canView($page, $member);

        $this->assertFalse($result['can_view']);
        $this->assertEquals('no_subscription_history', $result['reason']); // Trials don't create windows
    }

    public function testMultipleSubscriptionWindowsAllowAccessToContentFromEither()
    {
        $member = $this->createMember();

        // First subscription: Jan 1-31
        $this->createExpiredSubscription($member, 'paid', '2025-01-01', '2025-01-31');

        // Second subscription: March 1-31
        $this->createExpiredSubscription($member, 'paid', '2025-03-01', '2025-03-31');

        // Page from first window
        $page1 = $this->createPageWithAccess('premium', '2025-01-15 12:00:00');
        $result1 = $this->service->canView($page1, $member);
        $this->assertTrue($result1['can_view']);

        // Page from second window
        $page2 = $this->createPageWithAccess('premium', '2025-03-15 12:00:00');
        $result2 = $this->service->canView($page2, $member);
        $this->assertTrue($result2['can_view']);

        // Page from gap (Feb 15)
        $page3 = $this->createPageWithAccess('premium', '2025-02-15 12:00:00');
        $result3 = $this->service->canView($page3, $member);
        $this->assertFalse($result3['can_view']);
    }

    // === EDGE CASES ===

    public function testBulkEnrichPagesWithMixedAccessLevels()
    {
        $member = $this->createMember();
        $this->createActiveSubscription($member, 'paid');

        $freePage = $this->createPageWithAccess('free');
        $memberPage = $this->createPageWithAccess('member');
        $premiumPage = $this->createPageWithAccess('premium');

        $pages = [$freePage, $memberPage, $premiumPage];

        $enriched = $this->service->enrichPagesWithAccessInfo($pages, $member, $this->siteId);

        $this->assertCount(3, $enriched);

        // All should be viewable
        $this->assertTrue($enriched[0]['can_view']);
        $this->assertTrue($enriched[1]['can_view']);
        $this->assertTrue($enriched[2]['can_view']);

        // Check access levels
        $this->assertEquals('free', $enriched[0]['access_level']);
        $this->assertEquals('member', $enriched[1]['access_level']);
        $this->assertEquals('premium', $enriched[2]['access_level']);
    }

    public function testBulkEnrichWithHistoricalWindows()
    {
        $member = $this->createMember();

        // Subscription Jan 1-31
        $this->createExpiredSubscription($member, 'paid', '2025-01-01', '2025-01-31');

        $pages = [
            $this->createPageWithAccess('premium', '2025-01-15'), // During
            $this->createPageWithAccess('premium', '2025-02-15'), // After
            $this->createPageWithAccess('premium', '2024-12-15'), // Before
        ];

        $enriched = $this->service->enrichPagesWithAccessInfo($pages, $member, $this->siteId);

        $this->assertTrue($enriched[0]['can_view']);  // During window
        $this->assertFalse($enriched[1]['can_view']); // After window
        $this->assertFalse($enriched[2]['can_view']); // Before window
    }

    public function testPageWithoutMetadataDefaultsToFree()
    {
        $page = Page::create([
            'site_id' => $this->siteId,
            'title' => 'No Metadata Page',
            'slug' => 'no-meta-' . uniqid(),
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s')
        ]);
// No metadata created
        $result = $this->service->canView($page, null);

        $this->assertTrue($result['can_view']);
    }

// === HELPER METHODS ===

    public function testPageWithoutPublishedDateCannotUseHistoricalAccess()
    {
        $member = $this->createMember();
        $this->createExpiredSubscription($member, 'paid', '2025-01-01', '2025-01-31');

        $page = Page::create([
            'site_id' => $this->siteId,
            'title' => 'No Date Page',
            'slug' => 'no-date-' . uniqid(),
            'status' => 'published',
            'published_at' => null // No date
        ]);

        PageMetadata::create([
            'page_id' => $page->id,
            'visibility' => 'premium'
        ]);

        $page = $page->load(['metadata']);

        $result = $this->service->canView($page, $member);

        $this->assertFalse($result['can_view']);
    }

    public function testTimezoneConsistency()
    {
        $member = $this->createMember();
        $subscription = $this->createActiveSubscription($member, 'paid');

        // Create window with explicit UTC timestamps
        $window = SubscriptionWindow::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'subscription_id' => $subscription->id,
            'type' => 'paid',
            'window_start' => '2025-01-01 00:00:00',
            'window_end' => '2025-01-31 23:59:59'
        ]);

        // Page published at exact window start (edge case)
        $page1 = $this->createPageWithAccess('premium', '2025-01-01 00:00:00');
        $result1 = $this->service->canView($page1, $member);
        $this->assertTrue($result1['can_view']); // Inclusive start

        // Page published at exact window end
        $page2 = $this->createPageWithAccess('premium', '2025-01-31 23:59:59');
        $result2 = $this->service->canView($page2, $member);
        $this->assertTrue($result2['can_view']); // Inclusive end

        // One second after window end
        $page3 = $this->createPageWithAccess('premium', '2025-02-01 00:00:00');
        $result3 = $this->service->canView($page3, $member);
        $this->assertFalse($result3['can_view']);
    }
}
