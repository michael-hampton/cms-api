<?php

namespace App\Tests\Unit\Models;

use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberModelTest extends FunctionalTestCase
{

    use CreatesTestData;

    public function testHasActiveSubscriptionOfTypeReturnsTrueForPaid()
    {
        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertTrue($member->hasActiveSubscriptionOfType('paid', $this->siteId));
        $this->assertFalse($member->hasActiveSubscriptionOfType('trial', $this->siteId));
    }

    public function testHasActiveSubscriptionOfTypeReturnsFalseForExpired()
    {
        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'expired',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        $this->assertFalse($member->hasActiveSubscriptionOfType('paid', $this->siteId));
    }

    public function testGetSubscriptionWindowsReturnsOnlyPaidWindows()
    {
        $member = $this->createMember();

        $paidSub = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'expired',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);

        // Trial subscription (should not have window)
        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Trial',
            'status' => 'expired',
            'type' => 'trial',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 week')),
            'end_date' => date('Y-m-d H:i:s'),
            'price' => 0,
            'currency' => 'USD'
        ]);

        $windows = $member->getSubscriptionWindows($this->siteId);

        // Only paid subscription should have a window
        $this->assertCount(1, $windows);
        $this->assertEquals('paid', $windows->first()->type);
    }

}