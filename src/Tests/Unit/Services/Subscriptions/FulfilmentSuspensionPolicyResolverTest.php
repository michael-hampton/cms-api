<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\FulfilmentSuspensionRule;
use App\Enums\Subscriptions\FulfilmentSuspensionDelayType;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Subscriptions\FulfilmentSuspensionPolicyResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class FulfilmentSuspensionPolicyResolverTest extends TestCase
{
    private $planRepository;
    private $fulfilmentRepository;
    private FulfilmentSuspensionPolicyResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->fulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);

        $this->resolver = new FulfilmentSuspensionPolicyResolver(
            $this->planRepository,
            $this->fulfilmentRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makePlan(?string $type, $value): object
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->fulfilment_suspension_delay_type = $type;
        $plan->fulfilment_suspension_delay_value = $value;

        return $plan;
    }

    public function test_resolve_defaults_to_immediate_when_plan_not_found(): void
    {
        $this->planRepository->shouldReceive('find')->with(1)->andReturn(null);

        $rule = $this->resolver->resolveForPlan(1);

        $this->assertTrue($rule->isImmediate());
        $this->assertSame(FulfilmentSuspensionDelayType::IMMEDIATE, $rule->type);
    }

    public function test_resolve_defaults_to_immediate_when_no_override_set(): void
    {
        $this->planRepository->shouldReceive('find')->with(1)->andReturn($this->makePlan(null, null));

        $rule = $this->resolver->resolveForPlan(1);

        $this->assertTrue($rule->isImmediate());
    }

    public function test_resolve_defaults_to_immediate_when_type_is_explicitly_immediate(): void
    {
        $this->planRepository->shouldReceive('find')->with(1)->andReturn($this->makePlan('immediate', 5));

        $rule = $this->resolver->resolveForPlan(1);

        $this->assertTrue($rule->isImmediate());
    }

    public function test_resolve_defaults_to_immediate_when_days_value_is_zero(): void
    {
        $this->planRepository->shouldReceive('find')->with(1)->andReturn($this->makePlan('days', 0));

        $rule = $this->resolver->resolveForPlan(1);

        $this->assertTrue($rule->isImmediate());
    }

    public function test_resolve_defaults_to_immediate_when_value_missing(): void
    {
        $this->planRepository->shouldReceive('find')->with(1)->andReturn($this->makePlan('days', null));

        $rule = $this->resolver->resolveForPlan(1);

        $this->assertTrue($rule->isImmediate());
    }

    public function test_resolve_returns_days_rule_with_valid_override(): void
    {
        $this->planRepository->shouldReceive('find')->with(1)->andReturn($this->makePlan('days', 14));

        $rule = $this->resolver->resolveForPlan(1);

        $this->assertFalse($rule->isImmediate());
        $this->assertSame(FulfilmentSuspensionDelayType::DAYS, $rule->type);
        $this->assertSame(14, $rule->value);
    }

    public function test_resolve_returns_issues_rule_with_valid_override(): void
    {
        $this->planRepository->shouldReceive('find')->with(1)->andReturn($this->makePlan('issues', 3));

        $rule = $this->resolver->resolveForPlan(1);

        $this->assertFalse($rule->isImmediate());
        $this->assertSame(FulfilmentSuspensionDelayType::ISSUES, $rule->type);
        $this->assertSame(3, $rule->value);
    }

    public function test_suspension_due_immediate_is_always_true(): void
    {
        $now = new \DateTimeImmutable('2026-07-20');

        $due = $this->resolver->isSuspensionDue(5, FulfilmentSuspensionRule::immediate(), $now);

        $this->assertTrue($due);
    }

    public function test_suspension_due_true_when_no_issue_delivered_yet_regardless_of_rule(): void
    {
        $this->fulfilmentRepository->shouldReceive('firstDeliveredAt')->with(5)->andReturn(null);

        $now = new \DateTimeImmutable('2026-07-20');

        $due = $this->resolver->isSuspensionDue(5, FulfilmentSuspensionRule::afterDays(30), $now);

        $this->assertTrue($due);
    }

    public function test_days_rule_not_due_before_delay_elapses(): void
    {
        $firstDelivered = new \DateTimeImmutable('2026-07-01');
        $this->fulfilmentRepository->shouldReceive('firstDeliveredAt')->with(5)->andReturn($firstDelivered);

        $now = new \DateTimeImmutable('2026-07-20'); // 19 days later

        $due = $this->resolver->isSuspensionDue(5, FulfilmentSuspensionRule::afterDays(30), $now);

        $this->assertFalse($due);
    }

    public function test_days_rule_due_once_delay_elapses(): void
    {
        $firstDelivered = new \DateTimeImmutable('2026-06-01');
        $this->fulfilmentRepository->shouldReceive('firstDeliveredAt')->with(5)->andReturn($firstDelivered);

        $now = new \DateTimeImmutable('2026-07-20'); // 49 days later

        $due = $this->resolver->isSuspensionDue(5, FulfilmentSuspensionRule::afterDays(30), $now);

        $this->assertTrue($due);
    }

    public function test_days_rule_due_exactly_on_boundary(): void
    {
        $firstDelivered = new \DateTimeImmutable('2026-06-20');
        $this->fulfilmentRepository->shouldReceive('firstDeliveredAt')->with(5)->andReturn($firstDelivered);

        $now = new \DateTimeImmutable('2026-07-20'); // exactly 30 days later

        $due = $this->resolver->isSuspensionDue(5, FulfilmentSuspensionRule::afterDays(30), $now);

        $this->assertTrue($due);
    }

    public function test_issues_rule_not_due_when_not_enough_further_issues_delivered(): void
    {
        $this->fulfilmentRepository->shouldReceive('firstDeliveredAt')->with(5)->andReturn(new \DateTimeImmutable('-1 year'));
        $this->fulfilmentRepository->shouldReceive('countDeliveredForSubscription')->with(5)->andReturn(2);

        $due = $this->resolver->isSuspensionDue(5, FulfilmentSuspensionRule::afterIssues(3), new \DateTimeImmutable());

        // Needs 1 (first) + 3 = 4 delivered; only 2 delivered so far.
        $this->assertFalse($due);
    }

    public function test_issues_rule_due_once_enough_further_issues_delivered(): void
    {
        $this->fulfilmentRepository->shouldReceive('firstDeliveredAt')->with(5)->andReturn(new \DateTimeImmutable('-1 year'));
        $this->fulfilmentRepository->shouldReceive('countDeliveredForSubscription')->with(5)->andReturn(4);

        $due = $this->resolver->isSuspensionDue(5, FulfilmentSuspensionRule::afterIssues(3), new \DateTimeImmutable());

        $this->assertTrue($due);
    }
}
