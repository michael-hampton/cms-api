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
        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => $plan->price ?? 10.00,
            'currency' => $plan->currency ?? 'GBP',
            'delivery_type' => SubscriptionType::PRINTED->value,
            'type' => 'paid',
        ]);
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

        $this->actingAsMember($member);
        $response = $this->get(
            "/press-stack/account/subscriptions/{$subscription->id}/issue-deliveries"
        );
        $data = json_decode($response->getContent(), true);
        $delivery = $data['data']['deliveries'][0];

        $this->assertResponseStatus(200, $response);
        $this->assertEquals($deferredUntil->format('Y-m-d'), $delivery['estimated_delivery_date']);
        $this->assertEquals(
            $issue->estimated_delivery_date->format('Y-m-d'),
            $delivery['scheduled_delivery_date']
        );
        $this->assertEquals('scheduled', $delivery['fulfilment_status']);
    }
}
