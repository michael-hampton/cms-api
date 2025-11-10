<?php

namespace App\Tests\Unit\Services;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Models\Member;
use App\Services\CartService;
use App\Services\CheckoutService;
use App\Services\OrderService;
use App\Services\ShippingService;
use App\Services\VoucherService;
use App\Models\Order;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;

class CheckoutServiceTest extends FunctionalTestCase
{
    private $cartService;
    private $orderService;
    private $voucherService;
    private $shippingService;
    private CheckoutService $service;
    private $memberAuthWrapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = m::mock(CartService::class);
        $this->memberAuthWrapper = m::mock(MemberAuthWrapper::class);
        $this->orderService = m::mock(OrderService::class);
        $this->voucherService = m::mock(VoucherService::class);
        $this->shippingService = m::mock(ShippingService::class);

        $this->service = new CheckoutService(
            $this->cartService,
            $this->orderService,
            $this->voucherService,
            $this->shippingService,
            $this->memberAuthWrapper
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testProcessCheckoutFailsWhenFirstNameMissing()
    {
        $data = [
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890'
        ];

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('First name is required', $result['message']);
    }

    public function testProcessCheckoutFailsWhenLastNameMissing()
    {
        $data = [
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '1234567890'
        ];

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Last name is required', $result['message']);
    }

    public function testProcessCheckoutFailsWhenEmailMissing()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890'
        ];

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Email is required', $result['message']);
    }

    public function testProcessCheckoutFailsWhenPhoneMissing()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ];

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Phone is required', $result['message']);
    }

    public function testProcessCheckoutFailsWhenAddressMissingAndNoSavedAddress()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890'
        ];

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Address is required', $result['message']);
    }

    public function testProcessCheckoutFailsWhenCartIsEmpty()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'City',
            'postal_code' => '12345',
            'country' => 'US'
        ];

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([]);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cart is empty', $result['message']);
    }

    public function testProcessCheckoutSuccessfullyWithoutVoucher()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'City',
            'postal_code' => '12345',
            'country' => 'US',
            'payment_method' => 'card'
        ];

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Product 1',
                'product_sku' => 'SKU1',
                'price' => 50.00,
                'quantity' => 2,
                'subtotal' => 100.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->cartService->shouldReceive('getTotal')
            ->once()
            ->andReturn(100.00);

        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->with(100.00, $data)
            ->andReturn(10.00);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->with(m::on(function($orderData) {
                return $orderData['subtotal'] === 100.00
                    && $orderData['shipping'] === 10.00
                    && $orderData['tax'] === 11.00
                    && $orderData['total'] === 121.00
                    && $orderData['discount'] === 0;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->cartService->shouldReceive('clear')
            ->once();

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('member')
            ->once()
            ->andReturn($member);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Order placed successfully', $result['message']);
        $this->assertEquals('ORD-123', $result['order_id']);
        $this->assertEquals(121.00, $result['total']);
    }

    public function testProcessCheckoutCalculatesShippingCorrectly()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'City',
            'postal_code' => '12345',
            'country' => 'CA', // Canada
            'payment_method' => 'card'
        ];

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 50.00,
                'quantity' => 1,
                'subtotal' => 50.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->order_number = 'ORD-123';

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);

        // Canada shipping should be 15.00
        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->with(50.00, $data)
            ->andReturn(15.00);

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('member')
            ->once()
            ->andReturn($member);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->with(m::on(function($orderData) {
                return $orderData['shipping'] === 15.00;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutWithFreeShipping()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'City',
            'postal_code' => '12345',
            'country' => 'US'
        ];

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 150.00,
                'quantity' => 1,
                'subtotal' => 150.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->order_number = 'ORD-123';

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(150.00);

        // Free shipping over $100
        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->with(150.00, $data)
            ->andReturn(0.00);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->with(m::on(function($orderData) {
                return $orderData['subtotal'] === 150.00
                    && $orderData['shipping'] === 0.00
                    && $orderData['tax'] === 15.00
                    && $orderData['total'] === 165.00;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('member')
            ->once()
            ->andReturn($member);

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutWithVoucher()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'City',
            'postal_code' => '12345',
            'country' => 'US',
            'voucher_code' => 'SAVE10',
            'voucher_id' => 5,
            'discount_amount' => 10.00
        ];

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 100.00,
                'quantity' => 1,
                'subtotal' => 100.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(100.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->with(m::on(function($orderData) {
                return $orderData['subtotal'] == 100
                    && $orderData['discount'] == 10
                    && $orderData['shipping'] == 10
                    && $orderData['tax'] == 10
                    && $orderData['total'] == 110
                    && $orderData['voucher_code'] == 'SAVE10';
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->with(5, null, 10.00, 1);

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('member')
            ->twice()
            ->andReturn($member);

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals(110.00, $result['total']);
    }

    public function testProcessCheckoutWithAuthenticatedUser()
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('member')
            ->once()
            ->andReturn($member);

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'saved_address' => 5 // Using saved address
        ];

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 50.00,
                'quantity' => 1,
                'subtotal' => 50.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->order_number = 'ORD-123';

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->with(m::on(function($orderData) {
                return $orderData['user_id'] === 10
                    && $orderData['shipping_address_id'] === 5;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutWithSavedAddress()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'saved_address' => 7
        ];

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 50.00,
                'quantity' => 1,
                'subtotal' => 50.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->order_number = 'ORD-123';

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('member')
            ->once()
            ->andReturn($member);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->with(m::on(function($orderData) {
                return $orderData['shipping_address_id'] === 7
                    && !isset($orderData['shipping_address']);
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutHandlesOrderCreationException()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'City',
            'postal_code' => '12345',
            'country' => 'US'
        ];

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 50.00,
                'quantity' => 1,
                'subtotal' => 50.00
            ]
        ];

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('member')
            ->once()
            ->andReturn($member);

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andThrow(new \Exception('Database error'));

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to create order', $result['message']);
        $this->assertStringContainsString('Database error', $result['message']);
    }

    public function testProcessCheckoutCalculatesTaxCorrectly()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'City',
            'postal_code' => '12345',
            'country' => 'US'
        ];

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 100.00,
                'quantity' => 2,
                'subtotal' => 200.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->order_number = 'ORD-123';

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(200.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(0.00);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->with(m::on(function($orderData) {
                // Tax = (200 - 0 + 0) * 0.1 = 20.00
                return $orderData['tax'] === 20.00;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->cartService->shouldReceive('clear')->once();

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('member')
            ->once()
            ->andReturn($member);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutDoesNotApplyVoucherWhenDiscountIsZero()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'City',
            'postal_code' => '12345',
            'country' => 'US',
            'voucher_code' => 'INVALID',
            'voucher_id' => 5,
            'discount_amount' => 0.00
        ];

        $member = m::mock(Member::class)->makePartial();

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 50.00,
                'quantity' => 1,
                'subtotal' => 50.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->order_number = 'ORD-123';

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('member')
            ->once()
            ->andReturn($member);

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->orderService->shouldReceive('createOrder')->once()->andReturn($mockOrder);

        // Voucher service should NOT be called
        $this->voucherService->shouldReceive('applyVoucher')->never();

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }
}