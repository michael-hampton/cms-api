<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Checkout\EstimatedDelivery;
use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\Subscriptions\SubscriptionType;
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
use App\Services\Shopping\CheckoutResponseBuilder;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use App\Services\Subscriptions\SubscriptionBatchFactory;
use App\Services\Vouchers\DiscountResolver;
use App\Services\Vouchers\ResolvedDiscounts;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
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
        );
    }

    public function test_process_checkout_fails_when_not_authenticated(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'price' => 50.00, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]]
            ]);

        $this->memberAuth->shouldReceive('check')
            ->once()
            ->andReturn(false);

        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Please login to purchase a subscription', $result['message']);
        $this->assertArrayHasKey('redirect', $result);
    }

    public function test_process_checkout_fails_when_no_subscription_in_cart(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['product_id' => 1, 'price' => 25.00] // Not a subscription
            ]);

        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('No subscription in cart', $result['message']);
    }

    public function test_process_checkout_creates_subscriptions_in_transaction(): void
    {
        $member = $this->createMockMember();
        $subscription = $this->createMockSubscription();
        $order = $this->createMockOrder();

        $this->setupAuthenticatedMember($member);
        $this->setupCartWithSingleSubscription();

        $this->setDeliveryEstimateExpectations();

        // Critical: verify transaction wraps subscription + order creation
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($order, $subscription) {
                return $callback();
            });

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

        $this->setupSuccessfulPayment();
        $this->setupCartClear();

        $result = $this->service->processCheckout([], 1);

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

        // Verify transaction completes BEFORE Stripe call
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use (&$transactionCallOrder, $order, $subscription) {
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

        // Stripe call happens AFTER transaction
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

        $this->orderDraftService->shouldReceive('attachPaymentIntent')
            ->once();

        $this->cartService->shouldReceive('clear')->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->andReturn(['success' => true]);

        $result = $this->service->processCheckout([], 1);

        // Verify order: transaction ends before Stripe call
        $this->assertEquals([
            'transaction_start',
            'transaction_end',
            'stripe_call'
        ], $transactionCallOrder);
    }

    public function test_process_checkout_handles_stripe_failure_gracefully(): void
    {
        // 1. Create the specific instances once
        $subscriptionMock = Mockery::mock(Subscription::class)->makePartial();
        $orderMock = Mockery::mock(Order::class)->makePartial();
        $member = $this->createMockMember();

        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        // Ensure cart returns the expected structure
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]]
            ]);

        // 2. Setup Database to execute BOTH transaction closures
        $this->database->shouldReceive('transaction')
            ->times(2)
            ->andReturnUsing(fn($cb) => $cb());

        // 3. Inject the EXACT mocks into the factory and draft service
        $subscriptionsWithPricing = [[
            'subscription' => $subscriptionMock, // Match this instance
            'pricing' => $this->createMockPricing()
        ]];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->andReturn($subscriptionsWithPricing);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->andReturn($orderMock); // Match this instance

        // 4. Simulate Stripe failure
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Card declined'
            ]);

        // 5. Set expectations on the instances injected above
        $orderMock->shouldReceive('update')
            ->once()
            ->with(['payment_status' => 'failed'])
            ->andReturn(true);

        $subscriptionMock->shouldReceive('update')
            ->once()
            ->with(['status' => 'cancelled'])
            ->andReturn(true);

        // 6. Execute
        $result = $this->service->processCheckout([], 1);

        // 7. Assertions
        $this->assertFalse($result['success']);
        $this->assertEquals('Payment processing failed', $result['message']);
    }

    public function test_process_checkout_applies_voucher_only_once(): void
    {
        $member = $this->createMockMember();
        $order = $this->createMockOrder();

        $this->setupAuthenticatedMember($member);
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        // Cart with 3 subscriptions
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'price' => 50.00, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]],
                ['subscription_plan_id' => 2, 'price' => 60.00, 'options' => ['delivery_type' => SubscriptionType::DIGITAL->value]],
                ['subscription_plan_id' => 3, 'price' => 70.00, 'options' => ['delivery_type' => SubscriptionType::PRINTED->value]]
            ]);

        // FIXED: Only one transaction for successful path
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Verify factory is called with voucher code
        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::on(function ($data) {
                    return $data['voucher_code'] === 'SAVE10';
                }),
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

        $subscriptionsWithPricing = [
            ['subscription' => $this->createMockSubscription(), 'pricing' => $this->createMockPricing()],
            ['subscription' => $this->createMockSubscription(), 'pricing' => $this->createMockPricing()],
            ['subscription' => $this->createMockSubscription(), 'pricing' => $this->createMockPricing()]
        ];

        $this->setupSuccessfulPayment($order, $subscriptionsWithPricing);
        $this->setupCartClear();

        $result = $this->service->processCheckout(['voucher_code' => 'SAVE10'], 1);

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

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $subs = [
            ['subscription' => $this->createMockSubscription(101), 'pricing' => $this->createMockPricing()],
            ['subscription' => $this->createMockSubscription(102), 'pricing' => $this->createMockPricing()]
        ];

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn(['success' => true, 'client_secret' => 'secret', 'payment_intent_id' => 'pi_123']);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();
        $this->cartService->shouldReceive('clear')->once();

        // Ensure the response builder is actually called with the multi-sub data
        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->andReturn(['success' => true, 'multiple_subscriptions' => true]);

        $result = $this->service->processCheckout([], 1);

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

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $subs = [['subscription' => $this->createMockSubscription(), 'pricing' => $this->createMockPricing('digital')]];
        $order = $this->createMockOrder();

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn(['success' => true, 'client_secret' => 'pi_123', 'payment_intent_id' => 'pi_123']);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();
        $this->cartService->shouldReceive('clear')->once();

        // The builder logic (which you provided) returns requires_shipping => false for digital
        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->andReturn(['success' => true, 'requires_shipping' => false]);

        $result = $this->service->processCheckout([], 1);

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

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $subs = [['subscription' => $this->createMockSubscription(), 'pricing' => $this->createMockPricing(SubscriptionType::PRINTED->value)]];
        $order = $this->createMockOrder();

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')->andReturn($subs);
        $this->orderDraftService->shouldReceive('createPendingOrder')->andReturn($order);

        $this->paymentIntentService->shouldReceive('createForOrder')->andReturn(['success' => true, 'client_secret' => 'pi_123', 'payment_intent_id' => 'pi_123']);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();
        $this->cartService->shouldReceive('clear')->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->andReturn(['success' => true, 'requires_shipping' => true]);

        $result = $this->service->processCheckout([], 1);

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
            ['subscription_plan_id' => 1]
        ]);

        $this->database->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

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

        $subs = [[
            'subscription' => $this->createMockSubscription(),
            'pricing' => $discountedPricing
        ]];

        $order = $this->createMockOrder();

        // 1. Verify Factory call
        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->with(Mockery::any(), Mockery::subset(['voucher_code' => $voucherCode]), $member, $siteId, Mockery::any())
            ->andReturn($subs);

        // 2. FIX: Order matching signature: ($subscriptions, $member, $siteId, $data)
        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->with(
                $subs,                               // 1st: array $subscriptions
                $member,                             // 2nd: Member $member
                $siteId,                             // 3rd: int $siteId
                Mockery::subset($inputData),          // 4th: array $data (contains voucher)
                Mockery::any(),
            )
            ->andReturn($order);

        // 3. Complete the rest of the flow
        $paymentResult = ['success' => true, 'client_secret' => 'pi_test', 'payment_intent_id' => 'pi_123'];
        $this->paymentIntentService->shouldReceive('createForOrder')->once()->andReturn($paymentResult);
        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();
        $this->cartService->shouldReceive('clear')->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->with($order, $subs, $paymentResult)
            ->andReturn(['success' => true, 'order_id' => 789]);

        $result = $this->service->processCheckout($inputData, $siteId);

        $this->assertTrue($result['success']);
    }

    /**
     * Tests that when the payment gateway returns a failure,
     * the system cancels the pending subscriptions and marks the order as failed.
     */
    public function test_process_checkout_handles_stripe_failure(): void
    {
        // 1. Create your ONE TRUE mock instance
        $subscriptionMock = Mockery::mock(Subscription::class)->makePartial();
        $orderMock = Mockery::mock(Order::class)->makePartial();
        $member = $this->createMockMember();
        $this->setResolvedDiscountExpectations();
        $this->setDeliveryEstimateExpectations();

        $this->setupAuthenticatedMember($member);
        $this->setupCartWithSingleSubscription();

        // 2. Mock the internal service used by the Factory
        // This is the missing link that ensures the hashes match
        $internalSubService = Mockery::mock(\App\Services\Shopping\OneTimeSubscriptionService::class);
        $internalSubService->shouldReceive('createOneTimeSubscription')
            ->andReturn($subscriptionMock);

        // Re-initialize the factory with the mocked internal service if necessary,
        // or just ensure the factory mock returns the correct instance:
        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->andReturn([[
                'subscription' => $subscriptionMock,
                'pricing' => $this->createMockPricing()
            ]]);

        // 3. Database and Order expectations
        $this->database->shouldReceive('transaction')
            ->times(2)
            ->andReturnUsing(fn($cb) => $cb());

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->andReturn($orderMock);

        // 4. Force failure
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->once()
            ->andReturn(['success' => false]);

        // 5. Assertions on the SPECIFIC instances
        $orderMock->shouldReceive('update')
            ->once()
            ->with(['payment_status' => 'failed']);

        $subscriptionMock->shouldReceive('update')
            ->once()
            ->with(['status' => 'cancelled'])
            ->andReturn(true);

        // 6. Execute
        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
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

    private function setupSuccessfulPayment(): void
    {
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123',
                'customer_id' => 'cus_test123'
            ]);

        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->andReturn(['success' => true]);
    }

    public function testProcessCheckoutFailsWithNoSubscriptions(): void
    {
        $items = [
            ['type' => 'product', 'product_id' => 1]
        ];

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($items);

        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('No subscription in cart', $result['message']);
    }

    public function testProcessCheckoutFailsWithEmptyCart(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([]);

        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('No subscription in cart', $result['message']);
    }

    public function testProcessCheckoutFailsWhenNotAuthenticated(): void
    {
        $items = [
            ['subscription_plan_id' => 1, 'delivery_type' => SubscriptionType::DIGITAL->value]
        ];

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($items);

        $this->memberAuth->shouldReceive('check')
            ->once()
            ->andReturn(false);

        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Please login to purchase a subscription', $result['message']);
        $this->assertArrayHasKey('redirect', $result);
    }

    private function setupCartClear(): void
    {
        $this->cartService->shouldReceive('clear')->once();
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
            ->andReturn([
                ['id' => 1, 'price' => 100] // No subscription_plan_id
            ]);

        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('No subscription in cart', $result['message']);
    }

    public function test_process_checkout_returns_error_when_not_authenticated(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->andReturn([
                ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]
            ]);

        $this->memberAuth->shouldReceive('check')->andReturn(false);

        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('login', $result['message']);
        $this->assertArrayHasKey('redirect', $result);
    }

    public function test_process_checkout_resolves_discounts(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->setDeliveryEstimateExpectations();

        $subscriptionItems = [
            ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100, 'quantity' => 1]
        ];

        $this->cartService->shouldReceive('getItems')
            ->andReturn($subscriptionItems);

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

        $this->discountResolver->shouldReceive('resolve')
            ->once()
            ->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 1;
        $subscriptions = [['subscription' => Mockery::mock()]];

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($order, $subscriptions) {
                return $callback();
            })
            ->andReturn([$order, $subscriptions]);

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->with(
                Mockery::any(),
                [],
                $member,
                1,
                $resolvedDiscounts
            )
            ->andReturn($subscriptions);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->with(
                $subscriptions,
                $member,
                1,
                [],
                $resolvedDiscounts
            )
            ->andReturn($order);

        $paymentResult = ['success' => true, 'payment_intent_id' => 'pi_123'];
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andReturn($paymentResult);

        $this->orderDraftService->shouldReceive('attachPaymentIntent')
            ->once();

        $this->cartService->shouldReceive('clear')->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->andReturn(['success' => true]);

        $result = $this->service->processCheckout([], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_creates_subscriptions_and_order_in_transaction(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $subscriptionItems = [
            ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100, 'quantity' => 1]
        ];

        $this->cartService->shouldReceive('getItems')
            ->andReturn($subscriptionItems);

        $this->setDeliveryEstimateExpectations();

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = Mockery::mock(ResolvedDiscounts::class);
        $this->discountResolver->shouldReceive('resolve')->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 1;
        $subscriptions = [['subscription' => Mockery::mock()]];

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($order, $subscriptions) {
                return $callback();
            })
            ->andReturn([$order, $subscriptions]);

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->once()
            ->andReturn($subscriptions);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->once()
            ->andReturn($order);

        $paymentResult = ['success' => true];
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andReturn($paymentResult);

        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();
        $this->cartService->shouldReceive('clear')->once();
        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->andReturn(['success' => true]);

        $result = $this->service->processCheckout([], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_handles_payment_failure(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $subscriptionItems = [
            ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]
        ];

        $this->cartService->shouldReceive('getItems')
            ->andReturn($subscriptionItems);

        $this->setDeliveryEstimateExpectations();

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
            ->twice() // Once for creation, once for failure handling
            ->andReturnUsing(function ($callback) use ($order, $subscriptions) {
                return $callback();
            })
            ->andReturn([$order, $subscriptions]);

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->andReturn($subscriptions);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->andReturn($order);

        $paymentResult = ['success' => false];
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andReturn($paymentResult);

        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Payment', $result['message']);
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

        $this->service->processCheckout([], 1);
    }

    public function test_process_checkout_attaches_payment_intent_after_success(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->setDeliveryEstimateExpectations();

        $subscriptionItems = [
            ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]
        ];

        $this->cartService->shouldReceive('getItems')
            ->andReturn($subscriptionItems);

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = Mockery::mock(ResolvedDiscounts::class);
        $this->discountResolver->shouldReceive('resolve')->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $subscriptions = [['subscription' => Mockery::mock()]];

        $this->database->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            })
            ->andReturn([$order, $subscriptions]);

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->andReturn($subscriptions);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->andReturn($order);

        $paymentResult = ['success' => true, 'payment_intent_id' => 'pi_123'];
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andReturn($paymentResult);

        $this->orderDraftService->shouldReceive('attachPaymentIntent')
            ->once()
            ->with($order, $paymentResult);

        $this->cartService->shouldReceive('clear')->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->andReturn(['success' => true]);

        $result = $this->service->processCheckout([], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_clears_cart_after_success(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->setDeliveryEstimateExpectations();

        $subscriptionItems = [
            ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]
        ];

        $this->cartService->shouldReceive('getItems')
            ->andReturn($subscriptionItems);

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = Mockery::mock(ResolvedDiscounts::class);
        $this->discountResolver->shouldReceive('resolve')->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $subscriptions = [['subscription' => Mockery::mock()]];

        $this->database->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            })
            ->andReturn([$order, $subscriptions]);

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->andReturn($subscriptions);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->andReturn($order);

        $paymentResult = ['success' => true];
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andReturn($paymentResult);

        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();

        $this->cartService->shouldReceive('clear')
            ->once();

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->andReturn(['success' => true]);

        $result = $this->service->processCheckout([], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_builds_response(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $this->setDeliveryEstimateExpectations();

        $subscriptionItems = [
            ['id' => 1, 'subscription_plan_id' => 123, 'price' => 100]
        ];

        $this->cartService->shouldReceive('getItems')
            ->andReturn($subscriptionItems);

        $this->memberAuth->shouldReceive('check')->andReturn(true);
        $this->memberAuth->shouldReceive('getMember')->andReturn($member);

        $resolvedDiscounts = Mockery::mock(ResolvedDiscounts::class);
        $this->discountResolver->shouldReceive('resolve')->andReturn($resolvedDiscounts);

        $order = Mockery::mock(Order::class)->makePartial();
        $subscriptions = [['subscription' => Mockery::mock()]];

        $this->database->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            })
            ->andReturn([$order, $subscriptions]);

        $this->subscriptionBatchFactory->shouldReceive('createPendingSubscriptions')
            ->andReturn($subscriptions);

        $this->orderDraftService->shouldReceive('createPendingOrder')
            ->andReturn($order);

        $paymentResult = ['success' => true];
        $this->paymentIntentService->shouldReceive('createForOrder')
            ->andReturn($paymentResult);

        $this->orderDraftService->shouldReceive('attachPaymentIntent')->once();
        $this->cartService->shouldReceive('clear')->once();

        $expectedResponse = [
            'success' => true,
            'order_id' => 1,
            'redirect_url' => '/success'
        ];

        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->with($order, $subscriptions, $paymentResult)
            ->andReturn($expectedResponse);

        $result = $this->service->processCheckout([], 1);

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
            ->andReturn([
                ['subscription_plan_id' => 999, 'price' => 50.00, 'options' => []]
            ]);

        $this->subscriptionPlanRepository->shouldReceive('lockForUpdate')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription plan not found');

        $this->service->processCheckout([], 1);
    }

    public function test_process_checkout_throws_when_plan_availability_policy_blocks_purchase(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'price' => 50.00, 'options' => []]
            ]);

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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Subscription not available: Plan is sold out');

        $this->service->processCheckout([], 1);
    }

    public function test_process_checkout_throws_when_print_subscription_has_no_next_issue(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'price' => 50.00, 'quantity' => 1, 'options' => []]
            ]);

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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No issues scheduled for Test Magazine');

        $this->service->processCheckout([], 1);
    }

    public function test_process_checkout_throws_when_print_issue_out_of_stock_and_not_preorder(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'price' => 50.00, 'quantity' => 2, 'options' => []]
            ]);

        $planPolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $planPolicy->shouldReceive('canPurchase')->once()->andReturn(true);
        $planPolicy->shouldReceive('isPreRelease')->andReturn(false);
        $planPolicy->shouldReceive('getAvailabilityMessage')->andReturn('Available');

        $issuePolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $issuePolicy->shouldReceive('canPurchase')->once()->andReturn(true);
        $issuePolicy->shouldReceive('isPreOrder')->once()->andReturn(false);

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->stock_quantity = 1; // less than requested quantity of 2
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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('out of stock');

        $this->service->processCheckout([], 1);
    }

    public function test_process_checkout_throws_when_preorder_has_no_expected_ship_date(): void
    {
        $member = $this->createMockMember();
        $this->setupAuthenticatedMember($member);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'price' => 50.00, 'quantity' => 2, 'options' => []]
            ]);

        $planPolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $planPolicy->shouldReceive('canPurchase')->once()->andReturn(true);
        $planPolicy->shouldReceive('isPreRelease')->andReturn(false);
        $planPolicy->shouldReceive('getAvailabilityMessage')->andReturn('Available');

        $issuePolicy = Mockery::mock(AvailabilityPolicyInterface::class);
        $issuePolicy->shouldReceive('canPurchase')->once()->andReturn(true);
        $issuePolicy->shouldReceive('isPreOrder')->once()->andReturn(true);
        $issuePolicy->shouldReceive('getExpectedShipDate')->once()->andReturn(null); // No date configured

        $nextIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $nextIssue->stock_quantity = 0; // triggers preorder path
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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Pre-order requires expected ship date');

        $this->service->processCheckout([], 1);
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

        // Verify the discount resolver receives a context that has a VoucherContext set
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

        $this->database->shouldReceive('transaction')
            ->once()
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
        $this->cartService->shouldReceive('clear')->once();
        $this->responseBuilder->shouldReceive('buildCheckoutResponse')
            ->once()
            ->andReturn(['success' => true]);

        $result = $this->service->processCheckout(['voucher_code' => 'SAVE20', 'voucher_id' => null], 1);

        $this->assertTrue($result['success']);
    }

}