<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Member;
use App\Models\Subscription;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSubscriptionDeliveryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private Subscription $subscription;

    public function test_can_pause_print_subscription_delivery(): void
    {
        $pauseStart = (new \DateTime('+1 day'))->format('Y-m-d');
        $pauseEnd = (new \DateTime('+14 days'))->format('Y-m-d');

        $response = $this->post(
            "/member/subscriptions/{$this->subscription->id}/pause-delivery",
            [
                'pause_start' => $pauseStart,
                'pause_end' => $pauseEnd,
                'reason' => 'Holiday'
            ]
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Delivery paused successfully', $data['message']);

        // Verify in database
        $updated = Subscription::find($this->subscription->id);
        $this->assertTrue($updated->delivery_paused);
        $this->assertEquals($pauseStart, $updated->delivery_pause_start->format('Y-m-d'));
    }

    public function test_cannot_pause_with_invalid_dates(): void
    {
        $pauseStart = (new \DateTime('+7 days'))->format('Y-m-d');
        $pauseEnd = (new \DateTime('+1 day'))->format('Y-m-d');

        $response = $this->post(
            "/member/subscriptions/{$this->subscription->id}/pause-delivery",
            [
                'pause_start' => $pauseStart,
                'pause_end' => $pauseEnd
            ]
        );

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('End date must be after start date', $data['message']);
    }

    public function test_cannot_pause_for_more_than_90_days(): void
    {
        $pauseStart = (new \DateTime('+1 day'))->format('Y-m-d');
        $pauseEnd = (new \DateTime('+100 days'))->format('Y-m-d');

        $response = $this->post(
            "/member/subscriptions/{$this->subscription->id}/pause-delivery",
            [
                'pause_start' => $pauseStart,
                'pause_end' => $pauseEnd
            ]
        );

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('cannot exceed 90 days', $data['message']);
    }

    public function test_can_resume_paused_delivery(): void
    {
        // First pause the delivery
        $this->subscription->delivery_paused = true;
        $this->subscription->delivery_pause_start = new \DateTime('-1 day')->format('Y-m-d H:i:s');
        $this->subscription->delivery_pause_end = new \DateTime('+14 days')->format('Y-m-d H:i:s');
        $this->subscription->save();

        $response = $this->post(
            "/member/subscriptions/{$this->subscription->id}/resume-delivery"
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Delivery resumed successfully', $data['message']);

        // Verify in database
        $updated = Subscription::find($this->subscription->id);
        $this->assertFalse($updated->delivery_paused);
        $this->assertNull($updated->delivery_pause_start);
    }

    public function test_cannot_pause_digital_subscription(): void
    {
        $digitalSubscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $this->subscription->plan_id,
            'plan_name' => 'Digital Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 19.99,
            'currency' => 'USD',
            'delivery_type' => 'digital'
        ]);

        $pauseStart = (new \DateTime('+1 day'))->format('Y-m-d');
        $pauseEnd = (new \DateTime('+14 days'))->format('Y-m-d');

        $response = $this->post(
            "/member/subscriptions/{$digitalSubscription->id}/pause-delivery",
            [
                'pause_start' => $pauseStart,
                'pause_end' => $pauseEnd
            ]
        );

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('cannot be paused', $data['message']);
    }

    public function test_cannot_pause_other_members_subscription(): void
    {
        $otherMember = $this->createMember(['email' => 'other@example.com']);
        $otherSubscription = Subscription::create([
            'member_id' => $otherMember->id,
            'site_id' => $this->siteId,
            'plan_id' => $this->subscription->plan_id,
            'plan_name' => 'Print Plan',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'delivery_type' => 'print'
        ]);

        $pauseStart = (new \DateTime('+1 day'))->format('Y-m-d');
        $pauseEnd = (new \DateTime('+14 days'))->format('Y-m-d');

        $response = $this->post(
            "/member/subscriptions/{$otherSubscription->id}/pause-delivery",
            [
                'pause_start' => $pauseStart,
                'pause_end' => $pauseEnd
            ]
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_get_pause_status(): void
    {
        $this->subscription->delivery_paused = true;
        $this->subscription->delivery_pause_start = new \DateTime('-1 day')->format('Y-m-d H:i:s');
        $this->subscription->delivery_pause_end = new \DateTime('+14 days')->format('Y-m-d H:i:s');
        $this->subscription->save();

        $response = $this->get(
            "/member/subscriptions/{$this->subscription->id}/pause-status"
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['is_paused']);
        $this->assertFalse($data['can_pause']);
        $this->assertTrue($data['can_resume']);
        $this->assertNotNull($data['pause_start']);
        $this->assertNotNull($data['pause_end']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember();
        $this->actingAsMember($this->member);

        $plan = $this->createSubscriptionPlan();
        $this->subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'delivery_type' => 'print'
        ]);
    }
}