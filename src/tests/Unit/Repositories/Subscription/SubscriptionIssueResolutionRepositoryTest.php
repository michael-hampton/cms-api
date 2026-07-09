<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\DecisionSource;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Models\ReplacementPolicy;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionIssueResolutionRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SubscriptionIssueResolutionRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriptionIssueResolutionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SubscriptionIssueResolutionRepository();
    }

    /**
     * Not added to the shared CreatesTestData trait — kept local to this
     * test to avoid a wide edit to a file used by every repository test
     * in the suite for a policy record only this feature needs.
     */
    private function createReplacementPolicy(array $overrides = []): ReplacementPolicy
    {
        return ReplacementPolicy::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Policy ' . uniqid(),
            'description' => 'A test replacement policy',
            'allows_replacements' => true,
            'allows_extensions' => true,
            'max_replacements' => null,
            'max_extensions' => null,
            'require_stock' => true,
            'requires_manager_approval' => false,
            'is_default' => false,
            'active' => true,
        ], $overrides));
    }

    private function createSubscriptionWithPlanAndPolicy(): array
    {
        $policy = $this->createReplacementPolicy();

        $plan = $this->createSubscriptionPlan([
            'replacement_policy_id' => $policy->id,
        ]);

        $subscription = $this->createSubscription([
            'plan_id' => $plan->id,
        ]);

        $issue = $this->createIssueDelivery([
            'subscription_plan_id' => $plan->id,
        ]);

        return [$subscription, $plan, $policy, $issue];
    }

    public function test_has_open_resolution_returns_true_for_open_decision(): void
    {
        [$subscription, , $policy, $issue] = $this->createSubscriptionWithPlanAndPolicy();

        $this->repository->createReplacementResolution(
            $this->siteId,
            $subscription->id,
            $issue->id,
            ReplacementResolution::REPLACE,
            'Damaged in transit',
            DecisionSource::POLICY,
            1,
            $policy->id
        );

        $this->assertTrue($this->repository->hasOpenResolution($subscription->id, $issue->id));
    }

    public function test_has_open_resolution_returns_false_when_none_exists(): void
    {
        [$subscription, , , $issue] = $this->createSubscriptionWithPlanAndPolicy();

        $this->assertFalse($this->repository->hasOpenResolution($subscription->id, $issue->id));
    }

    public function test_has_open_resolution_is_scoped_to_subscription_and_issue(): void
    {
        [$subscription, , $policy, $issue] = $this->createSubscriptionWithPlanAndPolicy();
        [$otherSubscription, , , $otherIssue] = $this->createSubscriptionWithPlanAndPolicy();

        $this->repository->createReplacementResolution(
            $this->siteId,
            $otherSubscription->id,
            $otherIssue->id,
            ReplacementResolution::REPLACE,
            'Damaged in transit',
            DecisionSource::POLICY,
            1,
            $policy->id
        );

        $this->assertFalse($this->repository->hasOpenResolution($subscription->id, $issue->id));
        $this->assertTrue($this->repository->hasOpenResolution($otherSubscription->id, $otherIssue->id));
    }

    public function test_create_replacement_resolution_persists_decision_source_and_policy_id(): void
    {
        [$subscription, , $policy, $issue] = $this->createSubscriptionWithPlanAndPolicy();

        $resolution = $this->repository->createReplacementResolution(
            $this->siteId,
            $subscription->id,
            $issue->id,
            ReplacementResolution::REPLACE,
            'Damaged in transit',
            DecisionSource::BUSINESS_OVERRIDE,
            42,
            $policy->id,
            null,
            null,
            ['stock_decremented' => true]
        );

        $this->assertNotNull($resolution->id);
        $this->assertSame(ReplacementResolution::REPLACE->value, $resolution->decision);
        $this->assertSame(DecisionSource::BUSINESS_OVERRIDE->value, $resolution->decision_source);
        $this->assertSame($policy->id, $resolution->replacement_policy_id);
        $this->assertSame(42, $resolution->created_by);
        $this->assertTrue($resolution->metadata['stock_decremented']);

        $fresh = $this->fresh($resolution);
        $this->assertSame(DecisionSource::BUSINESS_OVERRIDE->value, $fresh->decision_source);
        $this->assertSame($policy->id, $fresh->replacement_policy_id);
    }

    public function test_count_decisions_for_subscription_counts_only_matching_decision_type(): void
    {
        [$subscription, , $policy, $issue] = $this->createSubscriptionWithPlanAndPolicy();
        $secondIssue = $this->createIssueDelivery(['subscription_plan_id' => $issue->subscription_plan_id]);
        $thirdIssue = $this->createIssueDelivery(['subscription_plan_id' => $issue->subscription_plan_id]);

        $this->repository->createReplacementResolution(
            $this->siteId,
            $subscription->id,
            $issue->id,
            ReplacementResolution::REPLACE,
            'Damaged in transit',
            DecisionSource::POLICY,
            1,
            $policy->id
        );

        $this->repository->createReplacementResolution(
            $this->siteId,
            $subscription->id,
            $secondIssue->id,
            ReplacementResolution::REPLACE,
            'Lost in transit',
            DecisionSource::POLICY,
            1,
            $policy->id
        );

        $this->repository->createReplacementResolution(
            $this->siteId,
            $subscription->id,
            $thirdIssue->id,
            ReplacementResolution::EXTEND,
            'Late delivery',
            DecisionSource::POLICY,
            1,
            $policy->id
        );

        $this->assertSame(
            2,
            $this->repository->countDecisionsForSubscription($subscription->id, ReplacementResolution::REPLACE)
        );

        $this->assertSame(
            1,
            $this->repository->countDecisionsForSubscription($subscription->id, ReplacementResolution::EXTEND)
        );
    }

    public function test_count_decisions_for_subscription_is_scoped_per_subscription(): void
    {
        [$subscription, , $policy, $issue] = $this->createSubscriptionWithPlanAndPolicy();
        [$otherSubscription, , , $otherIssue] = $this->createSubscriptionWithPlanAndPolicy();

        $this->repository->createReplacementResolution(
            $this->siteId,
            $subscription->id,
            $issue->id,
            ReplacementResolution::REPLACE,
            'Damaged in transit',
            DecisionSource::POLICY,
            1,
            $policy->id
        );

        $this->repository->createReplacementResolution(
            $this->siteId,
            $otherSubscription->id,
            $otherIssue->id,
            ReplacementResolution::REPLACE,
            'Damaged in transit',
            DecisionSource::POLICY,
            1,
            $policy->id
        );

        $this->assertSame(
            1,
            $this->repository->countDecisionsForSubscription($subscription->id, ReplacementResolution::REPLACE)
        );

        $this->assertSame(
            0,
            $this->repository->countDecisionsForSubscription($otherSubscription->id, ReplacementResolution::EXTEND)
        );
    }
}