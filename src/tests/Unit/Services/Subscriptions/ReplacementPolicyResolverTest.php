<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Models\ReplacementPolicy;
use App\Models\Subscription;
use App\Repositories\Subscriptions\ReplacementPolicyRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\Policies\GoodwillPolicy;
use App\Services\Subscriptions\Policies\NoReplacementPolicy;
use App\Services\Subscriptions\Policies\StandardConsumerPolicy;
use App\Services\Subscriptions\ReplacementPolicyResolver;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ReplacementPolicyResolverTest extends TestCase
{
    private function policyModel(int $id, string $policyClass): ReplacementPolicy
    {
        $policy = Mockery::mock(ReplacementPolicy::class)->makePartial();
        $policy->id = $id;
        $policy->policy_class = $policyClass;

        return $policy;
    }

    public function test_it_returns_an_instance_of_the_plans_assigned_policy_class(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->plan_id = 20;

        $policyModel = $this->policyModel(5, StandardConsumerPolicy::class);

        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn($subscription);

        $policyRepository = Mockery::mock(ReplacementPolicyRepository::class);
        $policyRepository->shouldReceive('findForPlan')->once()->with(20)->andReturn($policyModel);
        $policyRepository->shouldNotReceive('findDefault');

        $resolver = new ReplacementPolicyResolver($subscriptionRepository, $policyRepository);

        $result = $resolver->resolveForSubscription(1, 10);

        $this->assertInstanceOf(StandardConsumerPolicy::class, $result);
        $this->assertSame(5, $result->id());
    }

    public function test_it_falls_back_to_an_instance_of_the_default_policy_class_when_plan_has_none(): void
    {
        $default = $this->policyModel(99, NoReplacementPolicy::class);

        $policyRepository = Mockery::mock(ReplacementPolicyRepository::class);
        $policyRepository->shouldReceive('findForPlan')->once()->with(20)->andReturn(null);
        $policyRepository->shouldReceive('findDefault')->once()->with(10)->andReturn($default);

        $resolver = new ReplacementPolicyResolver(Mockery::mock(SubscriptionRepository::class), $policyRepository);

        $result = $resolver->resolveForPlan(20, 10);

        $this->assertInstanceOf(NoReplacementPolicy::class, $result);
        $this->assertSame(99, $result->id());
    }

    public function test_it_throws_a_configuration_exception_when_no_default_policy_exists_at_all(): void
    {
        $policyRepository = Mockery::mock(ReplacementPolicyRepository::class);
        $policyRepository->shouldReceive('findForPlan')->once()->with(20)->andReturn(null);
        $policyRepository->shouldReceive('findDefault')->once()->with(10)->andReturn(null);

        $resolver = new ReplacementPolicyResolver(Mockery::mock(SubscriptionRepository::class), $policyRepository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No default replacement policy is configured for site #10.');

        $resolver->resolveForPlan(20, 10);
    }

    public function test_it_throws_when_subscription_is_not_found(): void
    {
        $subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $subscriptionRepository->shouldReceive('find')->once()->with(1)->andReturn(null);

        $resolver = new ReplacementPolicyResolver($subscriptionRepository, Mockery::mock(ReplacementPolicyRepository::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription not found.');

        $resolver->resolveForSubscription(1, 10);
    }

    public function test_it_throws_a_configuration_exception_when_the_policy_class_does_not_exist(): void
    {
        $policyModel = $this->policyModel(5, 'App\\Services\\Subscriptions\\Policies\\DoesNotExist');

        $policyRepository = Mockery::mock(ReplacementPolicyRepository::class);
        $policyRepository->shouldReceive('findForPlan')->once()->with(20)->andReturn($policyModel);

        $resolver = new ReplacementPolicyResolver(Mockery::mock(SubscriptionRepository::class), $policyRepository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Replacement policy #5 has an invalid or missing policy_class.');

        $resolver->resolveForPlan(20, 10);
    }

    public function test_resolve_goodwill_returns_an_instance_of_the_goodwill_policy_class(): void
    {
        $goodwillModel = $this->policyModel(7, GoodwillPolicy::class);

        $policyRepository = Mockery::mock(ReplacementPolicyRepository::class);
        $policyRepository->shouldReceive('findByClass')
            ->once()
            ->with(GoodwillPolicy::class, 10)
            ->andReturn($goodwillModel);

        $resolver = new ReplacementPolicyResolver(Mockery::mock(SubscriptionRepository::class), $policyRepository);

        $result = $resolver->resolveGoodwill(10);

        $this->assertInstanceOf(GoodwillPolicy::class, $result);
        $this->assertSame(7, $result->id());
    }

    public function test_resolve_goodwill_throws_a_configuration_exception_when_no_row_is_seeded(): void
    {
        $policyRepository = Mockery::mock(ReplacementPolicyRepository::class);
        $policyRepository->shouldReceive('findByClass')
            ->once()
            ->with(GoodwillPolicy::class, 10)
            ->andReturn(null);

        $resolver = new ReplacementPolicyResolver(Mockery::mock(SubscriptionRepository::class), $policyRepository);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No goodwill override policy is configured for site #10.');

        $resolver->resolveGoodwill(10);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
