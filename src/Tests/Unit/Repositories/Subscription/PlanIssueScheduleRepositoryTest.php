<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\PlanIssueScheduleRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PlanIssueScheduleRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_finds_issue_sold_before_pause_when_delivery_falls_inside_window(): void
    {
        $plan = $this->createSubscriptionPlan();
        $pauseStart = new \DateTime('+5 days');
        $pauseEnd = new \DateTime('+15 days');

        $inside = $this->createIssue(
            $plan->id,
            new \DateTime('+2 days'),
            new \DateTime('+10 days')
        );
        $this->createIssue(
            $plan->id,
            new \DateTime('+3 days'),
            new \DateTime('+20 days')
        );

        $issues = (new PlanIssueScheduleRepository())->findWithinDeliveryWindow(
            $plan->id,
            $pauseStart,
            $pauseEnd
        );

        $this->assertEquals([$inside->id], $issues->pluck('id')->toArray());
    }

    public function test_uses_on_sale_date_when_estimated_delivery_date_is_missing(): void
    {
        $plan = $this->createSubscriptionPlan();
        $pauseStart = new \DateTime('+5 days');
        $pauseEnd = new \DateTime('+15 days');

        $inside = $this->createIssue(
            $plan->id,
            new \DateTime('+10 days'),
            null
        );

        $issues = (new PlanIssueScheduleRepository())->findWithinDeliveryWindow(
            $plan->id,
            $pauseStart,
            $pauseEnd
        );

        $this->assertEquals([$inside->id], $issues->pluck('id')->toArray());
    }

    private function createIssue(
        int $planId,
        \DateTimeInterface $onSaleDate,
        ?\DateTimeInterface $estimatedDeliveryDate
    ): IssueDelivery {
        return IssueDelivery::create([
            'site_id' => $this->siteId,
            'subscription_plan_id' => $planId,
            'subscription_id' => null,
            'issue_number' => random_int(1, 100000),
            'issue_title' => 'Test Issue',
            'status' => IssueScheduleStatus::ACTIVE->value,
            'on_sale_date' => $onSaleDate->format('Y-m-d H:i:s'),
            'estimated_delivery_date' => $estimatedDeliveryDate?->format('Y-m-d H:i:s'),
        ]);
    }
}
