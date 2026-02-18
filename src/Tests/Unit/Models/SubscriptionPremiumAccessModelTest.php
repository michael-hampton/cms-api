<?php

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPremiumAccess;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionPremiumAccessModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private Subscription $subscription;

    public function testCanCreatePremiumAccess(): void
    {
        $access = SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'granted_at' => date('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->assertNotNull($access);
        $this->assertEquals('newsletter', $access->premium_type);
        $this->assertEquals('insider', $access->premium_identifier);
        $this->assertTrue($access->is_active);
    }

    public function testIsExpiredReturnsFalseWhenNoExpiryDate(): void
    {
        $access = SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'granted_at' => date('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->assertFalse($access->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenExpired(): void
    {
        $access = SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'tech-weekly',
            'granted_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'is_active' => true
        ]);

        $this->assertTrue($access->isExpired());
    }

    public function testIsValidReturnsTrueForActiveNonExpired(): void
    {
        $access = SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'granted_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'is_active' => true
        ]);

        $this->assertTrue($access->isValid());
    }

    public function testIsValidReturnsFalseForInactive(): void
    {
        $access = SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'granted_at' => date('Y-m-d H:i:s'),
            'is_active' => false
        ]);

        $this->assertFalse($access->isValid());
    }

    public function testActiveScope(): void
    {
        // Active and not expired
        SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'is_active' => true
        ]);

        // Inactive
        SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'tech-weekly',
            'is_active' => false
        ]);

        // Expired
        SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'archive',
            'premium_identifier' => 'full',
            'is_active' => true,
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        $activeAccess = SubscriptionPremiumAccess::active()->get();

        $this->assertCount(1, $activeAccess);
        $this->assertEquals('insider', $activeAccess->first()->premium_identifier);
    }

    public function testByTypeScope(): void
    {
        SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'is_active' => true
        ]);

        SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'archive',
            'premium_identifier' => 'full',
            'is_active' => true
        ]);

        $newsletters = SubscriptionPremiumAccess::byType('newsletter')->get();

        $this->assertCount(1, $newsletters);
        $this->assertEquals('newsletter', $newsletters->first()->premium_type);
    }

    public function testByIdentifierScope(): void
    {
        SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'is_active' => true
        ]);

        SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'tech-weekly',
            'is_active' => true
        ]);

        $insiderAccess = SubscriptionPremiumAccess::byIdentifier('insider')->get();

        $this->assertCount(1, $insiderAccess);
        $this->assertEquals('insider', $insiderAccess->first()->premium_identifier);
    }

    public function testSubscriptionRelationship(): void
    {
        $access = SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'is_active' => true
        ]);

        $subscription = $access->subscription;

        $this->assertNotNull($subscription);
        $this->assertEquals($this->subscription->id, $subscription->id);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember();
        $this->subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Basic Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 19.99,
            'currency' => 'USD',
            'delivery_type' => SubscriptionType::PRINTED->value
        ]);
    }
}