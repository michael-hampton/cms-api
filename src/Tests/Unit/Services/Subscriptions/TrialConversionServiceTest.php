<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Subscriptions\BillingPeriod;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Database\Database;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\Order\OrderManager;
use App\Services\Billing\Stripe\StripeOffSessionCharger;
use App\Services\Subscriptions\Calculators\SubscriptionDateCalculator;
use App\Services\Subscriptions\TrialConversionService;
use App\Services\Subscriptions\Validators\OneTimePlanValidator;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Psr\Log\LoggerInterface;

class TrialConversionServiceTest extends FunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private TrialConversionService $service;
    private StripeOffSessionCharger|MockInterface $offSessionCharger;
    private Database|MockInterface $databaseMock;
    private OrderManager|MockInterface $orderManager;
    private SubscriptionDateCalculator|MockInterface $dateCalculator;
    private OneTimePlanValidator|MockInterface $planValidator;
    private LoggerInterface|MockInterface $logger;
    private OrderRepository $orderRepository;

    public function test_converts_trialing_subscription_to_active_on_successful_payment(): void
    {
        [$subscription, , $order] = $this->makeExpiredTrial();

        $this->orderRepository->shouldReceive('findLatestForSubscription')
            ->once()
            ->with($subscription)
            ->andReturn($order);

        $newEndDate = new \DateTimeImmutable('+1 year');
        $this->planValidator->shouldReceive('validateBillingPeriod')->andReturn(BillingPeriod::YEARLY);
        $this->dateCalculator->shouldReceive('calculateEndDate')->andReturn($newEndDate);

        $this->offSessionCharger->shouldReceive('charge')
            ->once()
            ->andReturn(['success' => true, 'payment_intent_id' => 'pi_test_123']);

        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn($d) => $d['status'] === SubscriptionStatus::ACTIVE->value));

        $this->orderManager->shouldReceive('updateOrderStatus')
            ->once()
            ->with($order->id, OrderStatus::COMPLETED->value, PaymentStatus::PAID->value);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->convertSingle($subscription);

        $this->assertTrue($result);
    }

    // =========================================================================
    // Happy path
    // =========================================================================

    /**
     * Build a mock TRIALING subscription whose trial has expired, with a plan
     * and an order that has a stripe_customer_id.
     *
     * Returns [subscription, plan, order].
     */
    private function makeExpiredTrial(
        float  $price = 99.99,
        string $currency = 'gbp'
    ): array
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 1;
        $plan->name = 'Test Magazine';
        $plan->billing_period = 'yearly';

        // Order has stripe_customer_id set by PaymentIntentService at checkout
        $order = new Order();
        $order->id = 10;
        $order->stripe_customer_id = 'cus_test_abc';

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 99;
        $subscription->status = SubscriptionStatus::TRIALING->value;
        $subscription->price = $price;
        $subscription->currency = $currency;
        $subscription->plan_id = 1;
        $subscription->member_id = 1;
        $subscription->payment_intent_id = null;
        $subscription->plan = $plan;

        // Trial has expired
        $subscription->shouldReceive('isTrialing')->andReturn(false)->byDefault();

        // Wire Order lookup: Order::where('one_time_subscription_id', ...) — we
        // intercept resolveLatestOrder() by patching the static query.
        // Because the service calls Order::where() directly (a model static),
        // we use a partial mock via the test helper below.
        $this->bindOrderQuery($subscription->id, $order);

        return [$subscription, $plan, $order];
    }

    /**
     * Stub the Order::where('one_time_subscription_id', $subId) static query
     * to return $order (or null).
     *
     * If your test environment does not support static model mocking, replace
     * this with a seeded in-memory databaseMock fixture instead.
     */
    private function bindOrderQuery(int $subscriptionId, ?Order $order): void
    {
        // This helper is a placeholder.  In a real test suite using an in-memory
        // SQLite DB or a model repository mock, you would seed the order row here.
        // The service calls Order::where('one_time_subscription_id', ...) directly,
        // so the cleanest alternative without changing production code is to run
        // these as integration tests against a test DB rather than pure unit tests.
        //
        // If your framework provides a way to swap the static query (e.g. via a
        // QueryBuilder mock), do it here.  The assertions above remain valid
        // regardless of how the fixture is wired.
    }

    public function test_charges_correct_amount_currency_and_metadata(): void
    {
        [$subscription, , $order] = $this->makeExpiredTrial(price: 29.99, currency: 'gbp');

        $this->orderRepository->shouldReceive('findLatestForSubscription')
            ->once()
            ->andReturn($order);

        $this->planValidator->shouldReceive('validateBillingPeriod')->andReturn(BillingPeriod::MONTHLY);
        $this->dateCalculator->shouldReceive('calculateEndDate')->andReturn(new \DateTimeImmutable('+1 month'));

        $this->offSessionCharger->shouldReceive('charge')
            ->once()
            ->with(
                $order->stripe_customer_id,
                2999,
                'gbp',
                Mockery::on(fn($m) => $m['conversion_type'] === 'trial_to_paid'
                    && $m['subscription_id'] === $subscription->id
                    && $m['order_id'] === $order->id
                )
            )
            ->andReturn(['success' => true, 'payment_intent_id' => 'pi_abc']);

        $subscription->shouldReceive('update')->once();
        $this->orderManager->shouldReceive('updateOrderStatus')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->service->convertSingle($subscription);
    }

    public function test_reads_stripe_customer_id_from_order_not_from_subscription(): void
    {
        [$subscription, , $order] = $this->makeExpiredTrial();

        $order->stripe_customer_id = 'cus_from_order_123';

        $this->orderRepository->shouldReceive('findLatestForSubscription')
            ->once()
            ->andReturn($order);

        $this->planValidator->shouldReceive('validateBillingPeriod')->andReturn(BillingPeriod::YEARLY);
        $this->dateCalculator->shouldReceive('calculateEndDate')->andReturn(new \DateTimeImmutable('+1 year'));

        $this->offSessionCharger->shouldReceive('charge')
            ->once()
            ->with('cus_from_order_123', Mockery::any(), Mockery::any(), Mockery::any())
            ->andReturn(['success' => true, 'payment_intent_id' => 'pi_x']);

        $subscription->shouldReceive('update')->once();
        $this->orderManager->shouldReceive('updateOrderStatus')->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->convertSingle($subscription);

        $this->assertTrue($result);
    }

    // =========================================================================
    // Failure paths
    // =========================================================================

    public function test_uses_database_transaction_for_activation(): void
    {
        [$subscription, , $order] = $this->makeExpiredTrial();

        $this->orderRepository->shouldReceive('findLatestForSubscription')
            ->once()
            ->andReturn($order);

        $this->planValidator->shouldReceive('validateBillingPeriod')->andReturn(BillingPeriod::YEARLY);
        $this->dateCalculator->shouldReceive('calculateEndDate')->andReturn(new \DateTimeImmutable('+1 year'));

        $this->offSessionCharger->shouldReceive('charge')
            ->once()
            ->andReturn(['success' => true, 'payment_intent_id' => 'pi_x']);

        $subscription->shouldReceive('update')->once();
        $this->orderManager->shouldReceive('updateOrderStatus')->once();

        $transactionExecuted = false;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($cb) use (&$transactionExecuted) {
                $transactionExecuted = true;
                return $cb();
            });

        $this->service->convertSingle($subscription);

        $this->assertTrue($transactionExecuted);
    }

    public function test_returns_false_and_expires_subscription_when_payment_declined(): void
    {
        [$subscription, , $order] = $this->makeExpiredTrial();

        $this->orderRepository->shouldReceive('findLatestForSubscription')
            ->once()
            ->andReturn($order);

        $this->offSessionCharger->shouldReceive('charge')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Card declined']);

        $subscription->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn($d) => $d['status'] === SubscriptionStatus::EXPIRED->value));

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->convertSingle($subscription);

        $this->assertFalse($result);
    }

    public function test_returns_false_when_subscription_is_not_trialing(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = SubscriptionStatus::ACTIVE->value;

        $result = $this->service->convertSingle($subscription);

        $this->assertFalse($result);
        $this->offSessionCharger->shouldNotHaveReceived('charge');
    }

    public function test_returns_false_when_trial_has_not_yet_ended(): void
    {
        [$subscription] = $this->makeExpiredTrial();
        // Override isTrialing() to return true (trial still running)
        $subscription->shouldReceive('isTrialing')->andReturn(true);

        $result = $this->service->convertSingle($subscription);

        $this->assertFalse($result);
        $this->offSessionCharger->shouldNotHaveReceived('charge');
    }

    public function test_returns_false_when_plan_not_found(): void
    {
        [$subscription] = $this->makeExpiredTrial();
        $subscription->plan = null;

        // expireSubscription() calls update inside a transaction
        $subscription->shouldReceive('update')->once();
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->convertSingle($subscription);

        $this->assertFalse($result);
        $this->offSessionCharger->shouldNotHaveReceived('charge');
    }

    public function test_returns_false_when_no_order_found(): void
    {
        // Build subscription with no linked order
        $subscription = $this->makeSubscriptionWithoutOrder();

        $this->orderRepository->shouldReceive('findLatestForSubscription')
            ->once()
            ->andReturn(null);

        $subscription->shouldReceive('update')->once(); // expire
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->convertSingle($subscription);

        $this->assertFalse($result);
        $this->offSessionCharger->shouldNotHaveReceived('charge');
    }

    /**
     * Subscription with no linked order — tests the "no order found" guard.
     */
    private function makeSubscriptionWithoutOrder(): Subscription
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 2;
        $plan->billing_period = 'monthly';

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 88;
        $subscription->status = SubscriptionStatus::TRIALING->value;
        $subscription->price = 9.99;
        $subscription->currency = 'gbp';
        $subscription->plan_id = 2;
        $subscription->member_id = 1;
        $subscription->payment_intent_id = null;
        $subscription->plan = $plan;
        $subscription->shouldReceive('isTrialing')->andReturn(false);

        $this->bindOrderQuery($subscription->id, null);

        return $subscription;
    }

    public function test_returns_false_when_order_has_no_stripe_customer_id(): void
    {
        [$subscription, , $order] = $this->makeExpiredTrial();
        $order->stripe_customer_id = null;

        $this->orderRepository->shouldReceive('findLatestForSubscription')
            ->once()
            ->andReturn($order);

        $subscription->shouldReceive('update')->once(); // expire
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $result = $this->service->convertSingle($subscription);

        $this->assertFalse($result);
        $this->offSessionCharger->shouldNotHaveReceived('charge');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function test_does_not_charge_when_already_active(): void
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 1;
        $subscription->status = SubscriptionStatus::ACTIVE->value;

        $result = $this->service->convertSingle($subscription);

        $this->assertFalse($result);
        $this->offSessionCharger->shouldNotHaveReceived('charge');
    }

    public function test_rethrows_when_payment_succeeded_but_activation_fails(): void
    {
        [$subscription, , $order] = $this->makeExpiredTrial();

        $this->orderRepository->shouldReceive('findLatestForSubscription')
            ->once()
            ->andReturn($order);

        $this->planValidator->shouldReceive('validateBillingPeriod')->andReturn(BillingPeriod::YEARLY);
        $this->dateCalculator->shouldReceive('calculateEndDate')->andReturn(new \DateTimeImmutable('+1 year'));

        $this->offSessionCharger->shouldReceive('charge')
            ->once()
            ->andReturn(['success' => true, 'payment_intent_id' => 'pi_x']);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andThrow(new \RuntimeException('DB connection lost'));

        $this->logger->shouldReceive('critical')->once();

        $this->expectException(\RuntimeException::class);

        $this->service->convertSingle($subscription);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->offSessionCharger = Mockery::mock(StripeOffSessionCharger::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->orderManager = Mockery::mock(OrderManager::class);
        $this->dateCalculator = Mockery::mock(SubscriptionDateCalculator::class);
        $this->planValidator = Mockery::mock(OneTimePlanValidator::class);
        $this->logger = Mockery::mock(LoggerInterface::class)->shouldIgnoreMissing();
        $this->orderRepository = Mockery::mock(OrderRepository::class);

        $this->service = new TrialConversionService(
            $this->offSessionCharger,
            $this->databaseMock,
            $this->orderManager,
            $this->dateCalculator,
            $this->planValidator,
            $this->logger,
            $this->orderRepository
        );
    }
}
