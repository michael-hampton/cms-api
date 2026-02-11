<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\MemberReward;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\RewardDefinition;
use App\Models\Shipment;
use App\Repositories\Billing\ShipmentRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Rewards\RewardsRepository;
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
use App\Services\Vouchers\DiscountResolver;
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
    private MerchantRepository $merchantRepository;
    private DiscountResolver $discountResolver;
    private RewardsRepository $rewardsRepository;

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
        $this->merchantRepository = m::mock(MerchantRepository::class);
        $this->discountResolver = m::mock(DiscountResolver::class);
        $this->rewardsRepository = m::mock(RewardsRepository::class);

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
            $this->taxCalculatorService,
            $this->merchantRepository,
            $this->discountResolver,
            $this->rewardsRepository,
        );
    }

    public function test_process_checkout_successfully()
    {
        $payload = [
            'voucher_code' => null,
            'payment_method_id' => 'pm_123',
            'shipping_address_id' => 10,
        ];

        $member = (object)['id' => 1];
        $cart = collect([['product_id' => 5, 'price' => 10000, 'qty' => 1]]);
        $calculatedOrder = ['subtotal' => 10000, 'total' => 12000];
        $splitOrders = [['merchant_id' => 1]];
        $allocations = [['merchant_id' => 1, 'amount' => 12000]];
        $stripeIntent = ['id' => 'pi_123', 'client_secret' => 'secret_123'];
        $createdOrder = (object)['id' => 999];

        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->with(m::type(\Closure::class))
            ->andReturnUsing(fn($callback) => $callback());

        $this->memberAuthWrapper
            ->shouldReceive('getMember')
            ->once()
            ->andReturn($member);

        $this->cartService
            ->shouldReceive('getCartForCheckout')
            ->once()
            ->with($member->id)
            ->andReturn($cart);

        $this->discountResolver
            ->shouldReceive('resolve')
            ->once()
            ->andReturn([]);

        $this->taxCalculatorService
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($calculatedOrder);

        $this->orderCalculationService
            ->shouldReceive('calculate')
            ->once()
            ->andReturn($calculatedOrder);

        $this->mockSplittingService
            ->shouldReceive('split')
            ->once()
            ->andReturn($splitOrders);

        $this->mockAllocationService
            ->shouldReceive('allocate')
            ->once()
            ->andReturn($allocations);

        $this->stripePaymentService
            ->shouldReceive('createPaymentIntent')
            ->once()
            ->with(m::on(fn($data) => $data['amount'] === 12000))
            ->andReturn($stripeIntent);

        $this->orderService
            ->shouldReceive('create')
            ->once()
            ->andReturn($createdOrder);

        $result = $this->service->processCheckout($payload);

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_123', $result['payment_intent_id']);
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
            ->twice()
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

        $this->setTransactionExpectations();

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

        $this->currencyResolver->shouldReceive('resolve')
            ->twice()
            ->with(1)
            ->andReturn('usd');

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
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
                return
                    $orderData['subtotal'] == 100
                    && $orderData['shipping'] == 10
                    && $orderData['tax'] == 11
                    && $orderData['total'] == 121
                    && $orderData['discount'] == 0;
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
                return $orderData['subtotal'] == 150
                    && $orderData['shipping'] == 0
                    && $orderData['tax'] == 11
                    && $orderData['total'] == 165;
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
                'subtotal' => 100.00,
                'id' => 1
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setRequiresShippingExpectation();
        $this->setTransactionExpectations();
        $this->setTaxCalculatorExpectations();

        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->with('SAVE10', $cartItems, null)
            ->andReturn([
                'valid' => true,
                'voucher_code' => 'SAVE10',
                'discount' => 10.00,
                'eligible_items' => [
                    [
                        'id' => 1,
                        'subtotal' => 100.00
                    ]
                ],
                'voucher_id' => 5,
            ]);

//        $this->voucherService->shouldReceive('getVoucherById')
//            ->once()
//            ->with(5);

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
                    && $orderData['tax'] == 11
                    && $orderData['total'] == 110
                    && $orderData['voucher_code'] == 'SAVE10';
            }), m::any(), 1)
            ->andReturn($mockOrder);

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->with(5, m::any(), 10.00, 1);

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
                return $orderData['tax'] === 11;
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
                'subtotal' => 50.00,
                'id' => 1
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

        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->with('INVALID', $cartItems, null)
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

        $this->currencyResolver->shouldReceive('resolve')
            ->times(3)
            ->andReturn('USD');
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
        $this->stripePaymentService->shouldReceive('createPaymentIntentWithCustomer')
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

        $this->memberAuthWrapper->shouldReceive('check');
        $this->memberAuthWrapper->shouldReceive('getMember')->andReturn(m::mock(Member::class));

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

        $this->currencyResolver->shouldReceive('resolve')
            ->once()
            ->andReturn('USD');
        $this->setRequiresShippingExpectation();

        $this->stripePaymentService->shouldReceive('createPaymentIntentWithCustomer')
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
        $this->stripePaymentService->shouldReceive('createPaymentIntentWithCustomer')->andReturn([
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
            ->twice()
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

    public function testCalculateTotalsWithStackableVoucher()
    {
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'base_price' => 100.00,
                'price' => 80.00, // £20 offer discount
                'quantity' => 1,
                'subtotal' => 80.00,
                'item_type' => 'offer'
            ]
        ];

        $voucherData = [
            'valid' => true,
            'discount' => 8.00, // 10% of £80
            'voucher_code' => 'STACK10',
            'voucher_id' => 1,
            'is_stackable' => true,
            'eligible_items' => $cartItems,
            'requires_override_decision' => false
        ];

        $totals = $this->invokePrivateMethod(
            $this->service,
            'calculateTotals',
            [$cartItems, $voucherData]
        );

        $this->assertEquals(100.00, $totals['base_subtotal']);
        $this->assertEquals(80.00, $totals['subtotal']);
        $this->assertEquals(20.00, $totals['offer_discount']);
        $this->assertEquals(8.00, $totals['voucher_discount']);
    }

    public function testCalculateTotalsWithNonStackableVoucherThatWins()
    {
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'base_price' => 200.00,
                'price' => 160.00, // £40 offer discount
                'quantity' => 1,
                'subtotal' => 160.00,
                'item_type' => 'offer'
            ]
        ];

        $voucherData = [
            'valid' => true,
            'discount' => 50.00, // £50 voucher discount
            'voucher_code' => 'BIG50',
            'voucher_id' => 2,
            'is_stackable' => false,
            'eligible_items' => $cartItems,
            'requires_override_decision' => true
        ];

        $totals = $this->invokePrivateMethod(
            $this->service,
            'calculateTotals',
            [$cartItems, $voucherData]
        );

        $this->assertEquals(200.00, $totals['base_subtotal']);
        $this->assertEquals(0.00, $totals['offer_discount']); // Offer removed for eligible items
        $this->assertEquals(50.00, $totals['voucher_discount']); // Voucher wins
    }

    public function testCalculateTotalsWithNonStackableVoucherThatLoses()
    {
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'base_price' => 200.00,
                'price' => 140.00, // £60 offer discount
                'quantity' => 1,
                'subtotal' => 140.00,
                'item_type' => 'offer'
            ]
        ];

        $voucherData = [
            'valid' => true,
            'discount' => 35.00, // £35 voucher discount (less than £60 offer)
            'voucher_code' => 'SMALL35',
            'voucher_id' => 3,
            'is_stackable' => false,
            'eligible_items' => $cartItems,
            'requires_override_decision' => true
        ];

        $totals = $this->invokePrivateMethod(
            $this->service,
            'calculateTotals',
            [$cartItems, $voucherData]
        );

        $this->assertEquals(200.00, $totals['base_subtotal']);
        $this->assertEquals(140.00, $totals['subtotal']);
        $this->assertEquals(60.00, $totals['offer_discount']); // Offer wins
        $this->assertEquals(0.00, $totals['voucher_discount']); // Voucher not applied
        $this->assertNull($totals['voucher_code']); // Voucher data cleared
    }

    public function testCalculateTotalsOnlyRemovesOfferForEligibleItems()
    {
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'base_price' => 100.00,
                'price' => 80.00, // £20 offer - eligible
                'quantity' => 1,
                'subtotal' => 80.00,
                'item_type' => 'offer'
            ],
            [
                'id' => 2,
                'product_id' => 2,
                'base_price' => 50.00,
                'price' => 40.00, // £10 offer - NOT eligible
                'quantity' => 1,
                'subtotal' => 40.00,
                'item_type' => 'offer'
            ]
        ];

        $voucherData = [
            'valid' => true,
            'discount' => 25.00,
            'voucher_code' => 'PARTIAL25',
            'voucher_id' => 4,
            'is_stackable' => false,
            'eligible_items' => [$cartItems[0]], // Only first item eligible
            'requires_override_decision' => true
        ];

        $totals = $this->invokePrivateMethod(
            $this->service,
            'calculateTotals',
            [$cartItems, $voucherData]
        );

        // Voucher £25 > eligible offer £20, so voucher wins for item 1
        // Item 2's £10 offer should remain
        $this->assertEquals(150.00, $totals['base_subtotal']);
        $this->assertEquals(10.00, $totals['offer_discount']); // Only item 2's offer remains
        $this->assertEquals(25.00, $totals['voucher_discount']);
    }

    public function testPrepareOrderItemsDistributesVoucherProportionally()
    {
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product A',
                'product_sku' => 'SKU-A',
                'price' => 60.00,
                'base_price' => 60.00,
                'quantity' => 1,
                'subtotal' => 60.00
            ],
            [
                'id' => 2,
                'product_id' => 2,
                'product_name' => 'Product B',
                'product_sku' => 'SKU-B',
                'price' => 40.00,
                'base_price' => 40.00,
                'quantity' => 1,
                'subtotal' => 40.00
            ]
        ];

        $totals = [
            'voucher_discount' => 10.00,
            'voucher_eligible_items' => $cartItems,
            'offer_discount' => 0
        ];

        $orderItems = $this->invokePrivateMethod(
            $this->service,
            'prepareOrderItemsWithDiscounts',
            [$cartItems, $totals]
        );

        $this->assertCount(2, $orderItems);

        $item1Metadata = json_decode($orderItems[0]['metadata'], true);
        $item2Metadata = json_decode($orderItems[1]['metadata'], true);

        // Item 1: 60/(60+40) * 10 = 6.00
        $this->assertEquals(6.00, $item1Metadata['voucher_discount']);
        $this->assertEquals(54.00, $orderItems[0]['total']);

        // Item 2: 40/(60+40) * 10 = 4.00
        $this->assertEquals(4.00, $item2Metadata['voucher_discount']);
        $this->assertEquals(36.00, $orderItems[1]['total']);
    }

    public function testPrepareOrderItemsHandlesRoundingOnLastItem()
    {
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product A',
                'product_sku' => 'SKU-A',
                'price' => 33.33,
                'quantity' => 1,
                'subtotal' => 33.33
            ],
            [
                'id' => 2,
                'product_id' => 2,
                'product_name' => 'Product B',
                'product_sku' => 'SKU-B',
                'price' => 33.33,
                'quantity' => 1,
                'subtotal' => 33.33
            ],
            [
                'id' => 3,
                'product_id' => 3,
                'product_name' => 'Product C',
                'product_sku' => 'SKU-C',
                'price' => 33.34,
                'quantity' => 1,
                'subtotal' => 33.34
            ]
        ];

        $totals = [
            'voucher_discount' => 10.00,
            'voucher_eligible_items' => $cartItems,
            'offer_discount' => 0
        ];

        $orderItems = $this->invokePrivateMethod(
            $this->service,
            'prepareOrderItemsWithDiscounts',
            [$cartItems, $totals]
        );

        $totalVoucherDistributed = 0;
        foreach ($orderItems as $item) {
            $metadata = json_decode($item['metadata'], true);
            $totalVoucherDistributed += $metadata['voucher_discount'];
        }

        // Total should equal exactly 10.00 with no rounding errors
        $this->assertEquals(10.00, $totalVoucherDistributed);
    }

    public function testProcessCheckoutIgnoresClientProvidedVoucherId()
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
            'voucher_code' => 'REAL10',
            'voucher_id' => 999 // Client tries to provide fake ID
        ];

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'price' => 100.00,
                'base_price' => 100.00,
                'quantity' => 1,
                'subtotal' => 100.00
            ]
        ];

        $this->setRequiresShippingExpectation();
        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);

        // VoucherService should be called with code only, returns actual voucher ID
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->with('REAL10', $cartItems, null)
            ->andReturn([
                'valid' => true,
                'discount' => 10.00,
                'voucher_id' => 1, // Actual voucher ID
                'voucher_code' => 'REAL10',
                'is_stackable' => true,
                'eligible_items' => $cartItems,
                'requires_override_decision' => false
            ]);

        // Rest of mocks...
        $this->setTransactionExpectations();
        $this->setCurrencyExpectations();
        $this->setTaxCalculatorExpectations();
        $this->cartService->shouldReceive('getTotal')->andReturn(100.00);
        $this->shippingService->shouldReceive('calculateShipping')->andReturn(10.00);
        $this->orderCalculationService->shouldReceive('calculateOrderTotals')->andReturn([
            'subtotal' => 100.00,
            'shipping' => 10.00,
            'discount' => 10.00,
            'tax' => 10.00,
            'total' => 130.00
        ]);
        $this->stripePaymentService->shouldReceive('createPaymentIntent')->andReturn([
            'success' => true,
            'client_secret' => 'secret',
            'payment_intent_id' => 'pi_123'
        ]);

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->orderService->shouldReceive('create')->once()->andReturn($mockOrder);

        // Verify applyVoucher is called with ACTUAL voucher ID, not client-provided
        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->with(1, null, 10.00, 1); // ID should be 1, not 999

        $this->memberAuthWrapper->shouldReceive('check')->andReturn(false);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testPrepareOrderItemsOnlyDistributesVoucherToEligibleItems()
    {
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Eligible Product',
                'product_sku' => 'SKU-1',
                'price' => 100.00,
                'quantity' => 1,
                'subtotal' => 100.00
            ],
            [
                'id' => 2,
                'product_id' => 2,
                'product_name' => 'Ineligible Product',
                'product_sku' => 'SKU-2',
                'price' => 100.00,
                'quantity' => 1,
                'subtotal' => 100.00
            ]
        ];

        $totals = [
            'voucher_discount' => 20.00,
            'voucher_eligible_items' => [$cartItems[0]], // Only first item
            'offer_discount' => 0
        ];

        $orderItems = $this->invokePrivateMethod(
            $this->service,
            'prepareOrderItemsWithDiscounts',
            [$cartItems, $totals]
        );

        $item1Metadata = json_decode($orderItems[0]['metadata'], true);
        $item2Metadata = json_decode($orderItems[1]['metadata'], true);

        // Item 1 gets full voucher discount
        $this->assertEquals(20.00, $item1Metadata['voucher_discount']);
        $this->assertEquals(80.00, $orderItems[0]['total']);

        // Item 2 gets no voucher discount
        $this->assertEquals(0.00, $item2Metadata['voucher_discount']);
        $this->assertEquals(100.00, $orderItems[1]['total']);
    }

    public function testProcessCheckoutIncludesDiscountBreakdownInResponse()
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
            'voucher_code' => 'TEST10'
        ];

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'base_price' => 100.00,
                'price' => 90.00, // £10 offer
                'quantity' => 1,
                'subtotal' => 90.00,
                'item_type' => 'offer'
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setRequiresShippingExpectation();
        $this->setTransactionExpectations();
        $this->setCurrencyExpectations();
        $this->setTaxCalculatorExpectations();

        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->once()->andReturn(90.00);

        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn([
                'valid' => true,
                'discount' => 9.00,
                'voucher_id' => 1,
                'voucher_code' => 'TEST10',
                'is_stackable' => true,
                'eligible_items' => $cartItems,
                'requires_override_decision' => false
            ]);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);

        $this->orderCalculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->andReturn([
                'subtotal' => 90.00,
                'shipping' => 10.00,
                'discount' => 19.00, // 10 offer + 9 voucher
                'tax' => 10.00,
                'total' => 91.00
            ]);

        $this->stripePaymentService->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'client_secret' => 'secret',
            'payment_intent_id' => 'pi_123'
        ]);

        $this->orderService->shouldReceive('create')->once()->andReturn($mockOrder);
        $this->voucherService->shouldReceive('applyVoucher')->once();
        $this->memberAuthWrapper->shouldReceive('check')->andReturn(false);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('discount_breakdown', $result);
        $this->assertEquals(10.00, $result['discount_breakdown']['offer_discount']);
        $this->assertEquals(9.00, $result['discount_breakdown']['voucher_discount']);
        $this->assertEquals(19.00, $result['discount_breakdown']['total_discount']);
    }

    public function testProcessCheckoutWithMerchantFundedVoucher()
    {
        $data = $this->getBaseCheckoutData();
        $data['voucher_code'] = 'MERCHANT10';

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 100.00,
                'base_price' => 100.00,
                'quantity' => 1,
                'subtotal' => 100.00
            ]
        ];

        $merchant = m::mock(Merchant::class)->makePartial();
        $merchant->id = 5;
        $merchant->balance = 1000.00;

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        // Setup mocks
        $this->setupBasicMocks($cartItems, $mockOrder);

        // Voucher validation returns merchant_id
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->with('MERCHANT10', $cartItems, null)
            ->andReturn([
                'valid' => true,
                'discount' => 10.00,
                'voucher_id' => 1,
                'voucher_code' => 'MERCHANT10',
                'merchant_id' => 5, // Merchant-funded voucher
                'campaign_id' => null,
                'is_stackable' => true,
                'eligible_items' => $cartItems,
                'eligible_subtotal' => 100.00,
                'has_offer_discount' => false,
                'requires_override_decision' => false,
                'message' => 'Voucher validated successfully'
            ]);

        // Merchant funding expectations
        $this->merchantRepository->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn($merchant);

        $this->merchantRepository->shouldReceive('updateBalance')
            ->once()
            ->with(5, 990.00)
            ->andReturn(true);

        $this->merchantRepository->shouldReceive('createTransaction')
            ->once()
            ->with(m::on(function ($data) {
                return $data['merchant_id'] === 5
                    && $data['order_id'] === 1
                    && $data['voucher_id'] === 1
                    && $data['type'] === 'voucher_funding'
                    && $data['amount'] === -10.00
                    && $data['balance_before'] === 1000.00
                    && $data['balance_after'] === 990.00
                    && $data['status'] === 'completed';
            }))
            ->andReturn(m::mock(\App\Models\MerchantTransaction::class));

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->with(1, null, 10.00, 1)
            ->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals(10.00, $result['discount_breakdown']['voucher_discount']);
    }

    public function testProcessCheckoutWithInsufficientMerchantBalance()
    {
        $data = $this->getBaseCheckoutData();
        $data['voucher_code'] = 'MERCHANT50';

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 100.00,
                'base_price' => 100.00,
                'quantity' => 1,
                'subtotal' => 100.00
            ]
        ];

        $merchant = m::mock(Merchant::class)->makePartial();
        $merchant->id = 5;
        $merchant->balance = 10.00; // Insufficient for £50 discount

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setupBasicMocks($cartItems, $mockOrder);

        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn([
                'valid' => true,
                'discount' => 50.00,
                'voucher_id' => 1,
                'voucher_code' => 'MERCHANT50',
                'merchant_id' => 5,
                'campaign_id' => null,
                'is_stackable' => true,
                'eligible_items' => $cartItems,
                'eligible_subtotal' => 100.00,
                'has_offer_discount' => false,
                'requires_override_decision' => false,
                'message' => 'Voucher validated successfully'
            ]);

        // Merchant funding with insufficient balance
        $this->merchantRepository->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn($merchant);

        // Should NOT update balance when insufficient
        $this->merchantRepository->shouldNotReceive('updateBalance');

        // Should create pending_review transaction
        $this->merchantRepository->shouldReceive('createTransaction')
            ->once()
            ->with(m::on(function ($data) {
                $metadata = json_decode($data['metadata'], true);
                return $data['merchant_id'] === 5
                    && $data['status'] === 'pending_review'
                    && $data['balance_after'] == 10 // Balance unchanged
                    && isset($metadata['shortfall']);
            }))
            ->andReturn(m::mock(\App\Models\MerchantTransaction::class));

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        // Order should still succeed
        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutWithMerchantFundingException()
    {
        $data = $this->getBaseCheckoutData();
        $data['voucher_code'] = 'MERCHANT10';

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 100.00,
                'base_price' => 100.00,
                'quantity' => 1,
                'subtotal' => 100.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setupBasicMocks($cartItems, $mockOrder);

        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn([
                'valid' => true,
                'discount' => 10.00,
                'voucher_id' => 1,
                'voucher_code' => 'MERCHANT10',
                'merchant_id' => 5,
                'campaign_id' => null,
                'is_stackable' => true,
                'eligible_items' => $cartItems,
                'eligible_subtotal' => 100.00,
                'has_offer_discount' => false,
                'requires_override_decision' => false,
                'message' => 'Voucher validated successfully'
            ]);

        // Merchant not found - should throw exception
        $this->merchantRepository->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn(null);

        // Should create failed transaction
        $this->merchantRepository->shouldReceive('createTransaction')
            ->once()
            ->with(m::on(function ($data) {
                $metadata = json_decode($data['metadata'], true);
                return $data['status'] === 'failed'
                    && isset($metadata['error']);
            }))
            ->andReturn(m::mock(\App\Models\MerchantTransaction::class));

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        // Order should still succeed even if merchant funding fails
        $this->assertTrue($result['success']);
    }

    public function testProcessCheckoutWithoutMerchantFunding()
    {
        $data = $this->getBaseCheckoutData();
        $data['voucher_code'] = 'PLATFORM10';

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 100.00,
                'base_price' => 100.00,
                'quantity' => 1,
                'subtotal' => 100.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setupBasicMocks($cartItems, $mockOrder);

        // Platform-funded voucher (no merchant_id)
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn([
                'valid' => true,
                'discount' => 10.00,
                'voucher_id' => 1,
                'voucher_code' => 'PLATFORM10',
                'merchant_id' => null, // Platform-funded
                'campaign_id' => null,
                'is_stackable' => true,
                'eligible_items' => $cartItems,
                'eligible_subtotal' => 100.00,
                'has_offer_discount' => false,
                'requires_override_decision' => false,
                'message' => 'Voucher validated successfully'
            ]);

        // Should NOT attempt merchant funding
        $this->merchantRepository->shouldNotReceive('find');
        $this->merchantRepository->shouldNotReceive('updateBalance');
        $this->merchantRepository->shouldNotReceive('createTransaction');

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    // Add these new tests to CheckoutServiceTest.php

    public function testProcessCheckoutWithRewardDiscount()
    {
        $data = $this->getBaseCheckoutData();
        $data['reward_id'] = 1;

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 100.00,
                'base_price' => 100.00,
                'quantity' => 1,
                'subtotal' => 100.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        // Mock reward repository
        $mockReward = m::mock(MemberReward::class)->makePartial();
        $mockReward->id = 1;
        $mockReward->member_id = 10;
        $mockReward->status = 'pending';

        $mockDefinition = m::mock(RewardDefinition::class)->makePartial();
        $mockDefinition->reward_type = 'percentage_discount';
        $mockDefinition->reward_config = ['percentage' => 10];

        $mockReward->shouldReceive('rewardDefinition')->andReturn($mockDefinition);
        $mockReward->shouldReceive('isPending')->andReturn(true);
        $mockReward->shouldReceive('isExpired')->andReturn(false);
        $mockReward->shouldReceive('claim')->once()->andReturn(true);
        $mockReward->shouldReceive('update')->once();

        $this->rewardsRepository->shouldReceive('find')->with(1)->andReturn($mockReward);

        $this->setupBasicMocks($cartItems, $mockOrder);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals(10.00, $result['discount_breakdown']['reward_discount']);
    }

    public function testProcessCheckoutWithStackableVoucherAndOffer()
    {
        $data = $this->getBaseCheckoutData();
        $data['voucher_code'] = 'SAVE10';

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 80.00,         // Offer price
                'base_price' => 100.00,   // Original price
                'quantity' => 1,
                'subtotal' => 80.00,
                'item_type' => 'offer'    // Has offer discount
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setupBasicMocks($cartItems, $mockOrder);

        // Voucher is stackable
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn([
                'valid' => true,
                'discount' => 8.00,        // 10% of £80
                'voucher_id' => 1,
                'voucher_code' => 'SAVE10',
                'merchant_id' => null,
                'campaign_id' => null,
                'is_stackable' => true,    // Stackable!
                'eligible_items' => $cartItems,
                'eligible_subtotal' => 80.00,
                'has_offer_discount' => true,
                'requires_override_decision' => false,
                'discount_type' => 'percentage',
                'message' => 'Voucher validated successfully'
            ]);

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);

        // Should have BOTH discounts
        $this->assertEquals(20.00, $result['discount_breakdown']['offer_discount']);
        $this->assertEquals(8.00, $result['discount_breakdown']['voucher_discount']);
        $this->assertEquals(28.00, $result['discount_breakdown']['total_discount']);
    }

    public function testProcessCheckoutWithNonStackableVoucherOverridesOffer()
    {
        $data = $this->getBaseCheckoutData();
        $data['voucher_code'] = 'MEGA50';

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 80.00,         // Offer price (£20 off)
                'base_price' => 100.00,   // Original price
                'quantity' => 1,
                'subtotal' => 80.00,
                'item_type' => 'offer'
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setupBasicMocks($cartItems, $mockOrder);

        // Non-stackable voucher with bigger discount
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn([
                'valid' => true,
                'discount' => 50.00,       // 50% of £100 (calculated from base)
                'voucher_id' => 1,
                'voucher_code' => 'MEGA50',
                'merchant_id' => null,
                'campaign_id' => null,
                'is_stackable' => false,   // NOT stackable!
                'eligible_items' => $cartItems,
                'eligible_subtotal' => 100.00, // Based on base price
                'has_offer_discount' => true,
                'requires_override_decision' => true,
                'discount_type' => 'percentage',
                'message' => 'Voucher validated successfully'
            ]);

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);

        // Non-stackable voucher should override offer
        $this->assertEquals(0.00, $result['discount_breakdown']['offer_discount']);
        $this->assertEquals(50.00, $result['discount_breakdown']['voucher_discount']);
        $this->assertEquals(50.00, $result['discount_breakdown']['total_discount']);
    }

    public function testProcessCheckoutDistributesDiscountProportionally()
    {
        $data = $this->getBaseCheckoutData();
        $data['voucher_code'] = 'SAVE20';

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 60.00,
                'base_price' => 60.00,
                'quantity' => 1,
                'subtotal' => 60.00
            ],
            [
                'id' => 2,
                'product_id' => 2,
                'product_name' => 'Product 2',
                'price' => 40.00,
                'base_price' => 40.00,
                'quantity' => 1,
                'subtotal' => 40.00
            ]
        ];

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;
        $mockOrder->order_number = 'ORD-123';

        $this->setupBasicMocks($cartItems, $mockOrder);

        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn([
                'valid' => true,
                'discount' => 20.00,
                'voucher_id' => 1,
                'voucher_code' => 'SAVE20',
                'merchant_id' => null,
                'campaign_id' => null,
                'is_stackable' => true,
                'eligible_items' => $cartItems,
                'eligible_subtotal' => 100.00,
                'has_offer_discount' => false,
                'requires_override_decision' => false,
                'discount_type' => 'percentage',
                'message' => 'Voucher validated successfully'
            ]);

        $this->orderService->shouldReceive('create')
            ->once()
            ->with(m::any(), m::on(function ($items) {
                // Item 1: 60% of total, so 60% of £20 = £12 discount
                // Item 2: 40% of total, so 40% of £20 = £8 discount
                $this->assertCount(2, $items);
                $this->assertEquals(12.00, $items[0]['discount']);
                $this->assertEquals(8.00, $items[1]['discount']);
                return true;
            }), m::any())
            ->andReturn($mockOrder);

        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertTrue($result['success']);
    }

    public function testResolveDiscountsWithAllTypes()
    {
        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $cartItems = [
            [
                'id' => 1,
                'product_id' => 1,
                'product_name' => 'Product 1',
                'price' => 80.00,        // Has £20 offer discount
                'base_price' => 100.00,
                'quantity' => 1,
                'item_type' => 'offer'
            ]
        ];

        $voucherData = [
            'valid' => true,
            'discount' => 8.00,  // 10% of £80
            'voucher_id' => 1,
            'voucher_code' => 'SAVE10',
            'is_stackable' => true,
            'eligible_items' => $cartItems,
            'discount_type' => 'percentage'
        ];

        // Mock reward
        $mockReward = m::mock(MemberReward::class)->makePartial();
        $mockReward->member_id = 10;
        $mockReward->shouldReceive('isPending')->andReturn(true);
        $mockReward->shouldReceive('isExpired')->andReturn(false);

        $mockDefinition = m::mock(RewardDefinition::class)->makePartial();
        $mockDefinition->reward_type = 'fixed_discount';
        $mockDefinition->reward_config = ['amount' => 5.00];

        $mockReward->shouldReceive('rewardDefinition')->andReturn($mockDefinition);
        $this->rewardsRepository->shouldReceive('find')->with(1)->andReturn($mockReward);

        // Use reflection to test private method
        $discounts = $this->invokePrivateMethod(
            $this->service,
            'resolveDiscounts',
            [$cartItems, $member, $voucherData, ['reward_id' => 1], 1]
        );

        // Should have all three discount types
        $this->assertEquals(2000, $discounts->offerDiscountCents);      // £20 offer
        $this->assertEquals(800, $discounts->voucherDiscountCents);     // £8 voucher
        $this->assertEquals(500, $discounts->rewardDiscountCents);      // £5 reward
        $this->assertEquals(3300, $discounts->getTotalDiscountCents()); // £33 total
    }

    private function getBaseCheckoutData(): array
    {
        return [
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
    }

    private function setupBasicMocks($cartItems, $mockOrder): void
    {
        $this->memberAuthWrapper->shouldReceive('getMember')->andReturn(\Mockery::mock(Member::class)->makePartial());
        $this->memberAuthWrapper->shouldReceive('check')->andReturn(true);
        $this->currencyResolver->shouldReceive('resolve')->andReturn('USD');
        $this->shippingService->shouldReceive('calculateShipping')->andReturn(0);
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->andReturn(['tax_cents' => 0]);
        $this->orderCalculationService->shouldReceive('calculateOrderTotals')->andReturn([]);
        $this->stripePaymentService->shouldReceive('createPaymentIntent')->andReturn(['success' => true]);
        $this->cartService->shouldReceive('requiresShipping')->andReturn(true);
        $this->cartService->shouldReceive('getItems')->andReturn($cartItems);
        $this->cartService->shouldReceive('getTotal')->andReturn(100.00);

        $this->databaseMock->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderService->shouldReceive('create')->andReturn($mockOrder);
    }
}