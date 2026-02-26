<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Enums\Newsletters\PremiumAccessType;
use App\Models\Member;
use App\Models\Model;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Models\SubscriptionBundle;
use App\Services\Newsletter\NewsletterRecipientResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterAccessIntegrationTest extends FunctionalTestCase
{
    use CreatesTestData;

    private NewsletterRecipientResolver $resolver;

    public function test_resolver_excludes_members_outside_geographic_region(): void
    {
        // Create newsletter restricted to US/CA
        $newsletter = Newsletter::create([
            'title' => 'US Newsletter',
            'content' => '[]',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'is_premium' => true,
            'slug' => 'us-news',
            'has_geographic_restrictions' => true,
            'allowed_regions' => ['US', 'CA'],
        ]);

        // Member in US (allowed)
        $memberUS = $this->createMember(['email' => 'us@example.com']);
        $memberUS->setRegion('US');

        // Member in FR (blocked)
        $memberFR = $this->createMember(['email' => 'fr@example.com']);
        $memberFR->setRegion('FR');

        // Both have active subscriptions with access
        $subUS = $this->createActiveSubscription($memberUS->id);
        $subUS->grantPremiumAccess(PremiumAccessType::Newsletter->value, 'us-news');

        $subFR = $this->createActiveSubscription($memberFR->id);
        $subFR->grantPremiumAccess(PremiumAccessType::Newsletter->value, 'us-news');

        // Create preferences
        $this->createPreference($memberUS, 'weekly');
        $this->createPreference($memberFR, 'weekly');

        // Resolve
        $result = $this->resolver->resolveForNewsletter($newsletter, $this->siteId);

        // Only US member should be included
        $this->assertContains('us@example.com', $result['valid']);
        $this->assertNotContains('fr@example.com', $result['valid']);
    }

    private function createActiveSubscription(int $memberId, array $overrides = []): Model
    {
        return Subscription::create(array_merge([
            'member_id' => $memberId,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
        ], $overrides));
    }

    private function createPreference(Member $member, string $frequency): void
    {
        $token = bin2hex(random_bytes(32));

        \App\Models\MemberSubscriptionPreference::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => $frequency,
            'unsubscribe_token' => $token,
            'is_active' => true,
        ]);
    }

    public function test_resolver_excludes_members_outside_time_window(): void
    {
        // Create newsletter with future access window
        $newsletter = Newsletter::create([
            'title' => 'Future Newsletter',
            'content' => '[]',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'is_premium' => true,
            'slug' => 'future-news',
            'has_time_window' => true,
            'access_window_start' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'access_window_end' => date('Y-m-d H:i:s', strtotime('+2 hours')),
        ]);

        $member = $this->createMember(['email' => 'member@example.com']);
        $subscription = $this->createActiveSubscription($member->id);
        $subscription->grantPremiumAccess(PremiumAccessType::Newsletter->value, 'future-news');
        $this->createPreference($member, 'weekly');

        $result = $this->resolver->resolveForNewsletter($newsletter, $this->siteId);

        // Should be excluded (window hasn't started)
        $this->assertNotContains('member@example.com', $result['valid']);
    }

    public function test_resolver_includes_members_with_bundle_access(): void
    {
        // Create bundle
        $bundle = SubscriptionBundle::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Bundle',
            'slug' => 'premium',
            'newsletter_slugs' => ['insider', 'tech-weekly'],
            'is_active' => true,
            'bundle_price' => 100,
            'total_price' => 100,
        ]);

        // Newsletter requiring bundle
        $newsletter = Newsletter::create([
            'title' => 'Insider Newsletter',
            'content' => '[]',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'is_premium' => true,
            'slug' => 'insider',
            'requires_bundle' => true,
            'bundle_id' => $bundle->id,
        ]);

        // Member with bundle
        $memberWithBundle = $this->createMember(['email' => 'bundle@example.com']);
        $subWithBundle = $this->createActiveSubscription($memberWithBundle->id, ['bundle_id' => $bundle->id]);
        $this->createPreference($memberWithBundle, 'weekly');

        // Member without bundle
        $memberWithoutBundle = $this->createMember(['email' => 'nobundle@example.com']);
        $subWithoutBundle = $this->createActiveSubscription($memberWithoutBundle->id);
        $this->createPreference($memberWithoutBundle, 'weekly');

        $result = $this->resolver->resolveForNewsletter($newsletter, $this->siteId);

        // Only member with bundle should be included
        $this->assertContains('bundle@example.com', $result['valid']);
        $this->assertNotContains('nobundle@example.com', $result['valid']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = $this->app->getContainer()->make(NewsletterRecipientResolver::class);
    }
}