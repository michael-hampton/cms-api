<?php

namespace App\Tests\Functional\Controllers\Members\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Models\Subscription;
use App\Models\SubscriptionPremiumAccess;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class AdminSubscriptionPremiumAccessControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Subscription $subscription;

    public function testGrantCreatesPremiumAccess(): void
    {
        $response = $this->postForSite(
            "/api/subscriptions/{$this->subscription->id}/premium-access/grant",
            [
                'premium_type' => 'newsletter',
                'premium_identifier' => 'insider',
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('access', $data['data']);

        $access = SubscriptionPremiumAccess::first();
        $this->assertNotNull($access);
        $this->assertEquals($this->subscription->id, $access->subscription_id);
        $this->assertEquals('newsletter', $access->premium_type);
        $this->assertEquals('insider', $access->premium_identifier);
        $this->assertTrue($access->is_active);
    }

    public function testRevokeMarksPremiumAccessInactive(): void
    {
        $access = SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'granted_at' => date('Y-m-d H:i:s'),
            'is_active' => true,
        ]);

        $response = $this->postForSite(
            "/api/subscriptions/{$this->subscription->id}/premium-access/revoke",
            [
                'premium_type' => 'newsletter',
                'premium_identifier' => 'insider',
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $access = $access->fresh();
        $this->assertFalse($access->is_active);
    }

    public function testUpdateModifiesPremiumAccess(): void
    {
        $access = SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'granted_at' => date('Y-m-d H:i:s'),
            'is_active' => true,
        ]);

        $response = $this->putForSite(
            "/api/subscriptions/premium-access/{$access->id}",
            [
                'expires_at' => '2030-01-01 00:00:00',
                'is_active' => false,
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $updated = $access->fresh();
        $this->assertEquals('2030-01-01 00:00:00', $updated->expires_at->format('Y-m-d H:i:s'));
        $this->assertFalse($updated->is_active);
    }

    public function testDestroyDeletesPremiumAccess(): void
    {
        $access = SubscriptionPremiumAccess::create([
            'subscription_id' => $this->subscription->id,
            'premium_type' => 'newsletter',
            'premium_identifier' => 'insider',
            'granted_at' => date('Y-m-d H:i:s'),
            'is_active' => true,
        ]);

        $response = $this->deleteForSite(
            "/api/subscriptions/premium-access/{$access->id}"
        );

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $this->assertNull(SubscriptionPremiumAccess::find($access->id));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscription = Subscription::create([
            'member_id' => $this->createMember()->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 19.99,
            'currency' => 'USD',
            'delivery_type' => SubscriptionType::DIGITAL->value,
        ]);
    }
}

