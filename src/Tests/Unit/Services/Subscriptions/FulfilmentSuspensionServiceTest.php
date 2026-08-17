<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\FulfilmentSuspensionRule;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\FulfilmentSuspensionPolicyResolver;
use App\Services\Subscriptions\FulfilmentSuspensionService;
use Mockery;
use PHPUnit\Framework\TestCase;

class FulfilmentSuspensionServiceTest extends TestCase
{
    private $policyResolver;
    private $fulfilmentRepository;
    private $subscriptionRepository;
    private $database;
    private $logger;
    private FulfilmentSuspensionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policyResolver = Mockery::mock(FulfilmentSuspensionPolicyResolver::class);
        $this->fulfilmentRepository = Mockery::mock(SubscriptionIssueFulfilmentRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->database = Mockery::mock(Database::class);
        $this->database->shouldReceive('transaction')
            ->byDefault()
            ->andReturnUsing(fn (callable $callback) => $callback());
        $this->logger = Mockery::mock(Logger::class)->shouldIgnoreMissing();

        $this->service = new FulfilmentSuspensionService(
            $this->policyResolver,
            $this->fulfilmentRepository,
            $this->subscriptionRepository,
            $this->database,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeSubscription(array $overrides = []): object
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 42;
        $subscription->plan_id = 7;
        $subscription->fulfilment_suspension_pending = false;
        $subscription->fulfilment_suspension_reason = null;

        foreach ($overrides as $key => $value) {
            $subscription->{$key} = $value;
        }

        return $subscription;
    }

    public function test_handle_trigger_suspends_immediately_when_due(): void
    {
        $subscription = $this->makeSubscription();
        $rule = FulfilmentSuspensionRule::immediate();

        $this->policyResolver->shouldReceive('resolveForPlan')->with(7)->andReturn($rule);
        $this->policyResolver->shouldReceive('isSuspensionDue')->with(42, $rule, Mockery::type(\DateTimeImmutable::class))->andReturn(true);

        $this->fulfilmentRepository->shouldReceive('suspendPendingForSubscription')
            ->once()
            ->with(42, FulfilmentSuspensionService::REASON_PAYMENT_FAILED)
            ->andReturn(3);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(42, ['fulfilment_suspension_pending' => false])
            ->andReturn(null);

        $this->service->handleTrigger($subscription, FulfilmentSuspensionService::REASON_PAYMENT_FAILED);

        $this->assertTrue(true);
    }

    public function test_handle_trigger_defers_when_not_due(): void
    {
        $subscription = $this->makeSubscription();
        $rule = FulfilmentSuspensionRule::afterDays(30);

        $this->policyResolver->shouldReceive('resolveForPlan')->with(7)->andReturn($rule);
        $this->policyResolver->shouldReceive('isSuspensionDue')->andReturn(false);

        $this->fulfilmentRepository->shouldReceive('suspendPendingForSubscription')->never();

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(42, Mockery::on(function (array $data) {
                return $data['fulfilment_suspension_pending'] === true
                    && $data['fulfilment_suspension_reason'] === FulfilmentSuspensionService::REASON_SUBSCRIPTION_SUSPENDED
                    && isset($data['fulfilment_suspension_triggered_at']);
            }))
            ->andReturn(null);

        $this->service->handleTrigger($subscription, FulfilmentSuspensionService::REASON_SUBSCRIPTION_SUSPENDED);

        $this->assertTrue(true);
    }

    public function test_reevaluate_pending_returns_false_when_not_flagged(): void
    {
        $subscription = $this->makeSubscription(['fulfilment_suspension_pending' => false]);

        $this->policyResolver->shouldReceive('resolveForPlan')->never();
        $this->fulfilmentRepository->shouldReceive('suspendPendingForSubscription')->never();

        $result = $this->service->reevaluatePending($subscription);

        $this->assertFalse($result);
    }

    public function test_reevaluate_pending_returns_false_when_still_not_due(): void
    {
        $subscription = $this->makeSubscription([
            'fulfilment_suspension_pending' => true,
            'fulfilment_suspension_reason' => FulfilmentSuspensionService::REASON_PAYMENT_FAILED,
        ]);
        $rule = FulfilmentSuspensionRule::afterDays(30);

        $this->policyResolver->shouldReceive('resolveForPlan')->with(7)->andReturn($rule);
        $this->policyResolver->shouldReceive('isSuspensionDue')->andReturn(false);

        $this->fulfilmentRepository->shouldReceive('suspendPendingForSubscription')->never();

        $result = $this->service->reevaluatePending($subscription);

        $this->assertFalse($result);
    }

    public function test_reevaluate_pending_suspends_and_returns_true_when_now_due(): void
    {
        $subscription = $this->makeSubscription([
            'fulfilment_suspension_pending' => true,
            'fulfilment_suspension_reason' => FulfilmentSuspensionService::REASON_PAYMENT_FAILED,
        ]);
        $rule = FulfilmentSuspensionRule::afterDays(30);

        $this->policyResolver->shouldReceive('resolveForPlan')->with(7)->andReturn($rule);
        $this->policyResolver->shouldReceive('isSuspensionDue')->andReturn(true);

        $this->fulfilmentRepository->shouldReceive('suspendPendingForSubscription')
            ->once()
            ->with(42, FulfilmentSuspensionService::REASON_PAYMENT_FAILED)
            ->andReturn(7);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(42, ['fulfilment_suspension_pending' => false])
            ->andReturn(null);

        $result = $this->service->reevaluatePending($subscription);

        $this->assertTrue($result);
    }

    public function test_release_clears_suspension_and_returns_released_count(): void
    {
        $subscription = $this->makeSubscription();

        $this->fulfilmentRepository->shouldReceive('releaseSuspendedForSubscription')
            ->once()
            ->with(42)
            ->andReturn(2);

        $this->subscriptionRepository->shouldReceive('update')
            ->once()
            ->with(42, [
                'fulfilment_suspension_pending' => false,
                'fulfilment_suspension_reason' => null,
            ])
            ->andReturn(null);

        $result = $this->service->release($subscription);

        $this->assertSame(2, $result);
    }

    public function test_suspend_now_is_atomic_no_writes_when_transaction_fails(): void
    {
        // Regression test: suspendNow() previously issued two sequential
        // writes with no Database::transaction() wrapper, so a failure
        // between them would leave fulfilments suspended without the
        // subscription flag being cleared. Asserts both writes only ever
        // happen inside the transaction boundary.
        $subscription = $this->makeSubscription();
        $rule = FulfilmentSuspensionRule::immediate();

        $this->policyResolver->shouldReceive('resolveForPlan')->with(7)->andReturn($rule);
        $this->policyResolver->shouldReceive('isSuspensionDue')->andReturn(true);

        $this->fulfilmentRepository->shouldNotReceive('suspendPendingForSubscription');
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->database->shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('could not open transaction'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not open transaction');

        $this->service->handleTrigger($subscription, FulfilmentSuspensionService::REASON_PAYMENT_FAILED);
    }

    public function test_release_is_atomic_no_writes_when_transaction_fails(): void
    {
        $subscription = $this->makeSubscription();

        $this->fulfilmentRepository->shouldNotReceive('releaseSuspendedForSubscription');
        $this->subscriptionRepository->shouldNotReceive('update');

        $this->database->shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('could not open transaction'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not open transaction');

        $this->service->release($subscription);
    }
}
