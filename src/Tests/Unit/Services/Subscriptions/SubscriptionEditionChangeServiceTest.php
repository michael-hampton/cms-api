<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Events\Subscriptions\SubscriptionEditionChanged;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\Model;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\SubscriptionChangeRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionEditionChangeService;
use App\Services\Subscriptions\SubscriptionIssueDeliveryRebuildService;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionEditionChangeServiceTest extends TestCase
{
    private const SITE_ID = 1;
    private const AGENT_ID = 99;
    private const SUB_ID = 10;
    private const PLAN_ID = 50;
    private const OLD_EDITION_ID = 20;
    private const NEW_EDITION_ID = 30;

    private SubscriptionRepository&MockInterface $subscriptionRepository;
    private SubscriptionPlanRepository&MockInterface $planRepository;
    private IssueDeliveryRepository&MockInterface $issueDeliveryRepository;
    private SubscriptionChangeRepository&MockInterface $changeRepository;
    private SubscriptionIssueDeliveryRebuildService&MockInterface $rebuildService;
    private Database&MockInterface $database;
    private CapturingEventDispatcher $events;
    private SubscriptionEditionChangeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->changeRepository = Mockery::mock(SubscriptionChangeRepository::class);
        $this->rebuildService = Mockery::mock(SubscriptionIssueDeliveryRebuildService::class);
        $this->database = Mockery::mock(Database::class);
        $this->events = CapturingEventDispatcher::fake();
        $this->database->shouldReceive('transaction')->byDefault()->andReturnUsing(fn(callable $callback) => $callback());
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();
        $this->service = new SubscriptionEditionChangeService(
            $this->subscriptionRepository,
            $this->planRepository,
            $this->issueDeliveryRepository,
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

    public function test_throws_when_subscription_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->with(self::SUB_ID)->andReturnNull();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not found');
        $this->changeEdition();
    }

    public function test_throws_when_subscription_belongs_to_different_site(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription(['site_id' => 999]));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong to this site');
        $this->changeEdition();
    }

    public function test_throws_when_subscription_is_not_active(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription(['status' => SubscriptionStatus::CANCELLED->value]));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Only active subscriptions');
        $this->changeEdition();
    }

    public function test_throws_when_current_plan_not_found(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription());
        $this->planRepository->shouldReceive('find')->once()->with(self::PLAN_ID)->andReturnNull();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Current subscription plan record not found');
        $this->changeEdition();
    }

    public function test_throws_when_current_plan_belongs_to_different_site(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->andReturn($this->makeSubscription());
        $this->planRepository->shouldReceive('find')->once()->andReturn($this->makePlan(['site_id' => 999]));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Current subscription plan does not belong to this site');
        $this->changeEdition();
    }

    public function test_throws_when_selected_edition_not_found(): void
    {
        $this->arrangeSubscriptionAndPlan();
        $this->issueDeliveryRepository->shouldReceive('find')->once()->with(self::NEW_EDITION_ID)->andReturnNull();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Edition #' . self::NEW_EDITION_ID . ' not found');
        $this->changeEdition();
    }

    public function test_throws_when_selected_edition_does_not_belong_to_subscription_plan(): void
    {
        $this->arrangeSubscriptionAndPlan();
        $this->issueDeliveryRepository->shouldReceive('find')->once()->andReturn($this->makeIssueDelivery(['subscription_plan_id' => 999]));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong to the subscription plan');
        $this->changeEdition();
    }

    public function test_throws_when_selected_edition_is_inactive(): void
    {
        $this->arrangeSubscriptionAndPlan();
        $this->issueDeliveryRepository->shouldReceive('find')->once()->andReturn($this->makeIssueDelivery(['status' => 'inactive']));
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not active');
        $this->changeEdition();
    }

    public function test_throws_when_no_current_future_edition_exists(): void
    {
        $this->arrangeSubscriptionPlanAndEdition();
        $this->rebuildService->shouldReceive('resolveCurrentFutureEditionId')->once()->with(self::SUB_ID)->andReturnNull();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('no future issues');
        $this->changeEdition();
    }

    public function test_throws_when_selected_edition_is_same_as_current_next_edition(): void
    {
        $this->arrangeSubscriptionPlanAndEdition();
        $this->rebuildService->shouldReceive('resolveCurrentFutureEditionId')->once()->with(self::SUB_ID)->andReturn(self::NEW_EDITION_ID);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('same as the current next edition');
        $this->changeEdition();
    }

    public function test_does_not_update_subscription_plan_id(): void
    {
        $this->arrangeValidPreconditions();
        $this->subscriptionRepository->shouldNotReceive('update');
        $this->expectRebuild();
        $this->changeRepository->shouldReceive('recordEditionChange')->once()->andReturn(Mockery::mock(Model::class));
        $result = $this->changeEdition();
        $this->assertEquals(self::SUB_ID, $result->subscription_id);
        $this->events->assertDispatched(SubscriptionEditionChanged::class);
    }

    public function test_calls_rebuild_service_with_correct_arguments(): void
    {
        $this->arrangeValidPreconditions();
        $this->expectRebuild();
        $this->changeRepository->shouldReceive('recordEditionChange')->once()->andReturn(Mockery::mock(Model::class));
        $this->changeEdition();
        $this->assertTrue(true);
    }

    public function test_records_audit_row_with_correct_arguments(): void
    {
        $this->arrangeValidPreconditions();
        $this->expectRebuild();
        $this->changeRepository->shouldReceive('recordEditionChange')->once()->with(
            self::SUB_ID,
            self::OLD_EDITION_ID,
            self::NEW_EDITION_ID,
            self::AGENT_ID,
            'test reason',
        )->andReturn(Mockery::mock(Model::class));
        $this->changeEdition('test reason');
        $this->assertTrue(true);
    }

    public function test_passes_null_reason_to_audit_row_when_not_provided(): void
    {
        $this->arrangeValidPreconditions();
        $this->expectRebuild();
        $this->changeRepository->shouldReceive('recordEditionChange')->once()->with(
            self::SUB_ID,
            self::OLD_EDITION_ID,
            self::NEW_EDITION_ID,
            self::AGENT_ID,
            null,
        )->andReturn(Mockery::mock(Model::class));
        $this->changeEdition();
        $this->assertTrue(true);
    }

    public function test_returns_result_with_expected_shape(): void
    {
        $this->arrangeValidPreconditions();
        $this->expectRebuild();
        $this->changeRepository->shouldReceive('recordEditionChange')->once()->andReturn(Mockery::mock(Model::class));
        $result = $this->changeEdition();
        $this->assertEquals(self::SUB_ID, $result->subscription_id);
        $this->assertEquals(self::OLD_EDITION_ID, $result->old_edition_id);
        $this->assertEquals(self::NEW_EDITION_ID, $result->new_edition_id);
        $this->assertEquals('Subscription edition changed successfully.', $result->message);
    }

    public function test_transaction_is_used_for_all_writes(): void
    {
        $this->arrangeValidPreconditions();
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn(callable $callback) => $callback());
        $this->expectRebuild();
        $this->changeRepository->shouldReceive('recordEditionChange')->once()->andReturn(Mockery::mock(Model::class));
        $this->changeEdition();
        $this->assertTrue(true);
    }

    public function test_no_writes_occur_outside_transaction_boundary(): void
    {
        $this->arrangeValidPreconditions();
        $finished = false;
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(function (callable $callback) use (&$finished) {
            $result = $callback();
            $finished = true;
            return $result;
        });
        $this->rebuildService->shouldReceive('rebuildForEditionChange')->once()->andReturnUsing(function () use (&$finished) {
            $this->assertFalse($finished);
        });
        $this->changeRepository->shouldReceive('recordEditionChange')->once()->andReturnUsing(function () use (&$finished) {
            $this->assertFalse($finished);
            return Mockery::mock(Model::class);
        });
        $this->changeEdition();
    }

    private function arrangeSubscriptionAndPlan(): void
    {
        $this->subscriptionRepository->shouldReceive('find')->once()->with(self::SUB_ID)->andReturn($this->makeSubscription());
        $this->planRepository->shouldReceive('find')->once()->with(self::PLAN_ID)->andReturn($this->makePlan());
    }

    private function arrangeSubscriptionPlanAndEdition(): void
    {
        $this->arrangeSubscriptionAndPlan();
        $this->issueDeliveryRepository->shouldReceive('find')->once()->with(self::NEW_EDITION_ID)->andReturn($this->makeIssueDelivery());
    }

    private function arrangeValidPreconditions(): void
    {
        $this->arrangeSubscriptionPlanAndEdition();
        $this->rebuildService->shouldReceive('resolveCurrentFutureEditionId')->once()->with(self::SUB_ID)->andReturn(self::OLD_EDITION_ID);
        $this->rebuildService->shouldReceive('countRemainingIssues')->once()->with(self::SUB_ID)->andReturn(3);
    }

    private function expectRebuild(): void
    {
        $this->rebuildService->shouldReceive('rebuildForEditionChange')->once()->with(
            self::SUB_ID,
            self::PLAN_ID,
            self::NEW_EDITION_ID,
            3,
        );
    }

    private function changeEdition(?string $reason = null): object
    {
        return $this->service->changeEdition(
            self::SUB_ID,
            self::NEW_EDITION_ID,
            self::SITE_ID,
            self::AGENT_ID,
            $reason,
        );
    }

    private function makeSubscription(array $overrides = []): Subscription&MockInterface
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $overrides['id'] ?? self::SUB_ID;
        $subscription->site_id = $overrides['site_id'] ?? self::SITE_ID;
        $subscription->status = $overrides['status'] ?? SubscriptionStatus::ACTIVE->value;
        $subscription->plan_id = $overrides['plan_id'] ?? self::PLAN_ID;
        return $subscription;
    }

    private function makePlan(array $overrides = []): SubscriptionPlan&MockInterface
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = $overrides['id'] ?? self::PLAN_ID;
        $plan->site_id = $overrides['site_id'] ?? self::SITE_ID;
        return $plan;
    }

    private function makeIssueDelivery(array $overrides = []): IssueDelivery&MockInterface
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = $overrides['id'] ?? self::NEW_EDITION_ID;
        $issue->subscription_plan_id = $overrides['subscription_plan_id'] ?? self::PLAN_ID;
        $issue->status = $overrides['status'] ?? 'active';
        return $issue;
    }
}
