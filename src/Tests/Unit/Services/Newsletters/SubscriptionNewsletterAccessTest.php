<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Enums\Newsletters\PremiumAccessType;
use App\Models\Member;
use App\Models\Model;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionNewsletterAccessTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private Newsletter $newsletter;

    public function test_can_access_newsletter_allows_active_subscription(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createFreeNewsletter();

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    private function createActiveSubscription(): Model
    {
        return Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);
    }

    private function createFreeNewsletter(): Model
    {
        return Newsletter::create([
            'title' => 'Free Newsletter',
            'content' => '[]',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'is_premium' => false,
            'slug' => null
        ]);
    }

    public function test_can_access_newsletter_denies_expired_subscription(): void
    {
        $subscription = $this->createExpiredSubscription();
        $newsletter = $this->createFreeNewsletter();

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
        $this->assertEquals('subscription_not_eligible', $result->reasonCode);
    }

    private function createExpiredSubscription(): Subscription
    {
        return Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Expired',
            'status' => 'expired',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);
    }

    public function test_can_access_newsletter_denies_access_level_mismatch(): void
    {
        $subscription = $this->createActiveSubscription();
        $newsletter = $this->createPaidNewsletter('insider');

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertFalse($result->allowed);
        $this->assertEquals('access_level_mismatch', $result->reasonCode);
    }

    private function createPaidNewsletter(string $slug): Model
    {
        return Newsletter::create([
            'title' => 'Paid Newsletter',
            'content' => '[]',
            'interval' => 'weekly',
            'active' => true,
            'site_id' => $this->siteId,
            'is_premium' => true,
            'slug' => $slug
        ]);
    }

    public function test_can_access_newsletter_allows_matching_access_level(): void
    {
        $subscription = $this->createActiveSubscription();
        $subscription->grantPremiumAccess(PremiumAccessType::Newsletter->value, 'insider');

        $newsletter = $this->createPaidNewsletter('insider');

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    public function test_can_access_newsletter_allows_grace_period(): void
    {
        $subscription = $this->createGracePeriodSubscription();
        $newsletter = $this->createFreeNewsletter();

        $result = $subscription->canAccessNewsletter($newsletter, $this->member);

        $this->assertTrue($result->allowed);
    }

    private function createGracePeriodSubscription(): Subscription
    {
        return Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Grace',
            'status' => 'grace_period',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+5 days')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createMember();
    }
}