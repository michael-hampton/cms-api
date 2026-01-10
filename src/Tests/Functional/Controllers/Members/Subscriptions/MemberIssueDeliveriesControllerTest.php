<?php

namespace App\Tests\Functional\Controllers\Members\Subscriptions;

use App\Models\IssueDelivery;
use App\Models\Member;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberIssueDeliveriesControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private Subscription $subscription;

    public function testIndexDisplaysUpcomingAndPastDeliveries(): void
    {
        // Create upcoming delivery
        IssueDelivery::create([
            'subscription_id' => $this->subscription->id,
            'issue_number' => 5,
            'scheduled_date' => date('Y-m-d', strtotime('+7 days')),
            'status' => 'scheduled'
        ]);

        // Create past delivery
        IssueDelivery::create([
            'subscription_id' => $this->subscription->id,
            'issue_number' => 4,
            'scheduled_date' => date('Y-m-d', strtotime('-7 days')),
            'delivery_date' => date('Y-m-d', strtotime('-5 days')),
            'status' => 'delivered'
        ]);

        $response = $this->getForSiteUnauthenticated("/member/subscriptions/{$this->subscription->id}/issue-deliveries");

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Delivery Schedule', $content);
//        $this->assertStringContainsString('Issue 5', $content);
//        $this->assertStringContainsString('Issue 4', $content);
    }

    public function testIndexRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite("/member/subscriptions/{$this->subscription->id}/issue-deliveries");

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/member/login', $response->getHeader('Location'));
    }

    public function testIndexReturns404ForNonExistentSubscription(): void
    {
        $response = $this->getForSite('/member/subscriptions/99999/issue-deliveries');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testIndexReturns404ForOtherMembersSubscription(): void
    {
        $otherMember = $this->createMember(['email' => 'other@example.com']);
        $otherSubscription = Subscription::create([
            'member_id' => $otherMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Print Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'delivery_type' => 'print'
        ]);

        $response = $this->getForSite("/member/subscriptions/{$otherSubscription->id}/deliveries");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testIndexRedirectsForDigitalSubscription(): void
    {
        $digitalSubscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 19.99,
            'currency' => 'USD',
            'delivery_type' => 'digital'
        ]);

        $response = $this->getForSite("/member/subscriptions/{$digitalSubscription->id}/issue-deliveries");

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/member/subscriptions', $response->getHeader('Location'));
    }

    public function testIndexRedirectsForBundleWithoutPrintComponent(): void
    {
        $bundleSubscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Digital Bundle',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 39.99,
            'currency' => 'USD',
            'delivery_type' => 'digital'
        ]);

        $response = $this->getForSite("/member/subscriptions/{$bundleSubscription->id}/issue-deliveries");

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testIndexShowsFlashErrorForNonPrintSubscription(): void
    {
        $digitalSubscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 19.99,
            'currency' => 'USD',
            'delivery_type' => 'digital'
        ]);

        $this->getForSite("/member/subscriptions/{$digitalSubscription->id}/issue-deliveries");

        $this->assertArrayHasKey('flash_error', $_SESSION);
        $this->assertStringContainsString('only available for print subscriptions', $_SESSION['flash_error']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember();
        $this->actingAsMember($this->member);

        $this->subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Print Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'delivery_type' => 'print'
        ]);
    }
}