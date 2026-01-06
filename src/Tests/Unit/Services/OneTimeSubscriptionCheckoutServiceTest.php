<?php

namespace App\Tests\Unit\Services;

use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Order;
use App\Models\Subscription;
use App\Services\CartService;
use App\Services\OneTimeSubscriptionCheckoutService;
use App\Services\OneTimeSubscriptionService;
use App\Services\OrderCalculationService;
use App\Services\OrderService;
use App\Services\Payment\StripePaymentProcessor;
use App\Services\ShippingService;
use App\Services\VoucherService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class OneTimeSubscriptionCheckoutServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OneTimeSubscriptionCheckoutService $service;
    private $cartService;
    private $subscriptionService;
    private $orderService;
    private $voucherService;
    private $shippingService;
    private $stripeProcessor;
    private $memberAuth;
    private $calculationService;
    private $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = Mockery::mock(CartService::class);
        $this->subscriptionService = Mockery::mock(OneTimeSubscriptionService::class);
        $this->orderService = Mockery::mock(OrderService::class);
        $this->voucherService = Mockery::mock(VoucherService::class);
        $this->shippingService = Mockery::mock(ShippingService::class);
        $this->stripeProcessor = Mockery::mock(StripePaymentProcessor::class);
        $this->memberAuth = Mockery::mock(MemberAuthWrapper::class);
        $this->calculationService = Mockery::mock(OrderCalculationService::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new OneTimeSubscriptionCheckoutService(
            $this->cartService,
            $this->subscriptionService,
            $this->orderService,
            $this->voucherService,
            $this->shippingService,
            $this->stripeProcessor,
            $this->memberAuth,
            $this->calculationService,
            $this->database
        );
    }

    public function test_process_checkout_fails_when_not_authenticated(): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                ['subscription_plan_id' => 1, 'price' => 50.00, 'options' => ['delivery_type' => 'digital']]
            ]);

        $this->memberAuth->shouldReceive('check')
            ->once()
            ->andReturn(false);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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
                ['product_id' => 1, 'price' => 25.00]
            ]);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('No subscription in cart', $result['message']);
    }

    public function test_process_checkout_succeeds_with_digital_delivery(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;
        $member->email = 'test@example.com';

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 456;
        $subscription->currency = 'USD';
        $subscription->plan_name = 'Premium Magazine';

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 789;
        $order->order_number = 'ORD-12345';

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                [
                    'subscription_plan_id' => 1,
                    'price' => 50.00,
                    'options' => ['delivery_type' => 'digital']
                ]
            ]);

        $this->memberAuth->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $this->memberAuth->shouldReceive('getMember')
            ->once()
            ->andReturn($member);

        $this->subscriptionService->shouldReceive('createOneTimeSubscription')
            ->once()
            ->with(123, 1, 'digital', 1, null, 0)
            ->andReturn($subscription);

        $this->shippingService->shouldReceive('calculateShipping')
            ->never();

        $this->calculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 50.00, 'shipping' => 0, 'discount' => 0])
            ->andReturn([
                'subtotal' => 50.00,
                'shipping' => 0,
                'tax' => 5.00,
                'discount' => 0,
                'total' => 55.00
            ]);

        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')
            ->once()
            ->with(Mockery::on(function ($data) use ($member) {
                return isset($data['amount'])
                    && $data['amount'] === 55.00
                    && isset($data['member'])
                    && $data['member']->id === $member->id
                    && isset($data['metadata']['member_id']);
            }))
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123',
                'customer_id' => 'cus_test123'
            ]);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andReturn($order);

        $this->cartService->shouldReceive('clear')
            ->once();

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->processCheckout([], 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test_secret', $result['client_secret']);
        $this->assertEquals('pi_test_123', $result['payment_intent_id']);
        $this->assertEquals(456, $result['subscription_id']);
        $this->assertEquals(789, $result['order_id']);
        $this->assertEquals('ORD-12345', $result['order_number']);
        $this->assertFalse($result['requires_shipping']);
    }

    public function test_process_checkout_succeeds_with_print_delivery(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;
        $member->email = 'test@example.com';

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 456;
        $subscription->currency = 'USD';
        $subscription->plan_name = 'Premium Magazine';

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 789;
        $order->order_number = 'ORD-12345';

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                [
                    'subscription_plan_id' => 1,
                    'price' => 50.00,
                    'options' => ['delivery_type' => 'print']
                ]
            ]);

        $this->memberAuth->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $this->memberAuth->shouldReceive('getMember')
            ->once()
            ->andReturn($member);

        $this->subscriptionService->shouldReceive('createOneTimeSubscription')
            ->once()
            ->with(123, 1, 'print', 1, null, 0)
            ->andReturn($subscription);

        $this->shippingService->shouldReceive('calculateShipping')
            ->twice()
            ->with(50.00, [])
            ->andReturn(10.00);

        $this->calculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 50.00, 'shipping' => 10.00, 'discount' => 0])
            ->andReturn([
                'subtotal' => 50.00,
                'shipping' => 10.00,
                'tax' => 6.00,
                'discount' => 0,
                'total' => 66.00
            ]);

        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')
            ->once()
            ->with(Mockery::on(function ($data) use ($member) {
                return isset($data['amount'])
                    && isset($data['member'])
                    && $data['member']->id === $member->id;
            }))
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123',
                'customer_id' => 'cus_test123'
            ]);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andReturn($order);

        $this->cartService->shouldReceive('clear')
            ->once();

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->processCheckout([], 1);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['requires_shipping']);
    }

    public function test_process_checkout_applies_voucher_discount(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 456;
        $subscription->currency = 'USD';
        $subscription->plan_name = 'Premium Magazine';

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 789;
        $order->order_number = 'ORD-12345';

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                [
                    'subscription_plan_id' => 1,
                    'price' => 50.00,
                    'options' => ['delivery_type' => 'digital']
                ]
            ]);

        $this->memberAuth->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $this->memberAuth->shouldReceive('getMember')
            ->once()
            ->andReturn($member);

        $this->voucherService->shouldReceive('validateVoucherForSubscription')
            ->once()
            ->with('SAVE10', 1, 123)
            ->andReturn([
                'valid' => true,
                'voucher_id' => 999,
                'discount' => 5.00
            ]);

        $this->subscriptionService->shouldReceive('createOneTimeSubscription')
            ->once()
            ->with(123, 1, 'digital', 1, 999, 5.00)
            ->andReturn($subscription);

        $this->calculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 45.00, 'shipping' => 0, 'discount' => 5.00])
            ->andReturn([
                'subtotal' => 45.00,
                'shipping' => 0,
                'tax' => 4.50,
                'discount' => 5.00,
                'total' => 49.50
            ]);

        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123',
                'customer_id' => 'cus_test123'
            ]);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andReturn($order);

        $this->cartService->shouldReceive('clear')
            ->once();

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->processCheckout(['voucher_code' => 'SAVE10'], 1);

        $this->assertTrue($result['success']);
    }

    public function test_process_checkout_handles_stripe_failure(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;

        $subscription = Mockery::mock(Subscription::class)->makePartial();
        $subscription->id = 456;
        $subscription->currency = 'USD';
        $subscription->plan_name = 'Premium Magazine';

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([
                [
                    'subscription_plan_id' => 1,
                    'price' => 50.00,
                    'options' => ['delivery_type' => 'digital']
                ]
            ]);

        $this->memberAuth->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $this->memberAuth->shouldReceive('getMember')
            ->once()
            ->andReturn($member);

        $this->subscriptionService->shouldReceive('createOneTimeSubscription')
            ->once()
            ->andReturn($subscription);

        $this->calculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->andReturn([
                'subtotal' => 50.00,
                'shipping' => 0,
                'tax' => 5.00,
                'discount' => 0,
                'total' => 55.00
            ]);

        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Card declined'
            ]);

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->processCheckout([], 1);

        $this->assertFalse($result['success']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}