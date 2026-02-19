<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Adverts\PlanMatchRule;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PlanMatchRuleTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testEvaluateReturnsHideWhenNoMember(): void
    {
        $rule = new PlanMatchRule('premium');
        $decision = $rule->evaluate(null);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::NOT_AUTHENTICATED, $decision->reason);
    }

    public function testEvaluateReturnsHideWhenPlanMismatch(): void
    {
        $member = new Member(['plan' => 'free']);
        $rule = new PlanMatchRule('premium');
        $decision = $rule->evaluate($member);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::PLAN_MISMATCH, $decision->reason);
    }

    public function testEvaluateReturnsShowWhenPlanMatches(): void
    {
        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 99.99,
            'currency' => 'USD',
            'billing_period' => 'yearly',
            'plan_type' => 'recurring',
            'is_active' => true,
        ]);

        $member = $this->createMember();

        Subscription::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'status' => 'active',
            'type' => 'paid',
            'start_date' => date('Y-m-d H:i:s'),
            'plan_name' => 'Premium Plan'
        ]);

        $rule = new PlanMatchRule('premium');
        $decision = $rule->evaluate($member);

        $this->assertTrue($decision->shouldRender);
    }
}
