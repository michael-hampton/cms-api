<?php

namespace App\Tests\Functional\Controllers\Members\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Models\IssueDelivery;
use App\Models\SubscriptionIssueFulfilment;
use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSubscriptionPauseRowsTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_pause_creates_a_deferred_delivery_row_and_keeps_schedule_date(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $originalDate = $issue->estimated_delivery_date->format('Y-m-d H:i:s');
        $pauseStart = (new \DateTime('+1 day'))->format('Y-m-d');
        $pauseEnd = (new \DateTime('+14 days'))->format('Y-m-d');

        $response = $this->postForSite(
            "/member/subscriptions/{$subscription->id}/pause-delivery",
            [
                'pause_start' => $pauseStart,
                'pause_end' => $pauseEnd,
                'reason' => 'Holiday',
            ],
            forMember: true,
        );

        $this->assertEquals(
            200,
            $response->getStatusCode(),
            $response->getContent() . ' ' . json_encode($response->getHeaders())
        );

        $row = SubscriptionIssueFulfilment::where('subscription_id', $subscription->id)
            ->where('issue_delivery_id', $issue->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals(
            (new \DateTime($pauseEnd))->modify('+1 day')->format('Y-m-d'),
            $row->deferred_until->format('Y-m-d')
        );

        $schedule = IssueDelivery::find($issue->id);
        $this->assertEquals($originalDate, $schedule->estimated_delivery_date->format('Y-m-d H:i:s'));
    }

    public function test_resume_clears_the_deferred_date(): void
    {
        [$subscription, $issue] = $this->createSubscriptionAndIssue();
        $subscription->update([
            'delivery_paused' => true,
            'delivery_pause_start' => (new \DateTime('-1 day'))->format('Y-m-d H:i:s'),
            'delivery_pause_end' => (new \DateTime('+14 days'))->format('Y-m-d H:i:s'),
        ]);

        SubscriptionIssueFulfilment::create([
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issue->id,
            'status' => 'scheduled',
            'attempts' => 0,
            'scheduled_for' => $issue->estimated_delivery_date->format('Y-m-d H:i:s'),
            'deferred_until' => (new \DateTime('+15 days'))->format('Y-m-d H:i:s'),
        ]);

        $response = $this->postForSite(
            "/member/subscriptions/{$subscription->id}/resume-delivery",
            forMember: true,
        );

        $this->assertEquals(
            200,
            $response->getStatusCode(),
            $response->getContent() . ' ' . json_encode($response->getHeaders())
        );

        $row = SubscriptionIssueFulfilment::where('subscription_id', $subscription->id)
            ->where('issue_delivery_id', $issue->id)
            ->first();

        $this->assertNull($row->deferred_until);
    }

    private function createSubscriptionAndIssue(): array
    {
        $member = $this->createMember();
        $this->actingAsMember($member);
        $plan = $this->createSubscriptionPlan();

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'delivery_type' => SubscriptionType::PRINTED->value,
        ]);

        $issue = IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_plan_id' => $plan->id,
            'subscription_id' => null,
            'issue_number' => 1,
            'issue_title' => 'Test Issue',
            'status' => IssueScheduleStatus::ACTIVE->value,
            'on_sale_date' => (new \DateTime('+7 days'))->format('Y-m-d H:i:s'),
            'estimated_delivery_date' => (new \DateTime('+8 days'))->format('Y-m-d H:i:s'),
        ]);

        return [$subscription, $issue];
    }
}
