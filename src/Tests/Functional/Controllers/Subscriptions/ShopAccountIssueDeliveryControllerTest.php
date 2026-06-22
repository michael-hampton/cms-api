<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\SubscriptionIssueFulfilment;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ShopAccountIssueDeliveryControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_account_issue_list_uses_subscriber_deferred_date(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();
        $subscription = $this->createPrintSubscription($member->id, $plan->id, $plan->name);
        $issue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'subscription_id' => null,
            'issue_number' => 1,
            'issue_title' => 'Account Test Issue',
            'status' => IssueScheduleStatus::ACTIVE->value,
            'on_sale_date' => (new \DateTime('+5 days'))->format('Y-m-d H:i:s'),
            'estimated_delivery_date' => (new \DateTime('+7 days'))->format('Y-m-d H:i:s'),
        ]);
        $deferredUntil = new \DateTime('+20 days');

        SubscriptionIssueFulfilment::create([
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issue->id,
            'status' => 'scheduled',
            'attempts' => 0,
            'scheduled_for' => $issue->estimated_delivery_date->format('Y-m-d H:i:s'),
            'deferred_until' => $deferredUntil->format('Y-m-d H:i:s'),
        ]);

        $delivery = $this->firstDeliveryFor($member->id, $subscription->id);

        $this->assertEquals($deferredUntil->format('Y-m-d'), $delivery['estimated_delivery_date']);
        $this->assertEquals(
            $issue->estimated_delivery_date->format('Y-m-d'),
            $delivery['scheduled_delivery_date']
        );
        $this->assertEquals('scheduled', $delivery['fulfilment_status']);
    }

    public function test_upcoming_issue_uses_estimated_delivery_date_without_fulfilment_row(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();
        $subscription = $this->createPrintSubscription($member->id, $plan->id, $plan->name);
        $issue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'subscription_id' => null,
            'issue_number' => 2,
            'issue_title' => 'Upcoming Delivery Issue',
            'status' => IssueScheduleStatus::ACTIVE->value,
            'on_sale_date' => (new \DateTime('-5 days'))->format('Y-m-d H:i:s'),
            'estimated_delivery_date' => (new \DateTime('+3 days'))->format('Y-m-d H:i:s'),
        ]);

        $delivery = $this->firstDeliveryFor($member->id, $subscription->id);

        $this->assertEquals($issue->id, $delivery['id']);
        $this->assertEquals('Upcoming Delivery Issue', $delivery['issue_title']);
        $this->assertEquals(
            $issue->estimated_delivery_date->format('Y-m-d'),
            $delivery['estimated_delivery_date']
        );
        $this->assertEquals(
            $issue->estimated_delivery_date->format('Y-m-d'),
            $delivery['scheduled_delivery_date']
        );
        $this->assertNull($delivery['fulfilment_status']);
    }

    private function createPrintSubscription(int $memberId, int $planId, string $planName): Subscription
    {
        return Subscription::create([
            'member_id' => $memberId,
            'site_id' => $this->siteId,
            'plan_id' => $planId,
            'plan_name' => $planName,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 10.00,
            'currency' => 'GBP',
            'delivery_type' => SubscriptionType::PRINTED->value,
            'type' => 'paid',
        ]);
    }

    private function firstDeliveryFor(int $memberId, int $subscriptionId): array
    {
        $member = \App\Models\Member::find($memberId);
        $this->actingAsMember($member);
        $response = $this->get(
            "/press-stack/account/subscriptions/{$subscriptionId}/issue-deliveries"
        );
        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(200, $response);
        $this->assertNotEmpty($data['data']['deliveries']);

        return $data['data']['deliveries'][0];
    }
}
