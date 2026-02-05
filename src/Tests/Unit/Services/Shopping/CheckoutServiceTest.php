<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Order;
use App\Models\Shipment;
use App\Repositories\Billing\ShipmentRepository;
use App\Services\Billing\CheckoutSplittingService;
use App\Services\Billing\Order\OrderCreationService;
use App\Services\Billing\Order\OrderManager;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\PaymentAllocationService;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\TaxCalculatorService;
use App\Services\Currency\CurrencyResolver;
use App\Services\Shopping\CartService;
use App\Services\Shopping\CheckoutService;
use App\Services\Shopping\MerchantShippingService;
use App\Services\Shopping\ShippingService;
use App\Services\Vouchers\VoucherService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery as m;

class CheckoutServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private CartService $cartService;
    private OrderCreationService $orderService;
    private VoucherService $voucherService;
    private ShippingService $shippingService;
    private CheckoutService $service;
    private MemberAuthWrapper $memberAuthWrapper;
    private OrderCalculationService $orderCalculationService;
    private StripePaymentProcessor $stripePaymentService;
    private CheckoutSplittingService $mockSplittingService;
    private PaymentAllocationService $mockAllocationService;
    private MerchantShippingService $mockMerchantShippingService;
    private ShipmentRepository $mockShipmentRepository;
    private CurrencyResolver $currencyResolver;
    private Database $databaseMock;
    private OrderManager $orderManager;
    private TaxCalculatorService $taxCalculatorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = m::mock(CartService::class);
        $this->orderCalculationService = m::mock(OrderCalculationService::class);
        $this->orderManager = m::mock(OrderManager::class);
        $this->memberAuthWrapper = m::mock(MemberAuthWrapper::class);
        $this->orderService = m::mock(OrderCreationService::class);
        $this->voucherService = m::mock(VoucherService::class);
        $this->shippingService = m::mock(ShippingService::class);
        $this->stripePaymentService = m::mock(StripePaymentProcessor::class);
        $this->mockSplittingService = m::mock(CheckoutSplittingService::class);
        $this->mockAllocationService = m::mock(PaymentAllocationService::class);
        $this->mockMerchantShippingService = m::mock(MerchantShippingService::class);
        $this->mockShipmentRepository = m::mock(ShipmentRepository::class);
        $this->currencyResolver = m::mock(CurrencyResolver::class);
        $this->databaseMock = m::mock(Database::class);
        $this->taxCalculatorService = m::mock(TaxCalculatorService::class);

        $this->service = new CheckoutService(
            $this->cartService,
            $this->orderService,
            $this->voucherService,
            $this->shippingService,
            $this->memberAuthWrapper,
            $this->orderCalculationService,
            $this->stripePaymentService,
            $this->mockSplittingService,
            $this->mockAllocationService,
            $this->mockMerchantShippingService,
            $this->mockShipmentRepository,
            $this->currencyResolver,
            $this->databaseMock,
            $this->orderManager,
            $this->taxCalculatorService
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testProcessCheckoutUsesTransaction(): void
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

        $this->setRequiresShippingExpectation();
        $this->setTaxCalculatorExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->cartService->shouldReceive('getTotal')
            ->once()
            ->andReturn(100.00);

        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->andReturn(10.00);

        $this->voucherService->shouldReceive('validateVoucher')
            ->never();

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->andReturn([
                'subtotal' => 100.00,
                'shipping' => 10.00,
                'discount' => 0,
                'tax' => 11.00,
                'total' => 121.00
            ]);

        $this->currencyResolver->shouldReceive('resolve')
            ->once()
            ->with(1)
            ->andReturn('usd');

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret_123',
                'payment_intent_id' => 'pi_test_123'
            ]);

        $this->orderService->shouldReceive('create')
            ->once()
            ->andReturn($mockOrder);

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test_secret_123', $result['client_secret']);
    }


    public function testProcessCheckoutFailsWhenFirstNameMissing()
    {
        $data = [
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890'
        ];

        $this->setRequiresShippingExpectation();

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

        $this->setRequiresShippingExpectation();

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

        $this->setRequiresShippingExpectation();

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

        $this->setRequiresShippingExpectation();

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

        $this->setRequiresShippingExpectation();

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

        $this->setRequiresShippingExpectation();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([]);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cart is empty', $result['message']);
    }

    public function testProcessCheckoutFailsWhenStripePaymentIntentFails()
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

        $this->setRequiresShippingExpectation();
        $this->setTaxCalculatorExpectations();

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

        $this->setCurrencyExpectations();

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->once()
            ->andReturn(m::mock(Member::class)->makePartial());

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 100, 'shipping' => 10, 'discount' => 0, 'tax' => 11])
            ->andReturn([
                'subtotal' => 100.00,
                'shipping' => 10.00,
                'discount' => 0,
                'tax' => 11.00,
                'total' => 121.00
            ]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->with(m::on(function ($params) {
                return $params['amount'] === 121.00
                    && $params['currency'] === 'usd'
                    && $params['site_id'] === 1;
            }))
            ->andReturn([
                'success' => false,
                'message' => 'Card declined'
            ]);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Card declined', $result['message']);
    }

    public function testProcessCheckoutSuccessfullyReturnsPaymentIntentDetails()
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

        $this->setRequiresShippingExpectation();
        $this->setMemberAuthExpectations();
        $this->setTransactionExpectations();
        $this->setTaxCalculatorExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->setCurrencyExpectations();

        $this->cartService->shouldReceive('getTotal')
            ->once()
            ->andReturn(100.00);

        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->with(100.00, $data)
            ->andReturn(10.00);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 100, 'shipping' => 10, 'discount' => 0, 'tax' => 11])
            ->andReturn([
                'subtotal' => 100.00,
                'shipping' => 10.00,
                'discount' => 0,
                'tax' => 11,
                'total' => 121.00
            ]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret_123',
                'payment_intent_id' => 'pi_test_123'
            ]);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                return $orderData['subtotal'] == 100
                    && $orderData['shipping'] == 10
                    && $orderData['tax'] == 11
                    && $orderData['total'] == 121
                    && $orderData['discount'] == 0;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->once()
            ->andReturn($member);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test_secret_123', $result['client_secret']);
        $this->assertEquals('pi_test_123', $result['payment_intent_id']);
        $this->assertEquals('ORD-123', $result['order_id']);
        $this->assertEquals(1, $result['order_internal_id']);
        $this->assertEquals(121.00, $result['total']);
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

        $this->setRequiresShippingExpectation();
        $this->setTransactionExpectations();
        $this->setTaxCalculatorExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->setCurrencyExpectations();

        $this->cartService->shouldReceive('getTotal')
            ->once()
            ->andReturn(100.00);

        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->with(100.00, $data)
            ->andReturn(10.00);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                return $orderData['subtotal'] === 100.00
                    && $orderData['shipping'] === 10.00
                    && $orderData['tax'] === 11.00
                    && $orderData['total'] === 121.00
                    && $orderData['discount'] === 0;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 100, 'shipping' => 10, 'discount' => 0, 'tax' => 11.00])
            ->andReturn(['subtotal' => 100.00, 'shipping' => 10.00, 'discount' => 0, 'tax' => 11.00, 'total' => 121.00]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret_123',
                'payment_intent_id' => 'pi_test_123'
            ]);

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
            'country' => 'CA',
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
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setRequiresShippingExpectation();
        $this->setTransactionExpectations();
        $this->setTaxCalculatorExpectations(5000, 1500, 'CA');

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);

        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->with(50.00, $data)
            ->andReturn(15.00);

        $this->setCurrencyExpectations();

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 50, 'shipping' => 15, 'discount' => 0, 'tax' => 11])
            ->andReturn([
                'subtotal' => 50.00,
                'shipping' => 15.00,
                'discount' => 0,
                'tax' => 6.50,
                'total' => 71.50
            ]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123'
            ]);

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                return $orderData['shipping'] === 15.00;
            }), m::any(), 1)
            ->andReturn($mockOrder);

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
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setRequiresShippingExpectation();
        $this->setTransactionExpectations();
        $this->setTaxCalculatorExpectations(15000, 0, 'US');

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(150.00);

        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->with(150.00, $data)
            ->andReturn(0.00);

        $this->setCurrencyExpectations();

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 150, 'shipping' => 0, 'discount' => 0, 'tax' => 11])
            ->andReturn([
                'subtotal' => 150.00,
                'shipping' => 0.00,
                'discount' => 0,
                'tax' => 15.00,
                'total' => 165.00
            ]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123'
            ]);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                return $orderData['subtotal'] === 150.00
                    && $orderData['shipping'] === 0.00
                    && $orderData['tax'] === 15.00
                    && $orderData['total'] === 165.00;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

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

        $this->setRequiresShippingExpectation();
        $this->setTransactionExpectations();
        $this->setTaxCalculatorExpectations();

        $this->voucherService->shouldReceive('validateVoucher')
            ->once()
            ->with(5, 100)
            ->andReturn([
                'valid' => true,
                'voucher_code' => 'SAVE10',
                'discount' => 10.00
            ]);

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(100.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 100, 'shipping' => 10, 'discount' => 10, 'tax' => 11])
            ->andReturn([
                'subtotal' => 100.00,
                'shipping' => 10.00,
                'discount' => 10.00,
                'tax' => 10.00,
                'total' => 110.00
            ]);

        $this->setCurrencyExpectations();

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123'
            ]);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
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
            ->with(5, m::any(), 10.00, 1);

        $this->memberAuthWrapper->shouldReceive('check')
            ->times(3)
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->times(3)
            ->andReturn($member);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals(110.00, $result['total']);
    }

    public function testProcessCheckoutWithAuthenticatedUser()
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $this->setCurrencyExpectations();
        $this->setTransactionExpectations();
        $this->setRequiresShippingExpectation();
        $this->setTaxCalculatorExpectations(5000, 1000, 'GB');

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'saved_address' => 5,
            'postal_code' => '12345',
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
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 50, 'shipping' => 10, 'discount' => 0, 'tax' => 11])
            ->andReturn([
                'subtotal' => 50.00,
                'shipping' => 10.00,
                'discount' => 0,
                'tax' => 6.00,
                'total' => 66.00
            ]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123'
            ]);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                return $orderData['user_id'] === 10
                    && $orderData['shipping_address_id'] === 5;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutWithSavedAddress()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'postal_code' => '12345',
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
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setRequiresShippingExpectation();

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->setCurrencyExpectations();
        $this->setTransactionExpectations();
        $this->setTaxCalculatorExpectations(5000, 1000, 'GB');

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 50, 'shipping' => 10, 'discount' => 0, 'tax' => 11])
            ->andReturn([
                'subtotal' => 50.00,
                'shipping' => 10.00,
                'discount' => 0,
                'tax' => 6.00,
                'total' => 66.00
            ]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123'
            ]);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                return $orderData['shipping_address_id'] === 7
                    && !isset($orderData['shipping_address']);
            }), m::any(), 1)
            ->andReturn($mockOrder);

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
            ->twice()
            ->andReturn(true);

        $this->setCurrencyExpectations();
        $this->setTransactionExpectations();
        $this->setRequiresShippingExpectation();
        $this->setTaxCalculatorExpectations(5000, 1000);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 50, 'shipping' => 10, 'discount' => 0, 'tax' => 11])
            ->andReturn([
                'subtotal' => 50.00,
                'shipping' => 10.00,
                'discount' => 0,
                'tax' => 6.00,
                'total' => 66.00
            ]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123'
            ]);

        $this->orderService->shouldReceive('create')
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

        $this->setCurrencyExpectations();
        $this->setTransactionExpectations();
        $this->setRequiresShippingExpectation();
        $this->setTaxCalculatorExpectations(20000, 0, 'US');

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                // Tax = (200 - 0 + 0) * 0.1 = 20.00
                return $orderData['tax'] === 20.00;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $member = m::mock(Member::class)->makePartial();

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 200, 'shipping' => 0, 'discount' => 0, 'tax' => 11])
            ->andReturn(['subtotal' => 100.00, 'shipping' => 10.00, 'discount' => 0, 'tax' => 20.00, 'total' => 121.00]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123'
            ]);

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
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $this->setCurrencyExpectations();
        $this->setTransactionExpectations();
        $this->setRequiresShippingExpectation();
        $this->setTaxCalculatorExpectations(5000, 1000);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 50, 'shipping' => 10, 'discount' => 0, 'tax' => 11])
            ->andReturn([
                'subtotal' => 50.00,
                'shipping' => 10.00,
                'discount' => 0,
                'tax' => 6.00,
                'total' => 66.00
            ]);

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'pi_test_secret',
                'payment_intent_id' => 'pi_test_123'
            ]);

        $this->voucherService->shouldReceive('validateVoucher')
            ->once()
            ->with(5, 50)
            ->andReturn([
                'valid' => false,
                'voucher_code' => 'INVALID',
                'discount' => 0.00
            ]);

        $this->orderService->shouldReceive('create')->once()->andReturn($mockOrder);

        // Voucher service should NOT be called
        $this->voucherService->shouldReceive('applyVoucher')->never();

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testConfirmRegularCheckoutPaymentSucceeds()
    {
        $this->stripePaymentService->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with('pi_test_123')
            ->andReturn([
                'success' => true,
                'status' => 'succeeded'
            ]);

        $this->orderManager->shouldReceive('updateOrderStatus')
            ->once()
            ->with(1, 'completed', 'paid');

        $this->setTransactionExpectations();

        $this->cartService->shouldReceive('clear')
            ->once();

        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;

        $this->orderManager->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($order);

        $result = $this->service->confirmRegularCheckoutPayment('pi_test_123', 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Order completed successfully', $result['message']);
    }

    public function testConfirmRegularCheckoutPaymentFailsWhenPaymentNotSucceeded()
    {
        $this->stripePaymentService->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with('pi_test_123')
            ->andReturn(['success' => true,
                'status' => 'requires_action'
            ]);
        $result = $this->service->confirmRegularCheckoutPayment('pi_test_123', 1);
        $this->assertFalse($result['success']);
        $this->assertEquals('Payment confirmation failed', $result['message']);
    }

    public function testConfirmRegularCheckoutPaymentHandlesException()
    {
        $this->stripePaymentService->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with('pi_test_123')
            ->andThrow(new \Exception('Network error'));

        $result = $this->service->confirmRegularCheckoutPayment('pi_test_123', 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Payment confirmation error', $result['message']);
    }

    public function testProcessMultiMerchantCheckoutSuccessfully()
    {
        $siteId = 1;
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'country' => 'US',
            'currency' => 'usd'
        ];

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 50.00,
                'quantity' => 1,
                'subtotal' => 50.00,
                'merchant_id' => 1
            ],
            [
                'id' => 2,
                'product_id' => 2,
                'product_name' => 'Product 2',
                'price' => 75.00,
                'quantity' => 1,
                'subtotal' => 75.00,
                'merchant_id' => 2
            ]
        ];

        $groups = [
            'merchant_1' => [
                'merchant_id' => 1,
                'stripe_group_key' => 'merchant_1',
                'items' => [$cartItems[0]]
            ],
            'merchant_2' => [
                'merchant_id' => 2,
                'stripe_group_key' => 'merchant_2',
                'items' => [$cartItems[1]]
            ]
        ];

        $shippingPerGroup = [
            'merchant_1' => 5.00,
            'merchant_2' => 7.00
        ];

        $allocations = [
            'merchant_1' => [
                'subtotal' => 50.00,
                'shipping' => 5.00,
                'tax' => 5.00,
                'discount' => 0.00,
                'total' => 60.00,
                'stripe_eligible' => true
            ],
            'merchant_2' => [
                'subtotal' => 75.00,
                'shipping' => 7.00,
                'tax' => 7.50,
                'discount' => 0.00,
                'total' => 89.50,
                'stripe_eligible' => true
            ]
        ];

        $mockOrder1 = m::mock(Order::class)->makePartial();
        $mockOrder1->id = 1;
        $mockOrder1->order_number = 'ORD-1234';

        $mockOrder2 = m::mock(Order::class)->makePartial();
        $mockOrder2->id = 2;
        $mockOrder2->order_number = 'ORD-5678';

        $this->setCurrencyExpectations();
        $this->setRequiresShippingExpectation();

        // Mock cart service
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->cartService->shouldReceive('getTotal')
            ->once()
            ->andReturn(125.00);

        $this->setTransactionExpectations();

        // Mock splitting service
        $this->mockSplittingService->shouldReceive('splitByMerchant')
            ->once()
            ->with($cartItems)
            ->andReturn($groups);

        // Mock merchant shipping
        $this->mockMerchantShippingService->shouldReceive('calculatePerGroup')
            ->once()
            ->with($groups, 'US')
            ->andReturn($shippingPerGroup);

        // Mock calculation service
        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->andReturn([
                'subtotal' => 125.00,
                'shipping' => 12.00,
                'tax' => 12.50,
                'discount' => 0.00,
                'total' => 149.50
            ]);

        // Mock allocation service
        $this->mockAllocationService->shouldReceive('allocate')
            ->once()
            ->andReturn($allocations);

        // Mock Stripe payment intents
        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->twice()
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_123',
                'client_secret' => 'secret_123'
            ], [
                'success' => true,
                'payment_intent_id' => 'pi_456',
                'client_secret' => 'secret_456'
            ]);

        // Mock order service
        $this->orderService->shouldReceive('createMerchantOrder')
            ->twice()
            ->andReturn($mockOrder1, $mockOrder2);

        // Mock shipment repository
        $this->mockShipmentRepository->shouldReceive('create')
            ->twice()
            ->andReturn(m::mock(Shipment::class));

        // Mock member auth
        $this->memberAuthWrapper->shouldReceive('check')
            ->atLeast()->once()
            ->andReturn(false);

        $this->mockMerchantShippingService->shouldReceive('isConsolidationEnabled')
            ->twice()
            ->andReturn(true);

        // Mock cart clear
        $this->cartService->shouldReceive('clear')
            ->once();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('checkout_id', $result);
        $this->assertArrayHasKey('order_numbers', $result);
        $this->assertCount(2, $result['order_numbers']);
        $this->assertEquals(['ORD-1234', 'ORD-5678'], $result['order_numbers']);
    }

    public function testProcessMultiMerchantCheckoutValidationFailure()
    {
        $siteId = 1;
        $data = []; // Missing required fields

        $this->setRequiresShippingExpectation();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testProcessMultiMerchantCheckoutStripeFailure()
    {
        $siteId = 1;
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'country' => 'US',
            'currency' => 'usd'
        ];

        $cartItems = [
            ['id' => 1, 'product_id' => 1, 'merchant_id' => 1, 'price' => 50.00, 'quantity' => 1, 'subtotal' => 50.00]
        ];

        $groups = [
            'merchant_1' => [
                'merchant_id' => 1,
                'stripe_group_key' => 'merchant_1',
                'items' => $cartItems
            ]
        ];

        $this->cartService->shouldReceive('getItems')->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->andReturn(50.00);
        $this->mockSplittingService->shouldReceive('splitByMerchant')->andReturn($groups);
        $this->mockMerchantShippingService->shouldReceive('calculatePerGroup')->andReturn(['merchant_1' => 5.00]);
        $this->orderCalculationService->shouldReceive('calculateOrderTotals')->andReturn([
            'subtotal' => 50.00,
            'total' => 55.00
        ]);
        $this->mockAllocationService->shouldReceive('allocate')->andReturn([
            'merchant_1' => ['total' => 55.00, 'stripe_eligible' => true]
        ]);

        $this->setCurrencyExpectations();
        $this->setRequiresShippingExpectation();

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Card declined'
            ]);

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Card declined', $result['message']);
    }

    public function testProcessMultiMerchantCheckoutWithVoucher()
    {
        $siteId = 1;
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'country' => 'US',
            'currency' => 'usd',
            'voucher_code' => 'SAVE10',
            'voucher_id' => 1,
            'discount_amount' => 10.00
        ];

        $cartItems = [
            ['id' => 1, 'product_id' => 1, 'merchant_id' => 1, 'price' => 50.00, 'quantity' => 1, 'subtotal' => 50.00]
        ];

        $groups = [
            'merchant_1' => [
                'merchant_id' => 1,
                'stripe_group_key' => 'merchant_1',
                'items' => $cartItems
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-1234';

        $this->setTransactionExpectations();
        $this->setRequiresShippingExpectation();

        $this->voucherService->shouldReceive('validateVoucher')
            ->once()
            ->with(1, 50)
            ->andReturn([
                'valid' => true,
                'voucher_code' => 'SAVE10',
                'discount' => 10.00,
                'voucher_id' => 1
            ]);

        $this->cartService->shouldReceive('getItems')->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->andReturn(50.00);
        $this->mockSplittingService->shouldReceive('splitByMerchant')->andReturn($groups);
        $this->mockMerchantShippingService->shouldReceive('calculatePerGroup')->andReturn(['merchant_1' => 5.00]);
        $this->orderCalculationService->shouldReceive('calculateOrderTotals')->andReturn([
            'subtotal' => 50.00,
            'discount' => 10.00,
            'total' => 45.00
        ]);

        $this->setCurrencyExpectations();

        $this->mockAllocationService->shouldReceive('allocate')->andReturn([
            'merchant_1' => ['total' => 45.00, 'stripe_eligible' => true, 'discount' => 10.00]
        ]);
        $this->stripePaymentService->shouldReceive('createPaymentIntent')->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderService->shouldReceive('createMerchantOrder')->andReturn($mockOrder);
        $this->mockShipmentRepository->shouldReceive('create')->andReturn(m::mock(Shipment::class));
        $this->memberAuthWrapper->shouldReceive('check')->andReturn(false);

        // Mock voucher application
        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->with(1, null, 10.00, 1);

        $this->cartService->shouldReceive('clear')->once();
        $this->mockMerchantShippingService->shouldReceive('isConsolidationEnabled')->andReturn(true);

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function testProcessMultiMerchantCheckoutEmptyCart()
    {
        $siteId = 1;
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'country' => 'US'
        ];

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([]);

        $this->setRequiresShippingExpectation();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cart is empty', $result['message']);
    }

    public function testProcessMultiMerchantCheckoutWithEmptyCart()
    {
        $checkoutData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'country' => 'US'
        ];

        $this->cartService->shouldReceive('getItems')->once()->andReturn([]);

        $this->setRequiresShippingExpectation();

        $result = $this->service->processMultiMerchantCheckout($checkoutData, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cart is empty', $result['message']);
    }

    public function testProcessCheckoutDoesNotRequireAddressWhenShippingNotRequired(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'payment_method' => 'card'
            // NO ADDRESS FIELDS
        ];

        $cartItems = [
            ['product_id' => 1, 'price' => 50.00, 'quantity' => 1, 'subtotal' => 50.00]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setTaxCalculatorExpectations(5000, 0, 'GB', null);

        // requiresShipping returns FALSE
        $this->cartService->shouldReceive('requiresShipping')
            ->atLeast()->once()
            ->andReturn(false);

        $this->setTransactionExpectations();
        $this->setCurrencyExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);

        // Shipping should be 0 when not required
        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->andReturn(0.00);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 50.00, 'shipping' => 0.00, 'discount' => 0, 'tax' => 11])
            ->andReturn([
                'subtotal' => 50.00,
                'shipping' => 0.00,
                'discount' => 0,
                'tax' => 5.00,
                'total' => 55.00
            ]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'client_secret' => 'secret',
                'payment_intent_id' => 'pi_123'
            ]);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                // Verify NO shipping address fields in order data
                return !isset($orderData['shipping_address'])
                    && !isset($orderData['shipping_address_id'])
                    && $orderData['shipping'] === 0.00;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->memberAuthWrapper->shouldReceive('check')->twice()->andReturn(false);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutSkipsAddressValidationWhenShippingNotRequired(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            // Intentionally missing address, city, postal_code, country
        ];

        $cartItems = [
            ['product_id' => 1, 'price' => 50.00, 'quantity' => 1, 'subtotal' => 50.00]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setTaxCalculatorExpectations(5000, 0, 'GB', null);

        $this->cartService->shouldReceive('requiresShipping')
            ->atLeast()->once()
            ->andReturn(false);

        $this->setTransactionExpectations();
        $this->setCurrencyExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(0.00);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->andReturn(['subtotal' => 50.00, 'shipping' => 0.00, 'discount' => 0, 'tax' => 5.00, 'total' => 55.00]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn(['success' => true, 'client_secret' => 'secret', 'payment_intent_id' => 'pi_123']);

        $this->orderService->shouldReceive('create')->once()->andReturn($mockOrder);
        $this->memberAuthWrapper->shouldReceive('check')->twice()->andReturn(false);

        $result = $this->service->processCheckout($data, 1);

        // Should succeed even without address fields
        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutRequiresAddressWhenShippingRequired(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            // Missing address fields
        ];

        $this->cartService->shouldReceive('requiresShipping')
            ->atLeast()->once()
            ->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertEquals('Address is required', $result['message']);
    }

    public function testProcessCheckoutWithSavedAddressSkipsManualAddressValidation(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'saved_address' => 7,
            'postal_code' => '12345'
            // NO manual address fields - should be OK
        ];

        $cartItems = [
            ['product_id' => 1, 'price' => 50.00, 'quantity' => 1, 'subtotal' => 50.00]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->cartService->shouldReceive('requiresShipping')
            ->atLeast()->once()
            ->andReturn(true);

        $this->setTransactionExpectations();
        $this->setCurrencyExpectations();
        $this->setTaxCalculatorExpectations(5000, 1000, 'GB');

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->andReturn(['subtotal' => 50.00, 'shipping' => 10.00, 'discount' => 0, 'tax' => 6.00, 'total' => 66.00]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn(['success' => true, 'client_secret' => 'secret', 'payment_intent_id' => 'pi_123']);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                return $orderData['shipping_address_id'] === 7
                    && !isset($orderData['address'])
                    && !isset($orderData['city']);
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->memberAuthWrapper->shouldReceive('check')->twice()->andReturn(true);
        $this->memberAuthWrapper->shouldReceive('getMember')->twice()->andReturn($member);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutCalculatesShippingOnlyWhenRequired(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ];

        $cartItems = [
            ['product_id' => 1, 'price' => 50.00, 'quantity' => 1, 'subtotal' => 50.00]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setTaxCalculatorExpectations(5000, 0, 'GB', null);

        // requiresShipping is false
        $this->cartService->shouldReceive('requiresShipping')
            ->atLeast()->once()
            ->andReturn(false);

        $this->setTransactionExpectations();
        $this->setCurrencyExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(50.00);

        // Shipping service should still be called but should return 0
        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->with(50.00, $data)
            ->andReturn(0.00);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->with([], ['subtotal' => 50.00, 'shipping' => 0.00, 'discount' => 0, 'tax' => 11])
            ->andReturn(['subtotal' => 50.00, 'shipping' => 0.00, 'discount' => 0, 'tax' => 5.00, 'total' => 55.00]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true, 'client_secret' => 'secret', 'payment_intent_id' => 'pi_123'
        ]);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::on(function ($orderData) {
                return $orderData['shipping'] === 0.00;
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->memberAuthWrapper->shouldReceive('check')->twice()->andReturn(false);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutDigitalOnlyCartDoesNotRequireShipping(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            // No address fields
        ];

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Digital Magazine',
                'price' => 9.99,
                'quantity' => 1,
                'subtotal' => 9.99,
                'is_digital' => true
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setTaxCalculatorExpectations(999, 0, 'GB', null);

        $this->cartService->shouldReceive('requiresShipping')
            ->atLeast()->once()
            ->andReturn(false);

        $this->setTransactionExpectations();
        $this->setCurrencyExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(9.99);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(0.00);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->andReturn(['subtotal' => 9.99, 'shipping' => 0.00, 'discount' => 0, 'tax' => 1.00, 'total' => 10.99]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true, 'client_secret' => 'secret', 'payment_intent_id' => 'pi_123'
        ]);

        $this->orderService->shouldReceive('create')->once()->andReturn($mockOrder);
        $this->memberAuthWrapper->shouldReceive('check')->twice()->andReturn(false);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    private function setMemberAuthExpectations()
    {
        $this->memberAuthWrapper->shouldReceive('check')->once()->andReturn(false);
    }

    private function setCurrencyExpectations()
    {
        $this->currencyResolver->shouldReceive('resolve')
            ->once()
            ->with(1)
            ->andReturn('usd');
    }

    private function setTransactionExpectations()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });
    }

    private function setRequiresShippingExpectation()
    {
        $this->cartService->shouldReceive('requiresShipping')->atLeast()->once()->andReturn(true);
    }

    private function setTaxCalculatorExpectations(float $subtotal = 10000, float $shipping = 1000, string $country = 'US', $postalCode = '12345')
    {
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')
            ->once()
            ->with(
                $subtotal,
                $shipping,
                $country,
                NULL,
                $postalCode,
                \Mockery::any())
            ->andReturn(['tax_cents' => 1100]);
    }
}