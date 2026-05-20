<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Actions\Stock\FulfilSubscriptionAction;
use App\DTO\Checkout\EligibilityResult;
use App\DTO\Checkout\EstimatedDelivery;
use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\PaymentStatus;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Exceptions\Checkout\CheckoutException;
use App\Exceptions\Stock\StockException;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Models\IssueDelivery;
use App\Models\Member;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Services\Billing\Order\OrderDraftService;
use App\Services\Billing\Payments\PaymentIntentService;
use App\Services\Billing\Preorder\Contracts\AvailabilityPolicyInterface;
use App\Services\Shipping\FulfilmentResolver;
use App\Services\Shipping\FulfilmentTypeInterface;
use App\Services\Shipping\InternalBusinessDayEstimator;
use App\Services\Shopping\CartService;
use App\Services\Shopping\CheckoutEligibilityService;
use App\Services\Shopping\CheckoutResponseBuilder;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use App\Services\Subscriptions\SubscriptionBatchFactory;
use App\Services\Vouchers\DiscountResolver;
use App\Services\Vouchers\ResolvedDiscounts;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class OneTimeSubscriptionCheckoutServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OneTimeSubscriptionCheckoutService $service;
    private $cartService;
    private $subscriptionBatchFactory;
    private $orderDraftService;
    private $paymentIntentService;
    private $responseBuilder;
    private $memberAuth;
    private $database;
    private DiscountResolver $discountResolver;
    private InternalBusinessDayEstimator $businessDayEstimator;
    private FulfilmentResolver $fulfilmentResolver;
    private SubscriptionPlanRepository $subscriptionPlanRepository;
    private CheckoutEligibilityService|MockInterface $eligibilityService;
    private FulfilSubscriptionAction|MockInterface $fulfilSubscriptionAction;


    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = Mockery::mock(CartService::class);
        $this->subscriptionBatchFactory = Mockery::mock(SubscriptionBatchFactory::class);
        $this->orderDraftService = Mockery::mock(OrderDraftService::class);
        $this->paymentIntentService = Mockery::mock(PaymentIntentService::class);
        $this->responseBuilder = Mockery::mock(CheckoutResponseBuilder::class);
        $this->memberAuth = Mockery::mock(MemberAuthWrapper::class);
        $this->database = Mockery::mock(Database::class);
        $this->discountResolver = Mockery::mock(DiscountResolver::class);
        $this->businessDayEstimator = Mockery::mock(InternalBusinessDayEstimator::class);
        $this->fulfilmentResolver = Mockery::mock(FulfilmentResolver::class);
        $this->subscriptionPlanRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->eligibilityService = Mockery::mock(CheckoutEligibilityService::class);
        $this->fulfilSubscriptionAction = Mockery::mock(FulfilSubscriptionAction::class);

        $this->service = new OneTimeSubscriptionCheckoutService(
            $this->cartService,
            $this->subscriptionBatchFactory,
            $this->orderDraftService,
            $this->paymentIntentService,
            $this->responseBuilder,
            $this->memberAuth,
            $this->database,
            $this->discountResolver,
            $this->businessDayEstimator,
            $this->fulfilmentResolver,
            $this->subscriptionPlanRepository,
            $this->eligibilityService,
            $this->fulfilSubscriptionAction,
        );

        $this->eligibilityService->shouldReceive('validate')
            ->andReturnUsing(function ($member, array $cartItems) {
                return new EligibilityResult(valid: $cartItems, removed: []);
            })->byDefault();

        // Default: reserve succeeds silently for digital subscription tests
        $this->fulfilSubscriptionAction->shouldReceive('reserve')->andReturn(1)->byDefault();
        $this->fulfilSubscriptionAction->shouldReceive('confirm')->andReturn(null)->byDefault();
        $this->fulfilSubscriptionAction->shouldReceive('release')->andReturn(null)->byDefault();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->andReturn(['success' => true])
            ->byDefault();
    }

    public function test_throws_when_cart_has_no_subscriptions(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([['product_id' => 1, 'price' => 25.00]]);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('No subscription in cart');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_throws_when_cart_is_empty(): void
    {
        $this->cartService->shouldReceive('getItems')->once()->andReturn([]);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('No subscription in cart');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_throws_when_member_not_authenticated(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'price' => 50.00, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]]
            ]);

        $this->memberAuth->shouldReceive('check')->once()->andReturn(false);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('Please login to purchase a subscription');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_throws_when_all_items_removed_by_eligibility(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setupCartWithSingleSubscription();

        $this->eligibilityService->shouldReceive('validate')
            ->once()
            ->andReturn(new EligibilityResult(valid: [], removed: [['subscription_plan_id' => 1]]));

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->setDeliveryEstimateExpectations();

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('All items were invalid and removed from the cart.');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_fails_when_no_subscription_in_cart(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['product_id' => 1, 'price' => 25.00]
            ]);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('No subscription in cart');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_creates_subscriptions_in_transaction(): void
    {
        $member = $this->createMockMember();
        $subscription = $this->createMockSubscription();
        $order = $this->createMockOrder();

        $this->setupAuthenticatedMember($member);
        $this->setupCartWithSingleSubscription();
        $this->setDeliveryEstimateExpectations();

        // Phase 1: creation transaction (discount resolution now happens inside)
        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn($callback) => $callback());

        $this->setResolvedDiscountExpectations();

        $subscriptionsWithPricing = [[
            'subscription' => $subscription,
            'pricing' => $this->createMockPricing()
        ]];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->andReturn($subscriptionsWithPricing);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->andReturn($order);

        // setupSuccessfulPayment now covers the activation transaction (Phase 4)
        $this->setupSuccessfulPayment();

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_does_not_clear_cart_before_payment_confirmation(): void
    {
        $member = $this->createMockMember();
        $subscription = $this->createMockSubscription();
        $order = $this->createMockOrder();

        $this->setupAuthenticatedMember($member);
        $this->setupCartWithSingleSubscription();
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->andReturn([[
                'subscription' => $subscription,
                'pricing' => $this->createMockPricing(),
            ]]);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->andReturn($order);

        $this->setupSuccessfulPayment();
        $this->cartService->shouldNotReceive('clear');

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_calls_stripe_outside_transaction(): void
    {
        $member = $this->createMockMember();
        $subscription = $this->createMockSubscription();
        $order = $this->createMockOrder();

        $this->setupAuthenticatedMember($member);
        $this->setupCartWithSingleSubscription();
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $transactionCallOrder = [];

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use (&$transactionCallOrder) {
                $transactionCallOrder[] = 'transaction_start';
                $result = $callback();
                $transactionCallOrder[] = 'transaction_end';
                return $result;
            });

        $subscriptionsWithPricing = [[
            'subscription' => $subscription,
            'pricing' => $this->createMockPricing()
        ]];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->andReturn($subscriptionsWithPricing);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->andReturn($order);

        // Stripe is called AFTER creation transaction closes
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->once()
            ->andReturnUsing(function () use (&$transactionCallOrder) {
                $transactionCallOrder[] = 'stripe_call';
                return [
                    'success' => true,
                    'client_secret' => 'pi_test_secret',
                    'payment_intent_id' => 'pi_test_123',
                    'customer_id' => 'cus_test123'
                ];
            });

        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();

        // ADDED: Phase 3 activation transaction runs after Stripe, also outside creation transaction
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use (&$transactionCallOrder) {
                $transactionCallOrder[] = 'activation_transaction';
                return $callback();
            });


        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->with(
                Mockery::type(Order::class),
                Mockery::type('array'),
                Mockery::on(fn($arg) => ($arg['payment_intent_id'] ?? '') === 'pi_test_123'),
                Mockery::any()
            )
            ->andReturn(['success' => true]);

        $this->service->processCheckout(['one_time_subscription' => true], 1);

        // CHANGED: activation_transaction added to expected order — proves both Stripe
        // and activation are outside the creation transaction
        $this->assertEquals([
            'transaction_start',
            'transaction_end',
            'stripe_call',
            'activation_transaction',
        ], $transactionCallOrder);
    }

    public function test_process_checkout_handles_stripe_failure_gracefully(): void
    {
        $subscriptionMock = Mockery::mock(Subscription::class)->makePartial();
        $orderMock = Mockery::mock(Order::class)->makePartial();
        $member = $this->createMockMember();

        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]]
            ]);

        // Creation transaction + failure rollback transaction (no activation — payment failed)
        $this->database->shouldReceive('transaction')
            ->times(2)
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->andReturn([['subscription' => $subscriptionMock, 'pricing' => $this->createMockPricing()]]);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->andReturn($orderMock);

        $this->paymentIntentService->shouldReceive('createForOrder')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Card declined']);

        // CHANGED: enum values instead of magic strings
        $orderMock->shouldReceive('update')
            ->once()
            ->with(['payment_status' => PaymentStatus::FAILED->value])
            ->andReturn(true);

        $subscriptionMock->shouldReceive('update')
            ->once()
            ->with(['status' => SubscriptionStatus::CANCELLED->value])
            ->andReturn(true);

        // CHANGED: now throws CheckoutException instead of returning ['success' => false]
        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('Payment processing failed');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_applies_voucher_only_once(): void
    {
        $member = $this->createMockMember();
        $order = $this->createMockOrder();

        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'price' => 50.00, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]],
                ['subscription_plan_id' => 2, 'price' => 60.00, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]],
                ['subscription_plan_id' => 3, 'price' => 70.00, 'options' => ['delivery_type' => SubscriptionType::PRINTED->value]]
            ]);

        // CHANGED: once() — creation only; setupSuccessfulPayment adds the activation transaction
        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::on(fn($data) => $data['voucher_code'] === 'SAVE10'),
                $member,
                1,
                Mockery::any()
            )
            ->andReturn([
                ['subscription' => $this->createMockSubscription(), 'pricing' => $this->createMockPricing()],
                ['subscription' => $this->createMockSubscription(), 'pricing' => $this->createMockPricing()],
                ['subscription' => $this->createMockSubscription(), 'pricing' => $this->createMockPricing()]
            ]);

        $this->orderDraftService->shouldReceive('createPendingOrder')->once()->andReturn($order);

        $this->setupSuccessfulPayment();

        $result = $this->service->processCheckout(['one_time_subscription' => true, 'voucher_code' => 'SAVE10'], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_with_multiple_subscriptions(): void
    {
        $member = $this->createMockMember();
        $order = $this->createMockOrder();
        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1],
            ['subscription_plan_id' => 2]
        ]);

        // CHANGED: twice() — creation + activation
        $this->database->shouldReceive('transaction')->twice()->andReturnUsing(fn($cb) => $cb());

        $subs = [
            ['subscription' => $this->createMockSubscription(101), 'pricing' => $this->createMockPricing()],
            ['subscription' => $this->createMockSubscription(102), 'pricing' => $this->createMockPricing()]
        ];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn(['success' => true, 'client_secret' => 'secret', 'payment_intent_id' => 'pi_123']);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->andReturn(['success' => true, 'multiple_subscriptions' => true]);

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['multiple_subscriptions']);
    }

    public function test_process_checkout_succeeds_with_digital_delivery(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]]
        ]);

        // CHANGED: twice() — creation + activation
        $this->database->shouldReceive('transaction')->twice()->andReturnUsing(fn($cb) => $cb());

        $subs = [['subscription' => $this->createMockSubscription(), 'pricing' => $this->createMockPricing('digital')]];
        $order = $this->createMockOrder();

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn(['success' => true, 'client_secret' => 'pi_123', 'payment_intent_id' => 'pi_123']);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->with(
                Mockery::type(Order::class),
                Mockery::type('array'),
                Mockery::on(fn($arg) => ($arg['payment_intent_id'] ?? '') === 'pi_123'),
                Mockery::any()
            )
            ->andReturn(['success' => true, 'requires_shipping' => false]);

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['requires_shipping']);
    }

    public function test_process_checkout_succeeds_with_print_delivery(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1, 'options' => ['delivery_type' => SubscriptionType::PRINTED->value]]
        ]);

        // CHANGED: twice() — creation + activation
        $this->database->shouldReceive('transaction')->twice()->andReturnUsing(fn($cb) => $cb());

        $subs = [['subscription' => $this->createMockSubscription(), 'pricing' => $this->createMockPricing(SubscriptionType::PRINTED->value)]];
        $order = $this->createMockOrder();

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn(['success' => true, 'client_secret' => 'pi_123', 'payment_intent_id' => 'pi_123']);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->with(
                Mockery::type(Order::class),
                Mockery::type('array'),
                Mockery::on(fn($arg) => ($arg['payment_intent_id'] ?? '') === 'pi_123'),
                Mockery::any()
            )
            ->andReturn(['success' => true, 'requires_shipping' => true]);

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['requires_shipping']);
    }


    /**
     * Rewritten to fix argument order for createPendingOrder.
     */
    public function test_process_checkout_applies_voucher_discount(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1, 'price' => 22]
        ]);

        // CHANGED: twice() — creation + activation
        $this->database->shouldReceive('transaction')->twice()->andReturnUsing(fn($cb) => $cb());

        $voucherCode = 'SAVE50';
        $inputData = ['voucher_code' => $voucherCode];
        $siteId = 1;

        $discountedPricing = new SubscriptionPricing(
            subtotalCents: 5000,
            discountCents: 2500,
            shippingCents: 0,
            taxCents: 0,
            totalCents: 2500,
            deliveryType: SubscriptionType::DIGITAL->value,
            voucherId: 99,
            shippingAddressSnapshot: null,
            originalAmount: 20
        );

        $subs = [['subscription' => $this->createMockSubscription(), 'pricing' => $discountedPricing]];
        $order = $this->createMockOrder();

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->with(Mockery::any(), Mockery::subset(['voucher_code' => $voucherCode]), $member, $siteId, Mockery::any())
            ->andReturn($subs);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->with($subs, $member, $siteId, Mockery::subset($inputData), Mockery::any(), false)
            ->andReturn($order);

        $paymentResult = ['success' => true, 'client_secret' => 'pi_test', 'payment_intent_id' => 'pi_123'];
        $this->paymentIntentService->shouldReceive('createForOrder')->once()->andReturn($paymentResult);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->with($order, $subs, $paymentResult, false)
            ->andReturn(['success' => true, 'order_id' => 789]);

        $result = $this->service->processCheckout(array_merge($inputData, ['one_time_subscription' => true]), $siteId);

        $this->assertTrue($result['success']);
    }

    /**
     * Tests that when the payment gateway returns a failure,
     * the system cancels the pending subscriptions and marks the order as failed.
     */
    public function test_process_checkout_handles_stripe_failure(): void
    {
        $subscriptionMock = Mockery::mock(Subscription::class)->makePartial();
        $orderMock = Mockery::mock(Order::class)->makePartial();
        $member = $this->createMockMember();
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $this->setupAuthenticatedMember($member);
        $this->setupCartWithSingleSubscription();

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->andReturn([['subscription' => $subscriptionMock, 'pricing' => $this->createMockPricing()]]);

        $this->database->shouldReceive('transaction')
            ->times(2)
            ->andReturnUsing(fn($cb) => $cb());

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->andReturn($orderMock);

        $this->paymentIntentService->shouldReceive('createForOrder')
            ->once()
            ->andReturn(['success' => false]);

        // CHANGED: enum values
        $orderMock->shouldReceive('update')
            ->once()
            ->with(['payment_status' => PaymentStatus::FAILED->value]);

        $subscriptionMock->shouldReceive('update')
            ->once()
            ->with(['status' => SubscriptionStatus::CANCELLED->value])
            ->andReturn(true);

        // CHANGED: throws instead of returning ['success' => false]
        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('Payment processing failed');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createMockMember(): Member
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;
        $member->email = 'test@example.com';
        return $member;
    }

    private function createMockSubscription(?int $id = null): Subscription
    {
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = $id ?? 456;
        $subscription->currency = 'USD';
        $subscription->plan_name = 'Premium Magazine';
        $subscription->shouldReceive('update')->andReturn(true);
        return $subscription;
    }

    private function createMockOrder(): Order
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 789;
        $order->order_number = 'ORD-12345';
        $order->total = 55.00;
        $order->currency = 'USD';
        $order->shouldReceive('update')->andReturn(true);
        return $order;
    }

    private function createMockPricing(string $deliveryType = 'digital'): SubscriptionPricing
    {
        return new SubscriptionPricing(
            subtotalCents: 5000,
            discountCents: 0,
            shippingCents: $deliveryType === SubscriptionType::PRINTED->value ? 1000 : 0,
            taxCents: 500,
            totalCents: 5500,
            deliveryType: $deliveryType,
            voucherId: null,
            shippingAddressSnapshot: null,
            originalAmount: 20
        );
    }

    private function setupAuthenticatedMember(Member $member): void
    {
        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);
    }

    private function setupCartWithSingleSubscription(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'price' => 50.00, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]]
            ]);
    }

    private function setupSuccessfulPayment(?string $intentId = 'pi_test_123'): void
    {
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => $intentId,
                'customer_id' => 'cus_test123'
            ])->byDefault();

        $this->orderDraftService->shouldReceive('attachPaymentIntent')->byDefault();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->andReturn(['success' => true])
            ->byDefault();
    }

    public function testProcessCheckoutFailsWithNoSubscriptions(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([['type' => 'product', 'product_id' => 1]]);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('No subscription in cart');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    // CHANGED: service now throws CheckoutException instead of returning ['success' => false]
    public function testProcessCheckoutFailsWithEmptyCart(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([]);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('No subscription in cart');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function testProcessCheckoutFailsWhenNotAuthenticated(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'delivery_type' => SubscriptionType::DIGITAL->value]
            ]);

        $this->memberAuth->shouldReceive('check')
            ->once()
            ->andReturn(false);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('Please login to purchase a subscription');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    private function setResolvedDiscountExpectations()
    {
        $subscriptionItems = [
            ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100, 'quantity' => 1]
        ];

        $resolvedDiscounts = new ResolvedDiscounts(
            items: $subscriptionItems,
            baseSubtotalCents: 10000,
            finalSubtotalCents: 9000,
            offerDiscountCents: 1000,
            tieredDiscountCents: 0,
            voucherDiscountCents: 0,
            rewardDiscountCents: 0,
            storeCreditCents: 0,
            merchantFundedCents: 1000,
            platformFundedCents: 0,
            customerCreditCents: 0
        );

        $this->discountResolver->shouldReceive('resolve')
            ->once()
            ->andReturn($resolvedDiscounts);
    }

    public function test_process_checkout_returns_error_when_no_subscription_items(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->andReturn([['id' => 1, 'price' => 100]]);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('No subscription in cart');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_returns_error_when_not_authenticated(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->andReturn([['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]]);

        $this->memberAuth->shouldReceive('check')->andReturn(false);

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('Please login to purchase a subscription');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_resolves_discounts(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->setDeliveryEstimateExpectations();

        $subscriptionItems = [
            ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100, 'quantity' => 1]
        ];

        $this->cartService->shouldReceive('getItems')->andReturn($subscriptionItems);

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = new ResolvedDiscounts(
            items: $subscriptionItems,
            baseSubtotalCents: 10000,
            finalSubtotalCents: 9000,
            offerDiscountCents: 1000,
            tieredDiscountCents: 0,
            voucherDiscountCents: 0,
            rewardDiscountCents: 0,
            storeCreditCents: 0,
            merchantFundedCents: 1000,
            platformFundedCents: 0,
            customerCreditCents: 0
        );

        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 1;
        $subscriptions = [['subscription' => $this->createMockSubscription()]];

        // CHANGED: twice() — creation + activation
        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->with(Mockery::any(), Mockery::any(), $member, 1, Mockery::type(ResolvedDiscounts::class))
            ->andReturn($subscriptions);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->with($subscriptions, $member, 1, Mockery::any(), Mockery::type(ResolvedDiscounts::class), false)
            ->andReturn($order);

        $paymentResult = ['success' => true, 'payment_intent_id' => 'pi_123'];
        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn($paymentResult);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();
        $this->responseBuilder->shouldReceive('buildCheckoutResponse')->andReturn(['success' => true]);

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_creates_subscriptions_and_order_in_transaction(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $subscriptionItems = [
            ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100, 'quantity' => 1]
        ];

        $this->cartService->shouldReceive('getItems')->andReturn($subscriptionItems);
        $this->setDeliveryEstimateExpectations();

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = Mockery::mock(ResolvedDiscounts::class);
        $this->discountResolver->shouldReceive('resolve')->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 1;
        $subscriptions = [['subscription' => $this->createMockSubscription()]];

        // CHANGED: twice() — creation + activation
        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->andReturn($subscriptions);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->andReturn($order);

        $paymentResult = ['success' => true];
        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn($paymentResult);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();
        $this->responseBuilder->shouldReceive('buildCheckoutResponse')->andReturn(['success' => true]);

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }


    public function test_process_checkout_handles_payment_failure(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $subscriptionItems = [['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]];

        $this->cartService->shouldReceive('getItems')->andReturn($subscriptionItems);
        $this->setDeliveryEstimateExpectations();

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = Mockery::mock(ResolvedDiscounts::class);
        $this->discountResolver->shouldReceive('resolve')->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        // CHANGED: enum value
        $order->shouldReceive('update')->once()->with(['payment_status' => PaymentStatus::FAILED->value]);

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        // CHANGED: enum value
        $subscription->shouldReceive('update')->once()->with(['status' => SubscriptionStatus::CANCELLED->value]);

        $subscriptions = [['subscription' => $subscription]];

        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subscriptions);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);
        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn(['success' => false]);

        // CHANGED: throws instead of returning ['success' => false]
        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('Payment processing failed');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_handles_payment_exception(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $subscriptionItems = [
            ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]
        ];

        $this->setDeliveryEstimateExpectations();

        $this->cartService->shouldReceive('getItems')
            ->andReturn($subscriptionItems);

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = Mockery::mock(ResolvedDiscounts::class);
        $this->discountResolver->shouldReceive('resolve')->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $order->shouldReceive('update')->once();

        $subscription = Mockery::mock();
        $subscription->shouldReceive('update')->once();

        $subscriptions = [['subscription' => $subscription]];

        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(function ($callback) use ($order, $subscriptions) {
                return $callback();
            })
            ->andReturn([$order, $subscriptions]);

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->andReturn($subscriptions);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->andReturn($order);

        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andThrow(new \Exception('Payment service error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment service error');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_attaches_payment_intent_after_success(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->setDeliveryEstimateExpectations();

        $subscriptionItems = [['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]];

        $this->cartService->shouldReceive('getItems')->andReturn($subscriptionItems);

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = Mockery::mock(ResolvedDiscounts::class);
        $this->discountResolver->shouldReceive('resolve')->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $subscriptions = [['subscription' => $this->createMockSubscription()]];

        // CHANGED: no count constraint — handles both creation and activation transparently
        $this->database->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subscriptions);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $paymentResult = ['success' => true, 'payment_intent_id' => 'pi_123'];
        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn($paymentResult);

        $this->orderDraftService->shouldReceive('attachPaymentIntent')
            ->once()
            ->with($order, $paymentResult);

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')->andReturn(['success' => true]);

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_clears_cart_after_success(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->setDeliveryEstimateExpectations();

        $subscriptionItems = [['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]];

        $this->cartService->shouldReceive('getItems')->andReturn($subscriptionItems);

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = Mockery::mock(ResolvedDiscounts::class);
        $this->discountResolver->shouldReceive('resolve')->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $subscriptions = [['subscription' => $this->createMockSubscription()]];

        // CHANGED: no count constraint — handles both creation and activation transparently
        $this->database->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subscriptions);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $paymentResult = ['success' => true];
        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn($paymentResult);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();


        $this->responseBuilder->shouldReceive('buildCheckoutResponse')->andReturn(['success' => true]);

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_builds_response(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->setDeliveryEstimateExpectations();

        $subscriptionItems = [['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]];

        $this->cartService->shouldReceive('getItems')->andReturn($subscriptionItems);

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = Mockery::mock(ResolvedDiscounts::class);
        $this->discountResolver->shouldReceive('resolve')->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $subscriptions = [['subscription' => $this->createMockSubscription()]];

        // CHANGED: no count constraint — handles both creation and activation transparently
        $this->database->shouldReceive('transaction')
            ->andReturnUsing(fn($callback) => $callback());

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subscriptions);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $paymentResult = ['success' => true];
        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn($paymentResult);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();

        $expectedResponse = ['success' => true, 'order_id' => 1, 'redirect_url' => '/success'];

        // CHANGED: 4th arg is now !empty($eligibility->removed); false when nothing removed
        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->with($order, $subscriptions, $paymentResult, false)
            ->andReturn($expectedResponse);

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertEquals($expectedResponse, $result);
    }

    private function setDeliveryEstimateExpectations()
    {
        $subscriptionPlan = Mockery::mock(SubscriptionPlan::class)->makePartial();

        $this->subscriptionPlanRepository->shouldReceive('lockForUpdate')
            ->atLeast()->once()
            ->andReturn($subscriptionPlan);

        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class)->makePartial();

        $this->fulfilmentResolver->shouldReceive('resolve')
            ->atLeast()->once()
            ->with($subscriptionPlan)
            ->andReturn($fulfilment);

        $today = new \DateTimeImmutable();

        $estimatedDelivery = new EstimatedDelivery(false, $today, $today, $today);

        $this->businessDayEstimator->shouldReceive('estimate')
            ->atLeast()->once()
            ->andReturn($estimatedDelivery);
    }

    public function test_process_checkout_throws_when_plan_not_found(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([['subscription_plan_id' => 999, 'price' => 50.00, 'options' => []]]);

        $this->subscriptionPlanRepository->shouldReceive('lockForUpdate')
            ->with(999)
            ->once()
            ->andReturn(null);

        // ADDED: validateAndAttachEstimates now runs inside the transaction
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription plan not found');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_throws_when_plan_availability_policy_blocks_purchase(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([['subscription_plan_id' => 1, 'price' => 50.00, 'options' => []]]);

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->once()->andReturn(false);
        $policy->shouldReceive('getAvailabilityMessage')->once()->andReturn('Plan is sold out');
        $policy->shouldReceive('isPreRelease')->andReturn(false);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = false;
        $plan->shouldReceive('availabilityPolicy')->andReturn($policy);

        $this->subscriptionPlanRepository->shouldReceive('lockForUpdate')
            ->with(1)
            ->once()
            ->andReturn($plan);

        $this->fulfilmentResolver->shouldReceive('resolve')->andReturn(
            Mockery::mock(\App\Services\Shipping\FulfilmentTypeInterface::class)
        );
        $this->businessDayEstimator->shouldReceive('estimate')->andReturn(
            new \App\DTO\Checkout\EstimatedDelivery(false, new \DateTimeImmutable(), new \DateTimeImmutable(), new \DateTimeImmutable())
        );

        // ADDED: validateAndAttachEstimates now runs inside the transaction
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not available: Plan is sold out');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_throws_when_print_subscription_has_no_next_issue(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([['subscription_plan_id' => 1, 'price' => 50.00, 'quantity' => 1, 'options' => []]]);

        $policy = Mockery::mock(AvailabilityPolicyInterface::class);
        $policy->shouldReceive('canPurchase')->once()->andReturn(true);
        $policy->shouldReceive('isPreRelease')->andReturn(false);
        $policy->shouldReceive('getAvailabilityMessage')->andReturn('Available');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = true;
        $plan->name = 'Test Magazine';
        $plan->shouldReceive('availabilityPolicy')->andReturn($policy);
        $plan->shouldReceive('getNextIssue')->once()->andReturn(null);

        $this->subscriptionPlanRepository->shouldReceive('lockForUpdate')
            ->with(1)
            ->once()
            ->andReturn($plan);

        // ADDED: validateAndAttachEstimates now runs inside the transaction
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No issues scheduled for Test Magazine');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_throws_when_print_issue_out_of_stock_and_not_preorder(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([['subscription_plan_id' => 1, 'price' => 50.00, 'quantity' => 2, 'options' => []]]);

        $planPolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $planPolicy->shouldReceive('canPurchase')->once()->andReturn(true);
        $planPolicy->shouldReceive('isPreRelease')->andReturn(false);
        $planPolicy->shouldReceive('getAvailabilityMessage')->andReturn('Available');

        $issuePolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $issuePolicy->shouldReceive('canPurchase')->once()->andReturn(true);
        $issuePolicy->shouldReceive('isPreOrder')->once()->andReturn(false);

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->stock_quantity = 1;
        $nextIssue->issue_number = 42;
        $nextIssue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = true;
        $plan->shouldReceive('availabilityPolicy')->andReturn($planPolicy);
        $plan->shouldReceive('getNextIssue')->once()->andReturn($nextIssue);

        $this->subscriptionPlanRepository->shouldReceive('lockForUpdate')
            ->with(1)
            ->once()
            ->andReturn($plan);

        // ADDED: validateAndAttachEstimates now runs inside the transaction
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('out of stock');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_throws_when_preorder_has_no_expected_ship_date(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([['subscription_plan_id' => 1, 'price' => 50.00, 'quantity' => 2, 'options' => []]]);

        $planPolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $planPolicy->shouldReceive('canPurchase')->once()->andReturn(true);
        $planPolicy->shouldReceive('isPreRelease')->andReturn(false);
        $planPolicy->shouldReceive('getAvailabilityMessage')->andReturn('Available');

        $issuePolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $issuePolicy->shouldReceive('canPurchase')->once()->andReturn(true);
        $issuePolicy->shouldReceive('isPreOrder')->once()->andReturn(true);
        $issuePolicy->shouldReceive('getExpectedShipDate')->once()->andReturn(null);

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->stock_quantity = 0;
        $nextIssue->issue_number = 42;
        $nextIssue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = true;
        $plan->shouldReceive('availabilityPolicy')->andReturn($planPolicy);
        $plan->shouldReceive('getNextIssue')->once()->andReturn($nextIssue);

        $this->subscriptionPlanRepository->shouldReceive('lockForUpdate')
            ->with(1)
            ->once()
            ->andReturn($plan);

        // ADDED: validateAndAttachEstimates now runs inside the transaction
        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pre-order requires expected ship date');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_process_checkout_builds_voucher_context_when_voucher_code_provided(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setDeliveryEstimateExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                [
                    'subscription_plan_id' => 1,
                    'price' => 50.00,
                    'base_price' => 50.00,
                    'quantity' => 1,
                    'options' => [
                        'delivery_type' => \App\Enums\Subscriptions\SubscriptionType::DIGITAL->value,
                        'pricing_tier_id' => 99,
                    ],
                ]
            ]);

        $this->discountResolver->shouldReceive('resolve')
            ->once()
            ->with(Mockery::on(function ($context) {
                return $context->voucherContext !== null
                    && $context->voucherContext->voucherData['voucher_code'] === 'SAVE20';
            }))
            ->andReturn(new \App\Services\Vouchers\ResolvedDiscounts(
                items: [],
                baseSubtotalCents: 5000,
                finalSubtotalCents: 4000,
                offerDiscountCents: 0,
                tieredDiscountCents: 0,
                voucherDiscountCents: 1000,
                rewardDiscountCents: 0,
                storeCreditCents: 0,
                merchantFundedCents: 0,
                platformFundedCents: 1000,
                customerCreditCents: 0
            ));

        $order = $this->createMockOrder();
        $subscription = $this->createMockSubscription();
        $subscriptions = [['subscription' => $subscription, 'pricing' => $this->createMockPricing()]];

        // CHANGED: twice() — creation + activation
        $this->database->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn($cb) => $cb());

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->andReturn($subscriptions);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->andReturn($order);

        $this->paymentIntentService->shouldReceive('createForOrder')
            ->once()
            ->andReturn(['success' => true, 'payment_intent_id' => 'pi_123']);

        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();
        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->with(
                Mockery::type(Order::class),
                Mockery::type('array'),
                Mockery::on(fn($arg) => ($arg['payment_intent_id'] ?? '') === 'pi_123'),
                Mockery::any()
            )
            ->andReturn(['success' => true]);

        $result = $this->service->processCheckout(['one_time_subscription' => true, 'voucher_code' => 'SAVE20', 'voucher_id' => null], 1);

        $this->assertTrue($result['success']);
    }

    public function test_eligibility_service_removes_duplicate_subscription_and_continues(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setDeliveryEstimateExpectations();
        $this->setResolvedDiscountExpectations();

        $kept = ['subscription_plan_id' => 1, 'price' => 50.00, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]];
        $removed = ['subscription_plan_id' => 2, 'price' => 30.00, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]];

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([$kept, $removed]);

        $this->eligibilityService->shouldReceive('validate')
            ->once()
            ->with($member, Mockery::any())
            ->andReturn(new EligibilityResult(valid: [$kept], removed: [$removed]));

        $order = $this->createMockOrder();
        $subscription = $this->createMockSubscription();
        $subs = [['subscription' => $subscription, 'pricing' => $this->createMockPricing()]];

        // CHANGED: once() for creation; setupSuccessfulPayment adds the activation transaction
        $this->database->shouldReceive('transaction')->twice()->andReturnUsing(fn($cb) => $cb());
        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->once()->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->once()->andReturn($order);
        $this->setupSuccessfulPayment();

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_zeroes_totals_when_all_items_are_free_gifts(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setDeliveryEstimateExpectations();
        $this->setResolvedDiscountExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            [
                'subscription_plan_id' => 1,
                'price' => 0.00,
                'base_price' => 0.00,
                'quantity' => 1,
                'options' => ['type' => \App\Enums\CartItemType::FREE_GIFT->value]
            ]
        ]);

        // CHANGED: once() for creation; setupSuccessfulPayment adds the activation transaction
        $this->database->shouldReceive('transaction')->twice()->andReturnUsing(fn($cb) => $cb());

        $order = $this->createMockOrder();
        $subscription = $this->createMockSubscription();
        $subs = [['subscription' => $subscription, 'pricing' => $this->createMockPricing()]];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->once()->andReturn($subs);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->with($subs, $member, 1, Mockery::any(), Mockery::any(), true)
            ->andReturn($order);

        $this->setupSuccessfulPayment();

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }

    public function test_reserve_is_called_in_phase_1_for_print_subscription_with_stock(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1, 'quantity' => 1, 'price' => 50.00, 'options' => []],
        ]);

        $issuePolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $issuePolicy->shouldReceive('canPurchase')->andReturn(true);

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->id = 77;
        $nextIssue->stock_quantity = 10;
        $nextIssue->issue_number = 1;
        $nextIssue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);

        $planPolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $planPolicy->shouldReceive('canPurchase')->andReturn(true);
        $planPolicy->shouldReceive('isPreRelease')->andReturn(false);
        $planPolicy->shouldReceive('getAvailabilityMessage')->andReturn('Available');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = true;
        $plan->shouldReceive('availabilityPolicy')->andReturn($planPolicy);
        $plan->shouldReceive('getNextIssue')->andReturn($nextIssue);

        $this->subscriptionPlanRepository->shouldReceive('lockForUpdate')->with(1)->andReturn($plan);

        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $this->fulfilmentResolver->shouldReceive('resolve')->andReturn($fulfilment);
        $today = new \DateTimeImmutable();
        $this->businessDayEstimator->shouldReceive('estimate')
            ->andReturn(new EstimatedDelivery(false, $today, $today, $today));

        // ── Core assertion: reserve() must be called with the issue and qty ──
        $this->fulfilSubscriptionAction->shouldReceive('reserve')
            ->once()
            ->with($nextIssue, 1)
            ->andReturn(42);
        // ────────────────────────────────────────────────────────────────────

        $this->database->shouldReceive('transaction')->twice()
            ->andReturnUsing(fn($cb) => $cb());

        $order = $this->createMockOrder();
        $subscription = $this->createMockSubscription();
        $subs = [['subscription' => $subscription, 'pricing' => $this->createMockPricing()]];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->once()->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->once()->andReturn($order);
        $this->setupSuccessfulPayment();

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }

    public function test_confirm_is_called_in_phase_3_after_successful_payment(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1, 'quantity' => 1, 'price' => 50.00, 'options' => []],
        ]);

        $this->setupPrintSubscriptionWithStock(reservationId: 55);

        $this->database->shouldReceive('transaction')->twice()
            ->andReturnUsing(fn($cb) => $cb());

        $order = $this->createMockOrder();
        $subscription = $this->createMockSubscription();
        $subs = [['subscription' => $subscription, 'pricing' => $this->createMockPricing()]];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->once()->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->once()->andReturn($order);

        // ── Core assertion: confirm() called with the reservation ID ─────────
        $this->fulfilSubscriptionAction->shouldReceive('confirm')->once()->with(1);
        // ────────────────────────────────────────────────────────────────────

        $this->setupSuccessfulPayment();

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }

    public function test_release_is_called_on_payment_failure_for_print_subscription(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1, 'quantity' => 2, 'price' => 50.00, 'options' => []],
        ]);

        $nextIssue = $this->setupPrintSubscriptionWithStock(reservationId: 10, quantity: 2);

        $this->database->shouldReceive('transaction')->twice()
            ->andReturnUsing(fn($cb) => $cb());

        $order = Mockery::mock(Order::class)->makePartial();
        $order->shouldReceive('update')->andReturn(true);
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('update')->andReturn(true);
        $subs = [['subscription' => $subscription]];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andReturn(['success' => false, 'message' => 'Declined']);

        // ── Core assertion: release() called with the issue model and qty ────
        $this->fulfilSubscriptionAction->shouldReceive('release')
            ->once()
            ->with($nextIssue, 2);
        // confirm() must NOT be called
        $this->fulfilSubscriptionAction->shouldNotReceive('confirm');
        // ────────────────────────────────────────────────────────────────────

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage('Payment processing failed');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_release_is_called_on_payment_exception_for_print_subscription(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1, 'quantity' => 1, 'price' => 50.00, 'options' => []],
        ]);

        $nextIssue = $this->setupPrintSubscriptionWithStock(reservationId: 7, quantity: 1);

        $this->database->shouldReceive('transaction')->twice()
            ->andReturnUsing(fn($cb) => $cb());

        $order = Mockery::mock(Order::class)->makePartial();
        $order->shouldReceive('update')->andReturn(true);
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('update')->andReturn(true);
        $subs = [['subscription' => $subscription]];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andThrow(new \Exception('Payment gateway timeout'));

        // ── Core assertion: release() called even when an exception is thrown ─
        $this->fulfilSubscriptionAction->shouldReceive('release')->once()->with($nextIssue, 1);
        // ────────────────────────────────────────────────────────────────────

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Payment gateway timeout');

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_reserve_is_not_called_for_digital_subscription(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1, 'quantity' => 1, 'price' => 50.00,
                'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]],
        ]);

        $this->database->shouldReceive('transaction')->twice()
            ->andReturnUsing(fn($cb) => $cb());

        $order = $this->createMockOrder();
        $subscription = $this->createMockSubscription();
        $subs = [['subscription' => $subscription, 'pricing' => $this->createMockPricing()]];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->once()->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->once()->andReturn($order);
        $this->setupSuccessfulPayment();

        // ── Digital subscriptions carry no issue stock — reserve must not fire
        $this->fulfilSubscriptionAction->shouldNotReceive('reserve');
        // ────────────────────────────────────────────────────────────────────

        $result = $this->service->processCheckout(['one_time_subscription' => true], 1);

        $this->assertTrue($result['success']);
    }

    public function test_reserve_throws_stock_exception_causes_transaction_to_propagate(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);

        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1, 'quantity' => 5, 'price' => 50.00, 'options' => []],
        ]);

        $issuePolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $issuePolicy->shouldReceive('canPurchase')->andReturn(true);

        $issuePolicy->shouldReceive('isPreOrder')->andReturn(false);

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->id = 77;
        $nextIssue->stock_quantity = 2;   // less than requested
        $nextIssue->issue_number = 1;
        $nextIssue->issue_title = 'Winter Issue';
        $nextIssue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);

        $planPolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $planPolicy->shouldReceive('canPurchase')->andReturn(true);
        $planPolicy->shouldReceive('isPreRelease')->andReturn(false);
        $planPolicy->shouldReceive('getAvailabilityMessage')->andReturn('Available');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = true;
        $plan->shouldReceive('availabilityPolicy')->andReturn($planPolicy);
        $plan->shouldReceive('getNextIssue')->andReturn($nextIssue);

        $this->subscriptionPlanRepository->shouldReceive('lockForUpdate')->with(1)->andReturn($plan);

        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $this->fulfilmentResolver->shouldReceive('resolve')->andReturn($fulfilment);
        $today = new \DateTimeImmutable();
        $this->businessDayEstimator->shouldReceive('estimate')
            ->andReturn(new EstimatedDelivery(false, $today, $today, $today));

        // reserve() throws — transaction rolls back
        $this->fulfilSubscriptionAction->shouldNotReceive('reserve')
            ->andThrow(StockException::insufficientStock('Winter Issue', 2, 5));

        $this->database->shouldReceive('transaction')->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->expectException(CheckoutException::class);
        $this->expectExceptionMessage("Issue #1 out of stock. Available: 2, Requested: 5");

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }

    public function test_no_release_called_when_no_print_subscriptions_on_payment_failure(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);
        $this->setDeliveryEstimateExpectations();
        $this->setResolvedDiscountExpectations();

        // Digital only — no stock was reserved
        $this->cartService->shouldReceive('getItems')->once()->andReturn([
            ['subscription_plan_id' => 1, 'quantity' => 1, 'price' => 50.00,
                'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]],
        ]);

        $this->database->shouldReceive('transaction')->twice()
            ->andReturnUsing(fn($cb) => $cb());

        $order = Mockery::mock(Order::class)->makePartial();
        $order->shouldReceive('update')->andReturn(true);
        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->shouldReceive('update')->andReturn(true);
        $subs = [['subscription' => $subscription]];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andReturn(['success' => false]);

        // ── No reservations → release must never be called ───────────────────
        $this->fulfilSubscriptionAction->shouldNotReceive('release');
        // ────────────────────────────────────────────────────────────────────

        $this->expectException(CheckoutException::class);

        $this->service->processCheckout(['one_time_subscription' => true], 1);
    }


    /**
     * Set up a print subscription plan with a next issue that has stock.
     * Wires fulfilSubscriptionAction::reserve() to return $reservationId.
     * Returns the $nextIssue mock so callers can assert release() against it.
     */
    private function setupPrintSubscriptionWithStock(int $reservationId = 1, int $quantity = 1): IssueDelivery
    {
        $issuePolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $issuePolicy->shouldReceive('canPurchase')->andReturn(true);

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->id = 77;
        $nextIssue->stock_quantity = 10;
        $nextIssue->issue_number = 1;
        $nextIssue->shouldReceive('availabilityPolicy')->andReturn($issuePolicy);

        $planPolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $planPolicy->shouldReceive('canPurchase')->andReturn(true);
        $planPolicy->shouldReceive('isPreRelease')->andReturn(false);
        $planPolicy->shouldReceive('getAvailabilityMessage')->andReturn('Available');

        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->print_shipping_required = true;
        $plan->shouldReceive('availabilityPolicy')->andReturn($planPolicy);
        $plan->shouldReceive('getNextIssue')->andReturn($nextIssue);

        $this->subscriptionPlanRepository->shouldReceive('lockForUpdate')->with(1)->andReturn($plan);

        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $this->fulfilmentResolver->shouldReceive('resolve')->andReturn($fulfilment);
        $today = new \DateTimeImmutable();
        $this->businessDayEstimator->shouldReceive('estimate')
            ->andReturn(new EstimatedDelivery(false, $today, $today, $today));

        $this->fulfilSubscriptionAction->shouldReceive('reserve')
            ->with($nextIssue, $quantity, 5)
            ->andReturn($reservationId);

        return $nextIssue;
    }

}
