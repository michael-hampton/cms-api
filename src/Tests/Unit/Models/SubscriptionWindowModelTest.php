<?php
// src/Tests/Unit/Models/SubscriptionWindowTest.php - NEW FILE

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionWindow;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionWindowModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCanCreateSubscriptionWindow()
    {
        $member = $this->createMember();
        $subscription = $this->createActiveSubscription($member, 'paid');

        $window = SubscriptionWindow::create([
            'member_id' => $member->id,
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'window_start' => date('Y-m-d H:i:s'),
            'window_end' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'type' => 'paid'
        ]);

        $this->assertNotNull($window->id);
        $this->assertEquals($member->id, $window->member_id);
        $this->assertEquals('paid', $window->type);
    }

    private function createActiveSubscription(Member $member, string $type): Model
    {
        return Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Test Plan',
            'status' => 'active',
            'type' => $type,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'price' => 29.99,
            'currency' => 'USD'
        ]);
    }

    public function testWindowBelongsToMember()
    {
        $member = $this->createMember();
        $subscription = $this->createActiveSubscription($member, 'paid');

        $window = SubscriptionWindow::create([
            'member_id' => $member->id,
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'window_start' => date('Y-m-d H:i:s'),
            'window_end' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'type' => 'paid'
        ]);

        $relatedMember = $window->member;

        $this->assertNotNull($relatedMember);
        $this->assertEquals($member->id, $relatedMember->id);
    }

    public function testWindowBelongsToSubscription()
    {
        $member = $this->createMember();
        $subscription = $this->createActiveSubscription($member, 'paid');

        $window = SubscriptionWindow::create([
            'member_id' => $member->id,
            'subscription_id' => $subscription->id,
            'site_id' => $this->siteId,
            'window_start' => date('Y-m-d H:i:s'),
            'window_end' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'type' => 'paid'
        ]);

        $relatedSubscription = $window->subscription;

        $this->assertNotNull($relatedSubscription);
        $this->assertEquals($subscription->id, $relatedSubscription->id);
    }

    public function testCanQueryWindowsByMember()
    {
        $member = $this->createMember();
        $subscription1 = $this->createActiveSubscription($member, 'paid');
        $subscription2 = $this->createActiveSubscription($member, 'paid');

        SubscriptionWindow::create([
            'member_id' => $member->id,
            'subscription_id' => $subscription1->id,
            'site_id' => $this->siteId,
            'window_start' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'window_end' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'type' => 'paid'
        ]);

        SubscriptionWindow::create([
            'member_id' => $member->id,
            'subscription_id' => $subscription2->id,
            'site_id' => $this->siteId,
            'window_start' => date('Y-m-d H:i:s'),
            'window_end' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'type' => 'paid'
        ]);

        $windows = SubscriptionWindow::where('member_id', $member->id)->get();

        $this->assertCount(4, $windows); // includes the ones generated when you create a subscription
    }

    public function testWindowsOrderByStartDate()
    {
        $member = $this->createMember();
        $subscription1 = $this->createActiveSubscription($member, 'paid');
        $subscription2 = $this->createActiveSubscription($member, 'paid');

        SubscriptionWindow::create([
            'member_id' => $member->id,
            'subscription_id' => $subscription2->id,
            'site_id' => $this->siteId,
            'window_start' => date('Y-m-d H:i:s'),
            'window_end' => date('Y-m-d H:i:s', strtotime('+1 month')),
            'type' => 'paid'
        ]);

        SubscriptionWindow::create([
            'member_id' => $member->id,
            'subscription_id' => $subscription1->id,
            'site_id' => $this->siteId,
            'window_start' => date('Y-m-d H:i:s', strtotime('-2 months')),
            'window_end' => date('Y-m-d H:i:s', strtotime('-1 month')),
            'type' => 'paid'
        ]);

        $windows = SubscriptionWindow::where('member_id', $member->id)
            ->orderBy('window_start', 'desc')
            ->get();

        $this->assertGreaterThan(
            $windows->last()->window_start,
            $windows->first()->window_start
        );
    }
}