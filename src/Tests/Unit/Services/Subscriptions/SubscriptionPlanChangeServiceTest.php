<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\PublicationChangeRebuildResult;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Enums\Subscriptions\SubscriptionDeliveryType;
use App\Events\Subscriptions\SubscriptionPlanChanged;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionChangeRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionIssueDeliveryRebuildService;
use App\Services\Subscriptions\SubscriptionPlanChangeService;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionPlanChangeServiceTest extends TestCase
{
    private SubscriptionRepository $subscriptionRepository;
    private SubscriptionPlanRepository $planRepository;
    private SubscriptionChangeRepository $changeRepository;
    private SubscriptionIssueDeliveryRebuildService $rebuildService;
    private Database $database;
    private CapturingEventDispatcher $events;

    private SubscriptionPlanChangeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->changeRepository = Mockery::mock(SubscriptionChangeRepository::class);
        $this->rebuildService = Mockery::mock(SubscriptionIssueDeliveryRebuildService::class);
        $this->database = Mockery::mock(Database::class);
        $this->events = CapturingEventDispatcher::fake();
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new SubscriptionPlanChangeService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->changeRepository,
            $this->rebuildService,
            $this->database,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_change_plan_updates_subscription_rebuilds_deliveries_and_records_audit(): void
    {
        $subscriptionId = 10;
        $oldPlanId = 20;
        $newPlanId = 30;
        $siteId = 1;
        $agentId = 99;
        $remainingIssues = 4;
        $reason = 'Customer requested different publication';

        $subscription = $this->subscription([
            'id' => $subscriptionId,
            'site_id' => $siteId,
            'plan_id' => $oldPlanId,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $oldPlan = $this->plan([
            'id' => $oldPlanId,
            'site_id' => $siteId,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $newPlan = $this->plan([
            'id' => $newPlanId,
            'site_id' => $siteId,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $rebuildResult = new PublicationChangeRebuildResult(
            oldEditionId: 1001,
            newEditionId: 2001,
            remainingIssuesTransferred: $remainingIssues,
        );

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with($subscriptionId)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with($oldPlanId)
            ->andReturn($oldPlan);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with($newPlanId)
            ->andReturn($newPlan);

        $this->rebuildService
            ->shouldReceive('countRemainingIssues')
            ->once()
            ->with($subscriptionId)
            ->andReturn($remainingIssues);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type(\Closure::class))
            ->andReturnUsing(fn (\Closure $callback) => $callback());

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->with($subscriptionId, [
                'plan_id' => $newPlanId,
            ])
            ->andReturn(Mockery::mock(SubscriptionPlan::class));

        $this->rebuildService
            ->shouldReceive('rebuildForPublicationChange')
            ->once()
            ->with($subscriptionId, $newPlanId, $remainingIssues)
            ->andReturn($rebuildResult);

        $this->changeRepository
            ->shouldReceive('recordPublicationChange')
            ->once()
            ->with(
                $subscriptionId,
                $oldPlanId,
                $newPlanId,
                1001,
                2001,
                $remainingIssues,
                $agentId,
                $reason,
            )
            ->andReturn(Mockery::mock(Model::class));

        $result = $this->service->changePlan(
            subscriptionId: $subscriptionId,
            newPlanId: $newPlanId,
            siteId: $siteId,
            agentId: $agentId,
            reason: $reason,
        );

        $this->assertSame($subscriptionId, $result->subscription_id);
        $this->assertSame($oldPlanId, $result->old_plan_id);
        $this->assertSame($newPlanId, $result->new_plan_id);
        $this->assertSame($oldPlanId, $result->old_publication_id);
        $this->assertSame($newPlanId, $result->new_publication_id);
        $this->assertSame(1001, $result->old_edition_id);
        $this->assertSame(2001, $result->new_edition_id);
        $this->assertSame($remainingIssues, $result->remaining_issues_transferred);
        $this->assertSame('Subscription plan changed successfully.', $result->message);
        $this->events->assertDispatched(
            SubscriptionPlanChanged::class,
            fn(SubscriptionPlanChanged $event): bool => $event->subscriptionId === $subscriptionId
                && $event->oldPlanId === $oldPlanId
                && $event->newPlanId === $newPlanId
                && $event->agentId === $agentId
        );
    }

    public function test_change_plan_throws_when_subscription_not_found(): void
    {
        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(999)
            ->andReturnNull();

        $this->assertNoMutationDependenciesAreCalled();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription #999 not found.');

        $this->service->changePlan(
            subscriptionId: 999,
            newPlanId: 30,
            siteId: 1,
            agentId: 99,
        );
    }

    public function test_change_plan_throws_when_subscription_belongs_to_different_site(): void
    {
        $subscription = $this->subscription([
            'id' => 10,
            'site_id' => 2,
            'plan_id' => 20,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($subscription);

        $this->assertNoMutationDependenciesAreCalled();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription does not belong to this site.');

        $this->service->changePlan(
            subscriptionId: 10,
            newPlanId: 30,
            siteId: 1,
            agentId: 99,
        );
    }

    public function test_change_plan_throws_when_subscription_is_not_active(): void
    {
        $subscription = $this->subscription([
            'id' => 10,
            'site_id' => 1,
            'plan_id' => 20,
            'status' => 'cancelled',
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($subscription);

        $this->assertNoMutationDependenciesAreCalled();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only active subscriptions can have their plan changed.');

        $this->service->changePlan(
            subscriptionId: 10,
            newPlanId: 30,
            siteId: 1,
            agentId: 99,
        );
    }

    public function test_change_plan_throws_when_selected_plan_is_same_as_current_plan(): void
    {
        $subscription = $this->subscription([
            'id' => 10,
            'site_id' => 1,
            'plan_id' => 20,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($subscription);

        $this->assertNoMutationDependenciesAreCalled();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected plan is the same as the current plan. No change required.');

        $this->service->changePlan(
            subscriptionId: 10,
            newPlanId: 20,
            siteId: 1,
            agentId: 99,
        );
    }

    public function test_change_plan_throws_when_current_plan_cannot_be_found(): void
    {
        $subscription = $this->subscription([
            'id' => 10,
            'site_id' => 1,
            'plan_id' => 20,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturnNull();

        $this->assertNoMutationDependenciesAreCalled();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Current subscription plan record not found.');

        $this->service->changePlan(
            subscriptionId: 10,
            newPlanId: 30,
            siteId: 1,
            agentId: 99,
        );
    }

    public function test_change_plan_throws_when_new_plan_cannot_be_found(): void
    {
        $subscription = $this->subscription();

        $oldPlan = $this->plan([
            'id' => 20,
            'site_id' => 1,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->with(10)
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturn($oldPlan);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(30)
            ->andReturnNull();

        $this->assertNoMutationDependenciesAreCalled();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription plan #30 not found.');

        $this->service->changePlan(
            subscriptionId: 10,
            newPlanId: 30,
            siteId: 1,
            agentId: 99,
        );
    }

    public function test_change_plan_throws_when_new_plan_is_inactive(): void
    {
        $subscription = $this->subscription();

        $oldPlan = $this->plan([
            'id' => 20,
            'site_id' => 1,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $newPlan = $this->plan([
            'id' => 30,
            'site_id' => 1,
            'is_active' => false,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturn($oldPlan);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(30)
            ->andReturn($newPlan);

        $this->assertNoMutationDependenciesAreCalled();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected plan is not active.');

        $this->service->changePlan(
            subscriptionId: 10,
            newPlanId: 30,
            siteId: 1,
            agentId: 99,
        );
    }

    public function test_change_plan_throws_when_new_plan_belongs_to_different_site(): void
    {
        $subscription = $this->subscription();

        $oldPlan = $this->plan([
            'id' => 20,
            'site_id' => 1,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $newPlan = $this->plan([
            'id' => 30,
            'site_id' => 2,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturn($oldPlan);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(30)
            ->andReturn($newPlan);

        $this->assertNoMutationDependenciesAreCalled();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected plan does not belong to the current site.');

        $this->service->changePlan(
            subscriptionId: 10,
            newPlanId: 30,
            siteId: 1,
            agentId: 99,
        );
    }

    public function test_change_plan_throws_when_delivery_type_is_incompatible(): void
    {
        $subscription = $this->subscription();

        $oldPlan = $this->plan([
            'id' => 20,
            'site_id' => 1,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $newPlan = $this->plan([
            'id' => 30,
            'site_id' => 1,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::DIGITAL,
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturn($oldPlan);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(30)
            ->andReturn($newPlan);

        $this->assertNoMutationDependenciesAreCalled();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan changes must stay within the same delivery type.');

        $this->service->changePlan(
            subscriptionId: 10,
            newPlanId: 30,
            siteId: 1,
            agentId: 99,
        );
    }

    public function test_remaining_issue_count_is_calculated_before_transaction_starts(): void
    {
        $subscription = $this->subscription();

        $oldPlan = $this->plan([
            'id' => 20,
            'site_id' => 1,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $newPlan = $this->plan([
            'id' => 30,
            'site_id' => 1,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $rebuildResult = new PublicationChangeRebuildResult(
            oldEditionId: 1001,
            newEditionId: 2001,
            remainingIssuesTransferred: 3,
        );

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturn($oldPlan);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(30)
            ->andReturn($newPlan);

        $this->rebuildService
            ->shouldReceive('countRemainingIssues')
            ->once()
            ->ordered('flow')
            ->with(10)
            ->andReturn(3);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->ordered('flow')
            ->with(Mockery::type(\Closure::class))
            ->andReturnUsing(fn (\Closure $callback) => $callback());

        $this->subscriptionRepository
            ->shouldReceive('update')
            ->once()
            ->andReturn(Mockery::mock(Subscription::class));

        $this->rebuildService
            ->shouldReceive('rebuildForPublicationChange')
            ->once()
            ->andReturn($rebuildResult);

        $this->changeRepository
            ->shouldReceive('recordPublicationChange')
            ->once()
            ->andReturn(Mockery::mock(Subscription::class));

        $result = $this->service->changePlan(
            subscriptionId: 10,
            newPlanId: 30,
            siteId: 1,
            agentId: 99,
        );

        $this->assertSame(3, $result->remaining_issues_transferred);
    }

    public function test_transaction_exception_bubbles_and_writes_are_not_faked(): void
    {
        $subscription = $this->subscription();

        $oldPlan = $this->plan([
            'id' => 20,
            'site_id' => 1,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $newPlan = $this->plan([
            'id' => 30,
            'site_id' => 1,
            'is_active' => true,
            'delivery_type' => SubscriptionDeliveryType::PRINT,
        ]);

        $this->subscriptionRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($subscription);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(20)
            ->andReturn($oldPlan);

        $this->planRepository
            ->shouldReceive('find')
            ->once()
            ->with(30)
            ->andReturn($newPlan);

        $this->rebuildService
            ->shouldReceive('countRemainingIssues')
            ->once()
            ->andReturn(3);

        $this->database
            ->shouldReceive('transaction')
            ->once()
            ->with(Mockery::type(\Closure::class))
            ->andThrow(new \RuntimeException('DB exploded'));

        $this->subscriptionRepository
            ->shouldNotReceive('update');

        $this->rebuildService
            ->shouldNotReceive('rebuildForPublicationChange');

        $this->changeRepository
            ->shouldNotReceive('recordPublicationChange');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB exploded');

        $this->service->changePlan(
            subscriptionId: 10,
            newPlanId: 30,
            siteId: 1,
            agentId: 99,
        );
    }

    private function assertNoMutationDependenciesAreCalled(): void
    {
        $this->rebuildService
            ->shouldNotReceive('countRemainingIssues');

        $this->database
            ->shouldNotReceive('transaction');

        $this->subscriptionRepository
            ->shouldNotReceive('update');

        $this->rebuildService
            ->shouldNotReceive('rebuildForPublicationChange');

        $this->changeRepository
            ->shouldNotReceive('recordPublicationChange');
    }

    private function subscription(array $overrides = []): Subscription&MockInterface
    {
        /** @var Subscription&MockInterface $subscription */
        $subscription = Mockery::mock(Subscription::class)->makePartial();

        foreach (array_merge([
            'id'      => 10,
            'site_id' => 1,
            'plan_id' => 20,
            'status'  => SubscriptionStatus::ACTIVE->value,
        ], $overrides) as $key => $value) {
            $subscription->{$key} = $value;
        }

        return $subscription;
    }

    private function plan(array $overrides = []): SubscriptionPlan&MockInterface
    {
        $deliveryType = $overrides['delivery_type'] ?? SubscriptionDeliveryType::PRINT;
        unset($overrides['delivery_type']);

        /** @var SubscriptionPlan&MockInterface $plan */
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();

        foreach (array_merge([
            'id'        => 20,
            'site_id'   => 1,
            'is_active' => true,
        ], $overrides) as $key => $value) {
            $plan->{$key} = $value;
        }

        $plan
            ->shouldReceive('getDeliveryType')
            ->zeroOrMoreTimes()
            ->andReturn($deliveryType);

        return $plan;
    }
}
