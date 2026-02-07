<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Enums\Newsletters\PremiumAccessType;
use App\Models\Member;
use App\Models\Model;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Models\SubscriptionBundle;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterAccessTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    public function test_newsletter_without_bundle_requirement_allows_any_subscription(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter(['requires_bundle' => false]);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    // ==================== PHASE 3: BUNDLE ACCESS TESTS ====================

    private function createActiveSubscription(array $overrides = []): Model
    {
        return Subscription::create(array_merge([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
        ], $overrides));
    }

    private function createNewsletter(array $overrides = []): Model
    {
        return Newsletter::create(array_merge([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'is_premium' => true,
        ], $overrides));
    }

    public function test_newsletter_requiring_bundle_denies_subscription_without_bundle(): void
    {
        $bundle = $this->createBundle('premium-bundle', ['insider', 'tech-weekly']);
        $subscription = $this->createActiveSubscription();

        $newsletter = $this->createNewsletter([
            'requires_bundle' => true,
            'bundle_id' => $bundle->id,
            'slug' => 'insider',
        ]);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
        $this->assertEquals('bundle_required', $result->reasonCode);
        $this->assertStringContainsString('premium-bundle', $result->reason);
    }

    private function createBundle(string $slug, array $newsletterSlugs, bool $isActive = true): Model
    {
        return SubscriptionBundle::create([
            'site_id' => $this->siteId,
            'name' => ucfirst($slug) . ' Bundle',
            'slug' => $slug,
            'description' => 'Test bundle',
            'newsletter_slugs' => $newsletterSlugs,
            'is_active' => $isActive,
        ]);
    }

    public function test_newsletter_requiring_bundle_allows_subscription_with_matching_bundle(): void
    {
        $bundle = $this->createBundle('premium-bundle', ['insider', 'tech-weekly']);
        $subscription = $this->createActiveSubscription(['bundle_id' => $bundle->id]);

        $newsletter = $this->createNewsletter([
            'requires_bundle' => true,
            'bundle_id' => $bundle->id,
            'slug' => 'insider',
        ]);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    public function test_subscription_has_bundle_access_to_newsletter_via_bundle(): void
    {
        $bundle = $this->createBundle('premium-bundle', ['insider', 'tech-weekly', 'finance-daily']);
        $subscription = $this->createActiveSubscription(['bundle_id' => $bundle->id]);

        $this->assertTrue($subscription->hasBundleAccessToNewsletter('insider'));
        $this->assertTrue($subscription->hasBundleAccessToNewsletter('tech-weekly'));
        $this->assertTrue($subscription->hasBundleAccessToNewsletter('finance-daily'));
        $this->assertFalse($subscription->hasBundleAccessToNewsletter('unknown-newsletter'));
    }

    public function test_subscription_without_bundle_has_no_bundle_access(): void
    {
        $subscription = $this->createActiveSubscription();

        $this->assertFalse($subscription->hasBundleAccessToNewsletter('any-newsletter'));
    }

    public function test_newsletter_slug_access_allows_bundle_or_direct_access(): void
    {
        $bundle = $this->createBundle('bundle', ['insider']);

        // Subscription with bundle access only
        $subscriptionWithBundle = $this->createActiveSubscription(['bundle_id' => $bundle->id]);

        // Subscription with direct premium access only
        $subscriptionWithDirect = $this->createActiveSubscription();
        $subscriptionWithDirect->grantPremiumAccess(PremiumAccessType::Newsletter->value, 'insider');

        $newsletter = $this->createNewsletter(['slug' => 'insider']);

        // Both should have access
        $this->assertTrue($subscriptionWithBundle->canAccessNewsletter($newsletter, $this->member)->allowed);
        $this->assertTrue($subscriptionWithDirect->canAccessNewsletter($newsletter, $this->member)->allowed);
    }

    // ==================== PHASE 4: GEOGRAPHIC RESTRICTIONS ====================

    public function test_bundle_includes_newsletter_returns_true_for_included_slug(): void
    {
        $bundle = $this->createBundle('test-bundle', ['insider', 'tech']);

        $this->assertTrue($bundle->includesNewsletter('insider'));
        $this->assertTrue($bundle->includesNewsletter('tech'));
        $this->assertFalse($bundle->includesNewsletter('other'));
    }

    public function test_inactive_bundle_does_not_include_newsletters(): void
    {
        $bundle = $this->createBundle('inactive-bundle', ['insider'], false);

        $this->assertFalse($bundle->includesNewsletter('insider'));
    }

    public function test_newsletter_without_geographic_restrictions_allows_any_region(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter(['has_geographic_restrictions' => false]);

        $this->member->setRegion('US');

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    public function test_newsletter_with_allowed_regions_allows_member_in_allowed_region(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_geographic_restrictions' => true,
            'allowed_regions' => ['US', 'CA', 'GB'],
        ]);

        $this->member->setRegion('US');

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    public function test_newsletter_with_allowed_regions_denies_member_in_other_region(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_geographic_restrictions' => true,
            'allowed_regions' => ['US', 'CA', 'GB'],
        ]);

        $this->member->setRegion('FR');

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
        $this->assertEquals('geographic_restriction', $result->reasonCode);
        $this->assertStringContainsString('FR', $result->reason);
    }

    public function test_newsletter_with_blocked_regions_denies_member_in_blocked_region(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_geographic_restrictions' => true,
            'blocked_regions' => ['CN', 'RU'],
        ]);

        $this->member->setRegion('CN');

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
        $this->assertEquals('geographic_restriction', $result->reasonCode);
    }

    public function test_newsletter_with_blocked_regions_allows_member_not_in_blocklist(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_geographic_restrictions' => true,
            'blocked_regions' => ['CN', 'RU'],
        ]);

        $this->member->setRegion('US');

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    public function test_newsletter_with_geographic_restrictions_denies_member_without_region(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_geographic_restrictions' => true,
            'allowed_regions' => ['US'],
        ]);

        $this->member->setRegion(null);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
        $this->assertEquals('geographic_restriction', $result->reasonCode);
    }

    // ==================== PHASE 5: TIME-BASED ACCESS WINDOWS ====================

    public function test_newsletter_with_geographic_restrictions_requires_member_object(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_geographic_restrictions' => true,
            'allowed_regions' => ['US'],
        ]);

        $result = $subscription->canAccessNewsletter($newsletter, null);

        $this->assertFalse($result->allowed);
        $this->assertEquals('member_required_for_geo_check', $result->reasonCode);
    }

    public function test_blocklist_takes_precedence_over_allowlist(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_geographic_restrictions' => true,
            'allowed_regions' => ['US', 'CN'],
            'blocked_regions' => ['CN'], // Block takes precedence
        ]);

        $this->member->setRegion('CN');

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
    }

    public function test_newsletter_without_time_window_is_always_accessible(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter(['has_time_window' => false]);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    public function test_newsletter_with_time_window_allows_access_within_window(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_time_window' => true,
            'access_window_start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'access_window_end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    public function test_newsletter_with_time_window_denies_access_before_window(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_time_window' => true,
            'access_window_start' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'access_window_end' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        ]);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
        $this->assertEquals('outside_access_window', $result->reasonCode);
    }

    public function test_newsletter_with_time_window_denies_access_after_window(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_time_window' => true,
            'access_window_start' => date('Y-m-d H:i:s', strtotime('-2 hours')),
            'access_window_end' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
        $this->assertEquals('outside_access_window', $result->reasonCode);
    }

    // ==================== COMBINED SCENARIOS ====================

    public function test_newsletter_with_only_start_window_allows_after_start(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_time_window' => true,
            'access_window_start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'access_window_end' => null,
        ]);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    public function test_newsletter_with_only_end_window_allows_before_end(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createNewsletter([
            'has_time_window' => true,
            'access_window_start' => null,
            'access_window_end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    public function test_all_restrictions_must_pass_for_access(): void
    {
        $bundle = $this->createBundle('premium', ['insider']);
        $subscription = $this->createActiveSubscription(['bundle_id' => $bundle->id]);

        $newsletter = $this->createNewsletter([
            'slug' => 'insider',
            'requires_bundle' => true,
            'bundle_id' => $bundle->id,
            'has_geographic_restrictions' => true,
            'allowed_regions' => ['US', 'CA'],
            'has_time_window' => true,
            'access_window_start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'access_window_end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        $this->member->setRegion('US');

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    // ==================== HELPER METHODS ====================

    public function test_failure_in_any_phase_denies_access(): void
    {
        $bundle = $this->createBundle('premium', ['insider']);
        $subscription = $this->createActiveSubscription(['bundle_id' => $bundle->id]);

        // All restrictions pass EXCEPT geographic
        $newsletter = $this->createNewsletter([
            'slug' => 'insider',
            'requires_bundle' => true,
            'bundle_id' => $bundle->id,
            'has_geographic_restrictions' => true,
            'allowed_regions' => ['US', 'CA'],
            'has_time_window' => true,
            'access_window_start' => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'access_window_end' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]);

        $this->member->setRegion('FR'); // Wrong region

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
        $this->assertEquals('geographic_restriction', $result->reasonCode);
    }

    public function test_expired_subscription_fails_before_other_checks(): void
    {
        $bundle = $this->createBundle('premium', ['insider']);
        $subscription = $this->createExpiredSubscription(['bundle_id' => $bundle->id]);

        $newsletter = $this->createNewsletter([
            'slug' => 'insider',
            'requires_bundle' => true,
            'bundle_id' => $bundle->id,
        ]);

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
        $this->assertEquals('subscription_not_eligible', $result->reasonCode);
    }

    private function createExpiredSubscription(array $overrides = []): Model
    {
        return Subscription::create(array_merge([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Expired',
            'status' => 'expired',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 29.99,
            'currency' => 'USD',
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createMember();
    }
}