<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Member;
use App\Models\Subscription;
use App\Repositories\SubscriptionRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriptionRepository $repository;
    private Member $testMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionRepository();
        $this->testMember = $this->createMember();
    }

    public function test_get_active_subscription_for_member_returns_active(): void
    {
        // Arrange
        $active = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $expired = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Basic',
            'status' => 'expired',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->getActiveSubscriptionForMember($this->testMember->id, $this->siteId);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($active->id, $result->id);
        $this->assertEquals('active', $result->status);
    }

    public function test_get_active_subscription_returns_null_when_none_active(): void
    {
        // Arrange
        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Expired Plan',
            'status' => 'expired',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->getActiveSubscriptionForMember($this->testMember->id, $this->siteId);

        // Assert
        $this->assertNull($result);
    }

    public function test_get_subscription_history_returns_all_subscriptions(): void
    {
        // Arrange
        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Basic',
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s', strtotime('-6 months')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-3 months')),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->getSubscriptionHistory($this->testMember->id, $this->siteId);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_subscription_history_ordered_by_created_at_desc(): void
    {
        // Arrange
        $old = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Old Plan',
            'status' => 'expired',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        $new = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'New Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->getSubscriptionHistory($this->testMember->id, $this->siteId);

        // Assert
        $this->assertEquals($new->id, $result->last()->id);
    }

    public function test_count_active_subscriptions_returns_correct_count(): void
    {
        // Arrange
        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Basic',
            'status' => 'cancelled',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 9.99,
            'currency' => 'USD'
        ]);

        // Act
        $count = $this->repository->countActiveSubscriptions($this->testMember->id, $this->siteId);

        // Assert
        $this->assertEquals(1, $count);
    }

    public function test_cancel_subscription_updates_status_and_auto_renew(): void
    {
        // Arrange
        $subscription = Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'auto_renew' => true,
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->cancelSubscription($subscription->id);

        // Assert
        $this->assertTrue($result);

        $updated = Subscription::find($subscription->id);
        $this->assertEquals('cancelled', $updated->status);
        $this->assertFalse($updated->auto_renew);
    }

    public function test_cancel_subscription_returns_false_for_nonexistent(): void
    {
        // Act
        $result = $this->repository->cancelSubscription(99999);

        // Assert
        $this->assertFalse($result);
    }

    public function test_subscriptions_filtered_by_site_id(): void
    {
        // Arrange
        $otherSite = $this->createSite();

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Site 1 Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        Subscription::create([
            'member_id' => $this->testMember->id,
            'site_id' => $otherSite->id,
            'plan_name' => 'Site 2 Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 19.99,
            'currency' => 'USD'
        ]);

        // Act
        $result = $this->repository->getActiveSubscriptionForMember($this->testMember->id, $this->siteId);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('Site 1 Plan', $result->plan_name);
    }
}