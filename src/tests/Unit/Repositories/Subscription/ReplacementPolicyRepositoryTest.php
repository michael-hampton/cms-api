<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Models\ReplacementPolicy;
use App\Repositories\Subscriptions\ReplacementPolicyRepository;
use App\Services\Subscriptions\Policies\StandardConsumerPolicy;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ReplacementPolicyRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ReplacementPolicyRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ReplacementPolicyRepository();
    }

    private function createReplacementPolicy(array $overrides = []): ReplacementPolicy
    {
        return ReplacementPolicy::create(array_merge([
            'site_id' => $this->siteId,
            'name' => 'Test Policy ' . uniqid(),
            'description' => 'A test replacement policy',
            'policy_class' => StandardConsumerPolicy::class,
            'is_default' => false,
            'active' => true,
        ], $overrides));
    }

    public function test_find_default_returns_the_active_default_policy_for_the_site(): void
    {
        $this->createReplacementPolicy(['is_default' => false]);
        $default = $this->createReplacementPolicy(['is_default' => true]);

        $result = $this->repository->findDefault($this->siteId);

        $this->assertNotNull($result);
        $this->assertSame($default->id, $result->id);
    }

    public function test_find_default_ignores_inactive_default_policy(): void
    {
        $this->createReplacementPolicy(['is_default' => true, 'active' => false]);

        $result = $this->repository->findDefault($this->siteId);

        $this->assertNull($result);
    }

    public function test_find_default_is_scoped_to_site(): void
    {
        $this->createReplacementPolicy(['is_default' => true]);

        $otherSite = $this->createSite();
        $otherDefault = ReplacementPolicy::create([
            'site_id' => $otherSite->id,
            'name' => 'Other Site Default',
            'policy_class' => StandardConsumerPolicy::class,
            'is_default' => true,
            'active' => true,
        ]);

        $result = $this->repository->findDefault($otherSite->id);

        $this->assertSame($otherDefault->id, $result->id);
    }

    public function test_find_for_plan_returns_the_plans_assigned_policy(): void
    {
        $policy = $this->createReplacementPolicy();
        $plan = $this->createSubscriptionPlan(['replacement_policy_id' => $policy->id]);

        $result = $this->repository->findForPlan($plan->id);

        $this->assertNotNull($result);
        $this->assertSame($policy->id, $result->id);
    }

    public function test_find_for_plan_returns_null_when_plan_has_no_policy_assigned(): void
    {
        $plan = $this->createSubscriptionPlan();

        $result = $this->repository->findForPlan($plan->id);

        $this->assertNull($result);
    }

    public function test_find_for_plan_returns_null_when_assigned_policy_is_inactive(): void
    {
        $policy = $this->createReplacementPolicy(['active' => false]);
        $plan = $this->createSubscriptionPlan(['replacement_policy_id' => $policy->id]);

        $result = $this->repository->findForPlan($plan->id);

        $this->assertNull($result);
    }
}