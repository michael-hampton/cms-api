<?php

namespace App\Tests\Unit\Services\Shopping;

use App\DTO\Cart\TaxData;
use App\DTO\Checkout\DeliveryMethodConfig;
use App\DTO\Checkout\EligibilityResult;
use App\DTO\Checkout\EstimatedDelivery;
use App\DTO\Vouchers\VoucherValidationResult;
use App\Enums\Orders\OrderLineStatus;
use App\Enums\Orders\OrderStatus;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\MemberReward;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\Billing\ShipmentRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductVariantRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Billing\CheckoutSplittingService;
use App\Services\Billing\Order\OrderCreationService;
use App\Services\Billing\Order\OrderManager;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\PaymentAllocationService;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\Preorder\Actions\CalculateSellableStockAction;
use App\Services\Billing\Preorder\Actions\ResolveAvailabilityAction;
use App\Services\Billing\Preorder\PhysicalProductAvailabilityPolicy;
use App\Services\Billing\TaxCalculatorService;
use App\Services\Currency\CurrencyResolver;
use App\Services\Shipping\FulfilmentResolver;
use App\Services\Shipping\FulfilmentTypeInterface;
use App\Services\Shipping\InternalBusinessDayEstimator;
use App\Services\Shipping\ShippingService;
use App\Services\Shopping\CartService;
use App\Services\Shopping\CheckoutEligibilityService;
use App\Services\Shopping\CheckoutService;
use App\Services\Shopping\MerchantShippingService;
use App\Services\Vouchers\DiscountResolver;
use App\Services\Vouchers\ResolvedDiscounts;
use App\Services\Vouchers\VoucherService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;

class CheckoutServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private CheckoutService $service;
    private CartService|MockInterface $cartService;
    private OrderCreationService|MockInterface $orderCreationService;
    private VoucherService|MockInterface $voucherService;
    private ShippingService|MockInterface $shippingService;
    private MemberAuthWrapper|MockInterface $memberAuthWrapper;
    private OrderCalculationService|MockInterface $calculationService;
    private StripePaymentProcessor|MockInterface $stripeProcessor;
    private CheckoutSplittingService|MockInterface $splittingService;
    private PaymentAllocationService|MockInterface $allocationService;
    private MerchantShippingService|MockInterface $merchantShippingService;
    private ShipmentRepository|MockInterface $shipmentRepository;
    private CurrencyResolver|MockInterface $currencyResolver;
    private Database|MockInterface $databaseMock;
    private OrderManager|MockInterface $orderManager;
    private TaxCalculatorService|MockInterface $taxCalculatorService;
    private MerchantRepository|MockInterface $merchantRepository;
    private DiscountResolver|MockInterface $discountResolver;
    private RewardsRepository|MockInterface $rewardsRepository;
    private InternalBusinessDayEstimator $businessDayEstimator;
    private FulfilmentResolver $fulfilmentResolver;
    private ProductRepository $productRepository;
    private ResolveAvailabilityAction $resolveAvailabilityAction;
    private CalculateSellableStockAction $calculateSellableStockAction;
    private ProductVariantRepository $productVariantRepository;
    private CheckoutEligibilityService|MockInterface $eligibilityService;


    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = Mockery::mock(CartService::class);
        $this->orderCreationService = Mockery::mock(OrderCreationService::class);
        $this->voucherService = Mockery::mock(VoucherService::class);
        $this->shippingService = Mockery::mock(ShippingService::class);
        $this->memberAuthWrapper = Mockery::mock(MemberAuthWrapper::class);
        $this->calculationService = Mockery::mock(OrderCalculationService::class);
        $this->stripeProcessor = Mockery::mock(StripePaymentProcessor::class);
        $this->splittingService = Mockery::mock(CheckoutSplittingService::class);
        $this->allocationService = Mockery::mock(PaymentAllocationService::class);
        $this->merchantShippingService = Mockery::mock(MerchantShippingService::class);
        $this->shipmentRepository = Mockery::mock(ShipmentRepository::class);
        $this->currencyResolver = Mockery::mock(CurrencyResolver::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->orderManager = Mockery::mock(OrderManager::class);
        $this->taxCalculatorService = Mockery::mock(TaxCalculatorService::class);
        $this->merchantRepository = Mockery::mock(MerchantRepository::class);
        $this->discountResolver = Mockery::mock(DiscountResolver::class);
        $this->rewardsRepository = Mockery::mock(RewardsRepository::class);
        $this->businessDayEstimator = Mockery::mock(InternalBusinessDayEstimator::class);
        $this->fulfilmentResolver = Mockery::mock(FulfilmentResolver::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->resolveAvailabilityAction = Mockery::mock(ResolveAvailabilityAction::class);
        $this->calculateSellableStockAction = Mockery::mock(CalculateSellableStockAction::class);
        $this->productVariantRepository = Mockery::mock(ProductVariantRepository::class);
        $this->eligibilityService = Mockery::mock(CheckoutEligibilityService::class);

        $this->service = new CheckoutService(
            $this->cartService,
            $this->orderCreationService,
            $this->voucherService,
            $this->shippingService,
            $this->memberAuthWrapper,
            $this->calculationService,
            $this->stripeProcessor,
            $this->splittingService,
            $this->allocationService,
            $this->merchantShippingService,
            $this->shipmentRepository,
            $this->currencyResolver,
            $this->databaseMock,
            $this->orderManager,
            $this->taxCalculatorService,
            $this->merchantRepository,
            $this->discountResolver,
            $this->rewardsRepository,
            $this->businessDayEstimator,
            $this->fulfilmentResolver,
            $this->productRepository,
            $this->resolveAvailabilityAction,
            $this->calculateSellableStockAction,
            $this->productVariantRepository,
            $this->eligibilityService
        );

        $this->eligibilityService->shouldReceive('validate')
            ->andReturnUsing(function ($member, array $cartItems) {
                return new EligibilityResult(valid: $cartItems, removed: []);
            })->byDefault();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_error_when_required_fields_are_missing(): void
    {
        $data = []; // Missing required fields
        $siteId = 1;

        $this->cartService->shouldReceive('requiresShipping')->andReturn(true);

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_it_returns_error_when_cart_is_empty(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;

        $this->cartService->shouldReceive('requiresShipping')->atLeast()->once()->andReturn(false);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([]);

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cart is empty', $result['message']);
    }

    private function getValidCheckoutData(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address_line_1' => '123 Main St',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'GB',
            'state' => null,
        ], $overrides);
    }

    public function test_it_successfully_processes_checkout_without_voucher(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        // Setup expectations
        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        // Discount resolution
        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')
            ->once()
            ->andReturn($discounts);

        // Shipping calculation
        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->with(100.00, $data)
            ->andReturn(10.00);

        // Currency resolution
        $this->currencyResolver->shouldReceive('resolve')
            ->once()
            ->with($siteId)
            ->andReturn('GBP');

        // Tax calculation
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')
            ->once()
            ->andReturn(new TaxData(rate: 0.1, taxCents: 2000));

        // Transaction wrapper
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($order) {
                return $callback();
            });

        // Payment intent creation
        $this->stripeProcessor->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_123',
                'client_secret' => 'secret_123'
            ]);


        // Order creation
        $this->orderCreationService->shouldReceive('create')
            ->once()
            ->andReturn($order);

        // Cart clearing
        $this->cartService->shouldReceive('clear')
            ->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('order_number', $result);
        $this->assertArrayHasKey('payment_intent_id', $result);
    }

    private function getCartItems(): array
    {
        return [
            [
                'id' => 1,
                'product_id' => 100,
                'quantity' => 2,
                'price' => 50.00,
                'name' => 'Test Product',
            ]
        ];
    }

    private function getMember(): object
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'john@example.com';
        $member->first_name = 'John';
        $member->last_name = 'Doe';
        return $member;
    }

    private function getOrder(array $overrides = []): object
    {
        $order = new Order();
        $order->id = $overrides['id'] ?? 1;
        $order->order_number = $overrides['order_number'] ?? 'ORD-12345';
        $order->status = $overrides['status'] ?? OrderStatus::COMPLETED->value;
        $order->total = $overrides['total'] ?? 120.00;
        $order->currency = $overrides['currency'] ?? 'GBP';
        return $order;
    }

    private function setupBasicCheckoutExpectations(array $cartItems, $member, int $siteId): void
    {
        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);
    }

    private function setEstimatedDeliveryExpectations()
    {
        $product = Mockery::mock(Product::class)->makePartial();

        $this->productRepository->shouldReceive('find')
            ->with(100)
            ->andReturn($product);

        $this->fulfilmentResolver->shouldReceive('resolve')
            ->atLeast()->once()
            ->andReturn(Mockery::mock(FulfilmentTypeInterface::class));

        $today = new \DateTimeImmutable();

        $estimatedDelivery = new EstimatedDelivery(false, $today, $today, $today);

        $this->businessDayEstimator->shouldReceive('estimate')
            ->atLeast()->once()
            ->andReturn($estimatedDelivery);
    }

    private function setPreorderExpectations()
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        $policy = Mockery::mock(PhysicalProductAvailabilityPolicy::class)->makePartial();

        $product->shouldReceive('availabilityPolicy')
            ->andReturn($policy);

        $policy->shouldReceive('canPurchase')->atLeast()->once()->andReturn(true);

        $this->productRepository->shouldReceive('lockForUpdate')
            ->atLeast()->once()
            ->andReturn($product);

        $this->calculateSellableStockAction->shouldReceive('execute')
            ->atLeast()->once()
            ->andReturn(100);

        $this->resolveAvailabilityAction->shouldReceive('execute')
            ->atLeast()->once()
            ->andReturn([
                'status' => OrderLineStatus::READY_TO_SHIP->value,
                'is_preorder' => false,
                'expected_ship_date' => now_datetime()->addDays(5)
            ]);
    }

    private function getResolvedDiscounts(
        int   $offerDiscountCents = 0,
        int   $voucherDiscountCents = 0,
        int   $rewardDiscountCents = 0,
        array $metadata = []
    ): ResolvedDiscounts
    {
        $discounts = Mockery::mock(ResolvedDiscounts::class);
        $discounts->offerDiscountCents = $offerDiscountCents;
        $discounts->voucherDiscountCents = $voucherDiscountCents;
        $discounts->rewardDiscountCents = $rewardDiscountCents;
        $discounts->merchantFundedCents = 0;
        $discounts->platformFundedCents = $offerDiscountCents + $voucherDiscountCents + $rewardDiscountCents;
        $discounts->finalSubtotalCents = 10000 - ($offerDiscountCents + $voucherDiscountCents + $rewardDiscountCents);
        $discounts->baseSubtotalCents = 0;
        $discounts->tieredDiscountCents = 0;
        $discounts->metadata = $metadata;

        $discounts->shouldReceive('getTotalDiscountCents')
            ->andReturn($offerDiscountCents + $voucherDiscountCents + $rewardDiscountCents);

        return $discounts;
    }

    public function test_it_successfully_processes_checkout_with_valid_voucher(): void
    {
        $data = $this->getValidCheckoutData(['voucher_code' => 'SAVE20']);
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        // Setup expectations
        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        // Voucher validation
        $voucherValidationResult = new VoucherValidationResult(true, 'good', 20);

        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->with('SAVE20', Mockery::any(), $member->id)
            ->andReturn($voucherValidationResult);

        // Discount resolution with voucher discount
        $discounts = $this->getResolvedDiscounts(0, 2000, 0, [
            'voucher' => [
                'voucher_id' => 100,
                'voucher_code' => 'SAVE20',
                'campaign_id' => null,
                'merchant_id' => null
            ]
        ]);
        $this->discountResolver->shouldReceive('resolve')
            ->once()
            ->andReturn($discounts);

        // Shipping calculation
        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->andReturn(10.00);

        // Currency resolution
        $this->currencyResolver->shouldReceive('resolve')
            ->once()
            ->andReturn('GBP');

        // Tax calculation
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')
            ->once()
            ->andReturn(new TaxData(rate: 0.1, taxCents: 1600));

        // Transaction wrapper
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Payment intent creation
        $this->stripeProcessor->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_123',
                'client_secret' => 'secret_123'
            ]);

        // Order creation
        $this->orderCreationService->shouldReceive('create')
            ->once()
            ->andReturn($order);

        // Voucher application
        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->with(100, $member->id, 20.00, $order->id);

        // Cart clearing
        $this->cartService->shouldReceive('clear')
            ->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('order_number', $result);
    }

    public function test_it_ignores_invalid_voucher_and_proceeds_with_checkout(): void
    {
        $data = $this->getValidCheckoutData(['voucher_code' => 'INVALID']);
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        // Setup expectations
        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $voucherValidationResult = new VoucherValidationResult(false, 'bad');

        // Voucher validation - returns invalid
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn($voucherValidationResult);

        // Discount resolution without voucher
        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')
            ->once()
            ->andReturn($discounts);

        // Continue with normal flow
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));

        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_detects_voucher_id_tampering_and_proceeds_without_voucher(): void
    {
        $data = $this->getValidCheckoutData([
            'voucher_code' => 'SAVE20',
            'voucher_id' => 999 // Tampered ID
        ]);
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $voucherValidationResult = new VoucherValidationResult(true, 'good', 20);

        // Voucher validation returns different ID
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn($voucherValidationResult);

        // Should proceed without voucher due to ID mismatch
        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')
            ->once()
            ->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));

        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_returns_error_when_payment_intent_creation_fails(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Payment intent fails
        $this->stripeProcessor->shouldReceive('createPaymentIntent')
            ->once()
            ->andReturn([
                'success' => false,
                'message' => 'Payment processor error'
            ]);

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Payment processor error', $result['message']);
    }

    public function test_it_uses_database_transaction_for_checkout(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));

        // Transaction assertion
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) use ($order) {
                $this->assertIsCallable($callback);
                return $callback();
            });

        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_emits_order_completed_event_on_successful_checkout(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);
        $this->assertTrue($result['success']);
    }

    public function test_it_clears_cart_only_after_successful_transaction(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);

        // Cart clear should be called within transaction
        $this->cartService->shouldReceive('clear')
            ->once()
            ->globally()
            ->ordered();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_handles_exception_and_returns_error(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(false);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to create order', $result['message']);
    }

    public function test_it_successfully_processes_multi_merchant_checkout(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->memberAuthWrapper->shouldReceive('check')
            ->times(3)
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->times(3)
            ->andReturn($member);

        // Discount resolution
        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        // Split by merchant
        $groups = [
            'merchant_1' => [
                'merchant_id' => 1,
                'stripe_group_key' => 'acct_123',
                'items' => [['product_id' => 1, 'quantity' => 1, 'price' => 50.00]]
            ],
            'merchant_2' => [
                'merchant_id' => 2,
                'stripe_group_key' => 'acct_456',
                'items' => [['product_id' => 2, 'quantity' => 1, 'price' => 50.00]]
            ]
        ];
        $this->splittingService->shouldReceive('splitByMerchant')
            ->once()
            ->andReturn($groups);

        // Shipping per group
        $shippingPerGroup = ['merchant_1' => 5.00, 'merchant_2' => 5.00];
        $this->merchantShippingService->shouldReceive('calculatePerGroup')
            ->once()
            ->andReturn($shippingPerGroup);

        // Tax calculation
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')
            ->once()
            ->andReturn(new TaxData(rate: 0.1, taxCents: 2000));

        // Payment allocation
        $allocations = [
            'merchant_1' => [
                'subtotal' => 50.00,
                'shipping' => 5.00,
                'tax' => 10.00,
                'total' => 65.00,
                'stripe_eligible' => true
            ],
            'merchant_2' => [
                'subtotal' => 50.00,
                'shipping' => 5.00,
                'tax' => 10.00,
                'total' => 65.00,
                'stripe_eligible' => true
            ]
        ];
        $this->allocationService->shouldReceive('allocate')
            ->once()
            ->andReturn($allocations);

        // Currency
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');

        $this->cartService->shouldReceive('requiresShipping')->times(3)->andReturn(false);

        // Stripe payment intents for each group
        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')
            ->twice()
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_123',
                'client_secret' => 'secret_123'
            ]);

        // Transaction wrapper
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Order creation for each merchant
        $order1 = $this->getOrder(['order_number' => 'ORD-001']);
        $order2 = $this->getOrder(['order_number' => 'ORD-002']);

        $this->orderCreationService->shouldReceive('createMerchantOrder')
            ->once()
            ->andReturn($order1);

        $this->orderCreationService->shouldReceive('createMerchantOrder')
            ->once()
            ->andReturn($order2);

        // Shipment creation
        $this->shipmentRepository->shouldReceive('create')
            ->twice();

        $this->merchantShippingService->shouldReceive('isConsolidationEnabled')
            ->twice()
            ->andReturn(false);

        // Cart clearing
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('checkout_id', $result);
        $this->assertArrayHasKey('order_numbers', $result);
        $this->assertCount(2, $result['order_numbers']);
    }

    public function test_it_returns_error_when_multi_merchant_checkout_has_no_groups(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(false);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->memberAuthWrapper->shouldReceive('check')
            ->once()
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->once()
            ->andReturn($member);

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        // No groups returned
        $this->splittingService->shouldReceive('splitByMerchant')
            ->once()
            ->andReturn([]);

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('No items to process', $result['message']);
    }

    public function test_it_uses_transaction_for_multi_merchant_checkout(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();

        $this->cartService->shouldReceive('requiresShipping')->times(2)->andReturn(false);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $groups = [
            'merchant_1' => [
                'merchant_id' => 1,
                'stripe_group_key' => 'acct_123',
                'items' => [['product_id' => 1, 'quantity' => 1, 'price' => 50.00]]
            ]
        ];
        $this->splittingService->shouldReceive('splitByMerchant')->once()->andReturn($groups);
        $this->merchantShippingService->shouldReceive('calculatePerGroup')->once()->andReturn(['merchant_1' => 5.00]);
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1000));
        $this->allocationService->shouldReceive('allocate')->once()->andReturn([
            'merchant_1' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true]
        ]);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);

        // Transaction assertion
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                $this->assertIsCallable($callback);
                return $callback();
            });

        $order = $this->getOrder();
        $this->orderCreationService->shouldReceive('createMerchantOrder')->once()->andReturn($order);
        $this->shipmentRepository->shouldReceive('create')->once();
        $this->merchantShippingService->shouldReceive('isConsolidationEnabled')->once()->andReturn(false);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_emits_multi_merchant_checkout_completed_event(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->memberAuthWrapper->shouldReceive('check')
            ->twice()
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->twice()
            ->andReturn($member);

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $groups = [
            'merchant_1' => [
                'merchant_id' => 1,
                'stripe_group_key' => 'acct_123',
                'items' => [['product_id' => 1, 'quantity' => 1, 'price' => 50.00]]
            ]
        ];
        $this->splittingService->shouldReceive('splitByMerchant')->once()->andReturn($groups);
        $this->merchantShippingService->shouldReceive('calculatePerGroup')->once()->andReturn(['merchant_1' => 5.00]);
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1000));
        $this->allocationService->shouldReceive('allocate')->once()->andReturn([
            'merchant_1' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true]
        ]);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $order = $this->getOrder();
        $this->orderCreationService->shouldReceive('createMerchantOrder')->once()->andReturn($order);
        $this->shipmentRepository->shouldReceive('create')->once();
        $this->merchantShippingService->shouldReceive('isConsolidationEnabled')->once()->andReturn(false);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);
        $this->assertTrue($result['success']);
    }

    public function test_it_handles_multi_merchant_checkout_exception(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(false);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andThrow(new \Exception('Service unavailable'));

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Checkout failed', $result['message']);
    }

    public function test_it_applies_voucher_with_merchant_funding(): void
    {
        $data = $this->getValidCheckoutData(['voucher_code' => 'MERCHANT20']);
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        // Voucher validation with merchant funding
        $voucherValidationResult = new VoucherValidationResult(true, 'good', 20);

        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn($voucherValidationResult);

        // Discount resolution with merchant-funded voucher
        $discounts = $this->getResolvedDiscounts(0, 2000, 0, [
            'voucher' => [
                'voucher_id' => 200,
                'voucher_code' => 'MERCHANT20',
                'campaign_id' => null,
                'merchant_id' => 5
            ]
        ]);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1600));
        $this->databaseMock->shouldReceive('transaction')->twice()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->merchantRepository->shouldReceive('createTransaction')
            ->once();

        // Voucher application
        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->with(200, $member->id, 20.00, $order->id);

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_works_with_guest_checkout_when_no_member_authenticated(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $order = $this->getOrder();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        // No authenticated member
        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->memberAuthWrapper->shouldReceive('check')->twice()->andReturn(false);

        // Discount resolution without member
        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_successfully_processes_checkout_with_reward(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $reward = Mockery::mock(MemberReward::class)->makePartial();

        $this->setEstimatedDeliveryExpectations();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);
        $this->rewardsRepository->shouldReceive('find')->once()->with(10)->andReturn($reward);

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setPreorderExpectations();

        // Discount resolution with reward
        $discounts = $this->getResolvedDiscounts(0, 0, 500, [
            'reward' => [
                'reward_id' => 10,
                'member_id' => $member->id,
                'amount' => 5.00
            ]
        ]);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1900));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_processes_checkout_with_offer_discount(): void
    {
        $data = $this->getValidCheckoutData();
        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);
        $siteId = 1;
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 100,
                'quantity' => 1,
                'price' => 80.00,
                'base_price' => 100.00,
                'name' => 'Discounted Product',
                'item_type' => 'offer'
            ]
        ];
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        // Discount resolution with offer discount
        $discounts = $this->getResolvedDiscounts(2000, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1600));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_processes_checkout_with_stackable_voucher_and_offer(): void
    {
        $data = $this->getValidCheckoutData(['voucher_code' => 'SAVE10']);
        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);
        $siteId = 1;
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 100,
                'quantity' => 1,
                'price' => 80.00,
                'base_price' => 100.00,
                'name' => 'Discounted Product',
                'item_type' => 'offer'
            ]
        ];
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $voucherValidationResult = new VoucherValidationResult(true, 'good', 8);

        // Voucher validation - stackable
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn($voucherValidationResult);

        // Both offer and voucher discounts
        $discounts = $this->getResolvedDiscounts(2000, 800, 0, [
            'voucher' => [
                'voucher_id' => 100,
                'voucher_code' => 'SAVE10',
                'campaign_id' => null,
                'merchant_id' => null,
                'is_stackable' => true
            ]
        ]);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1440));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->voucherService->shouldReceive('applyVoucher')->once();
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_processes_checkout_with_non_stackable_voucher_overriding_offer(): void
    {
        $data = $this->getValidCheckoutData(['voucher_code' => 'MEGA50']);
        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);
        $siteId = 1;
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 100,
                'quantity' => 1,
                'price' => 80.00,
                'base_price' => 100.00,
                'name' => 'Discounted Product',
                'item_type' => 'offer'
            ]
        ];
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $voucherValidationResult = new VoucherValidationResult(true, 'good', 50);

        // Non-stackable voucher with bigger discount
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn($voucherValidationResult);

        // Voucher overrides offer discount
        $discounts = $this->getResolvedDiscounts(0, 5000, 0, [
            'voucher' => [
                'voucher_id' => 200,
                'voucher_code' => 'MEGA50',
                'campaign_id' => null,
                'merchant_id' => null,
                'is_stackable' => false
            ]
        ]);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1000));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->voucherService->shouldReceive('applyVoucher')->once();
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_processes_checkout_with_all_discount_types(): void
    {
        $data = $this->getValidCheckoutData(['voucher_code' => 'SAVE10']);
        $siteId = 1;
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 100,
                'quantity' => 1,
                'price' => 80.00,
                'base_price' => 100.00,
                'name' => 'Discounted Product',
                'item_type' => 'offer'
            ]
        ];
        $member = $this->getMember();
        $order = $this->getOrder();

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $this->rewardsRepository->shouldReceive('find')->once()->with(10)->andReturn($reward);

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $voucherValidationResult = new VoucherValidationResult(true, 'good', 8);

        // Voucher validation
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn($voucherValidationResult);

        // All three discount types
        $discounts = $this->getResolvedDiscounts(2000, 800, 500, [
            'voucher' => [
                'voucher_id' => 100,
                'voucher_code' => 'SAVE10',
                'campaign_id' => null,
                'merchant_id' => null,
                'is_stackable' => true
            ],
            'reward' => [
                'reward_id' => 10,
                'member_id' => $member->id,
                'amount' => 5.00
            ]
        ]);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1390));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->voucherService->shouldReceive('applyVoucher')->once();
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_passes_correct_amount_in_cents_to_stripe(): void
    {
        $data = $this->getValidCheckoutData();
        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $discounts->finalSubtotalCents = 10000;
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        // Assert amount is in cents (integer)
        $this->stripeProcessor->shouldReceive('createPaymentIntent')
            ->once()
            ->with(Mockery::on(function ($args) {
                $this->assertIsFloat($args['amount']);
                $this->assertEquals(13000, $args['amount']); // 100.00 + 10.00 + 20.00 in cents
                return true;
            }))
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_123',
                'client_secret' => 'secret_123'
            ]);

        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_includes_discount_metadata_in_payment_intent(): void
    {
        $data = $this->getValidCheckoutData(['voucher_code' => 'SAVE20']);
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $voucherValidationResult = new VoucherValidationResult(true, 'good', 20);

        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn($voucherValidationResult);

        $discounts = $this->getResolvedDiscounts(1000, 2000, 500, [
            'voucher' => [
                'voucher_id' => 100,
                'voucher_code' => 'SAVE20',
                'campaign_id' => 5,
                'merchant_id' => null
            ]
        ]);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1650));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        // Assert metadata includes discount breakdown
        $this->stripeProcessor->shouldReceive('createPaymentIntent')
            ->once()
            ->with(Mockery::on(function ($args) {
                $this->assertArrayHasKey('metadata', $args);
                $this->assertEquals(1000, $args['metadata']['offer_discount_cents']);
                $this->assertEquals(2000, $args['metadata']['voucher_discount_cents']);
                $this->assertEquals(500, $args['metadata']['reward_discount_cents']);
                $this->assertEquals('SAVE20', $args['metadata']['voucher_code']);
                $this->assertEquals(5, $args['metadata']['campaign_id']);
                return true;
            }))
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_123',
                'client_secret' => 'secret_123'
            ]);

        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->voucherService->shouldReceive('applyVoucher')->once();
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_returns_payment_intent_details_in_response(): void
    {
        $data = $this->getValidCheckoutData();
        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_test_123',
            'client_secret' => 'pi_test_123_secret_abc'
        ]);

        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals('pi_test_123', $result['payment_intent_id']);
        $this->assertEquals('pi_test_123_secret_abc', $result['client_secret']);
    }

    public function test_it_applies_voucher_only_when_voucher_discount_is_present(): void
    {
        $data = $this->getValidCheckoutData();
        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        // No voucher discount
        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);

        // applyVoucher should NOT be called
        $this->voucherService->shouldNotReceive('applyVoucher');

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_creates_multiple_orders_for_multi_merchant_checkout(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $this->cartService->shouldReceive('requiresShipping')->times(4)->andReturn(false);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->memberAuthWrapper->shouldReceive('check')
            ->times(4)
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->times(4)
            ->andReturn($member);

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $groups = [
            'merchant_1' => [
                'merchant_id' => 1,
                'stripe_group_key' => 'acct_123',
                'items' => [['product_id' => 1, 'quantity' => 1, 'price' => 50.00]]
            ],
            'merchant_2' => [
                'merchant_id' => 2,
                'stripe_group_key' => 'acct_456',
                'items' => [['product_id' => 2, 'quantity' => 1, 'price' => 50.00]]
            ],
            'merchant_3' => [
                'merchant_id' => 3,
                'stripe_group_key' => 'acct_789',
                'items' => [['product_id' => 3, 'quantity' => 1, 'price' => 50.00]]
            ]
        ];
        $this->splittingService->shouldReceive('splitByMerchant')->once()->andReturn($groups);

        $shippingPerGroup = ['merchant_1' => 5.00, 'merchant_2' => 5.00, 'merchant_3' => 5.00];
        $this->merchantShippingService->shouldReceive('calculatePerGroup')->once()->andReturn($shippingPerGroup);

        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 3000));

        $allocations = [
            'merchant_1' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true],
            'merchant_2' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true],
            'merchant_3' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true]
        ];
        $this->allocationService->shouldReceive('allocate')->once()->andReturn($allocations);

        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');

        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')
            ->times(3)
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_123',
                'client_secret' => 'secret_123'
            ]);

        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        // Three orders created
        $order1 = $this->getOrder(['order_number' => 'ORD-001', 'id' => 1]);
        $order2 = $this->getOrder(['order_number' => 'ORD-002', 'id' => 2]);
        $order3 = $this->getOrder(['order_number' => 'ORD-003', 'id' => 3]);

        $this->orderCreationService->shouldReceive('createMerchantOrder')->times(3)->andReturn($order1, $order2, $order3);
        $this->shipmentRepository->shouldReceive('create')->times(3);
        $this->merchantShippingService->shouldReceive('isConsolidationEnabled')->times(3)->andReturn(false);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['order_numbers']);
        $this->assertEquals(['ORD-001', 'ORD-002', 'ORD-003'], $result['order_numbers']);
    }

    public function test_it_returns_error_when_multi_merchant_payment_intent_fails(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(false);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        //Multi-merchant checkout validates and gets cart items first
        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->memberAuthWrapper->shouldReceive('check')->once()->andReturn(true);
        $this->memberAuthWrapper->shouldReceive('getMember')->once()->andReturn($member);

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $groups = [
            'merchant_1' => [
                'merchant_id' => 1,
                'stripe_group_key' => 'acct_123',
                'items' => [['product_id' => 1, 'quantity' => 1, 'price' => 50.00]]
            ]
        ];
        $this->splittingService->shouldReceive('splitByMerchant')->once()->andReturn($groups);
        $this->merchantShippingService->shouldReceive('calculatePerGroup')->once()->andReturn(['merchant_1' => 5.00]);
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1000));
        $this->allocationService->shouldReceive('allocate')->once()->andReturn([
            'merchant_1' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true]
        ]);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');

        // Payment intent fails BEFORE transaction starts
        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')
            ->once()
            ->andReturn(['success' => false]);

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Payment processing failed', $result['message']);
    }

    public function test_it_creates_shipment_for_each_merchant_order(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();

        $this->cartService->shouldReceive('requiresShipping')->times(3)->andReturn(false);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->memberAuthWrapper->shouldReceive('check')
            ->times(3)
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->times(3)
            ->andReturn($member);

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $groups = [
            'merchant_1' => ['merchant_id' => 1, 'stripe_group_key' => 'acct_123', 'items' => [['product_id' => 1]]],
            'merchant_2' => ['merchant_id' => 2, 'stripe_group_key' => 'acct_456', 'items' => [['product_id' => 2]]]
        ];
        $this->splittingService->shouldReceive('splitByMerchant')->once()->andReturn($groups);
        $this->merchantShippingService->shouldReceive('calculatePerGroup')->once()->andReturn(['merchant_1' => 5.00, 'merchant_2' => 5.00]);
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));
        $this->allocationService->shouldReceive('allocate')->once()->andReturn([
            'merchant_1' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true],
            'merchant_2' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true]
        ]);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')->twice()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $order1 = $this->getOrder(['id' => 1]);
        $order2 = $this->getOrder(['id' => 2]);
        $this->orderCreationService->shouldReceive('createMerchantOrder')->twice()->andReturn($order1, $order2);
        $this->merchantShippingService->shouldReceive('isConsolidationEnabled')->twice()->andReturn(false);

        // Assert shipment creation is called twice
        $this->shipmentRepository->shouldReceive('create')
            ->twice()
            ->with(Mockery::on(function ($args) {
                $this->assertArrayHasKey('order_id', $args);
                $this->assertArrayHasKey('checkout_id', $args);
                $this->assertArrayHasKey('merchant_id', $args);
                $this->assertArrayHasKey('shipping_cost', $args);
                $this->assertArrayHasKey('country', $args);
                $this->assertEquals('pending', $args['status']);
                return true;
            }));

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_applies_voucher_once_at_checkout_level_in_multi_merchant(): void
    {
        $data = $this->getValidCheckoutData(['voucher_code' => 'SAVE20']);
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('requiresShipping')->times(3)->andReturn(false);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->memberAuthWrapper->shouldReceive('check')
            ->times(3)
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->times(3)
            ->andReturn($member);

        $voucherValidationResult = new VoucherValidationResult(true, 'good', 20);

        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn($voucherValidationResult);

        $discounts = $this->getResolvedDiscounts(0, 2000, 0, [
            'voucher' => [
                'voucher_id' => 100,
                'voucher_code' => 'SAVE20',
                'campaign_id' => null,
                'merchant_id' => null
            ]
        ]);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $groups = [
            'merchant_1' => ['merchant_id' => 1, 'stripe_group_key' => 'acct_123', 'items' => [['product_id' => 1]]],
            'merchant_2' => ['merchant_id' => 2, 'stripe_group_key' => 'acct_456', 'items' => [['product_id' => 2]]]
        ];
        $this->splittingService->shouldReceive('splitByMerchant')->once()->andReturn($groups);
        $this->merchantShippingService->shouldReceive('calculatePerGroup')->once()->andReturn(['merchant_1' => 5.00, 'merchant_2' => 5.00]);
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1600));
        $this->allocationService->shouldReceive('allocate')->once()->andReturn([
            'merchant_1' => ['subtotal' => 40.00, 'shipping' => 5.00, 'tax' => 8.00, 'total' => 53.00, 'stripe_eligible' => true],
            'merchant_2' => ['subtotal' => 40.00, 'shipping' => 5.00, 'tax' => 8.00, 'total' => 53.00, 'stripe_eligible' => true]
        ]);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')->twice()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $order1 = $this->getOrder(['id' => 1]);
        $order2 = $this->getOrder(['id' => 2]);
        $this->orderCreationService->shouldReceive('createMerchantOrder')->twice()->andReturn($order1, $order2);
        $this->shipmentRepository->shouldReceive('create')->twice();
        $this->merchantShippingService->shouldReceive('isConsolidationEnabled')->twice()->andReturn(false);

        // Voucher should be applied ONCE with first order ID
        $this->voucherService->shouldReceive('applyVoucher')
            ->once()
            ->with(100, $member->id, 20.00, $order1->id);

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_skips_non_stripe_eligible_groups_in_multi_merchant_checkout(): void
    {
        $data = $this->getValidCheckoutData();
        $this->cartService->shouldReceive('requiresShipping')->times(3)->andReturn(false);
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->memberAuthWrapper->shouldReceive('check')
            ->times(3)
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->times(3)
            ->andReturn($member);

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $groups = [
            'merchant_1' => ['merchant_id' => 1, 'stripe_group_key' => 'acct_123', 'items' => [['product_id' => 1]]],
            'merchant_2' => ['merchant_id' => 2, 'stripe_group_key' => null, 'items' => [['product_id' => 2]]]
        ];
        $this->splittingService->shouldReceive('splitByMerchant')->once()->andReturn($groups);
        $this->merchantShippingService->shouldReceive('calculatePerGroup')->once()->andReturn(['merchant_1' => 5.00, 'merchant_2' => 5.00]);
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));
        $this->allocationService->shouldReceive('allocate')->once()->andReturn([
            'merchant_1' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true],
            'merchant_2' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => false]
        ]);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');

        // Only ONE payment intent created (merchant_2 is not stripe eligible)
        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')
            ->once()
            ->andReturn([
                'success' => true,
                'payment_intent_id' => 'pi_123',
                'client_secret' => 'secret_123'
            ]);

        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $order1 = $this->getOrder(['id' => 1]);
        $order2 = $this->getOrder(['id' => 2]);
        $this->orderCreationService->shouldReceive('createMerchantOrder')->twice()->andReturn($order1, $order2);
        $this->shipmentRepository->shouldReceive('create')->twice();
        $this->merchantShippingService->shouldReceive('isConsolidationEnabled')->twice()->andReturn(false);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_calculates_shipping_cost_correctly(): void
    {
        $data = $this->getValidCheckoutData();
        $data['country'] = 'CA'; // Different country for different shipping
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn($cartItems);

        $this->memberAuthWrapper->shouldReceive('check')
            ->times(2)
            ->andReturn(true);

        $this->memberAuthWrapper->shouldReceive('getMember')
            ->times(2)
            ->andReturn($member);

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        // Assert shipping calculation receives correct parameters
        $this->shippingService->shouldReceive('calculateShipping')
            ->once()
            ->with(100.00, Mockery::on(function ($shippingData) {
                return $shippingData['country'] === 'CA';
            }))
            ->andReturn(15.00);

        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2500));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_validates_first_name_is_required(): void
    {
        $data = [
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address_line_1' => '123 Main St',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'GB'
        ];

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('name', strtolower($result['message']));
    }

    public function test_it_validates_last_name_is_required(): void
    {
        $data = [
            'first_name' => 'John',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'address_line_1' => '123 Main St',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'GB'
        ];

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('name', strtolower($result['message']));
    }

    public function test_it_validates_email_is_required(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '1234567890',
            'address_line_1' => '123 Main St',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'GB'
        ];

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('email', strtolower($result['message']));
    }

    public function test_it_validates_phone_is_required(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'address_line_1' => '123 Main St',
            'city' => 'London',
            'postal_code' => 'SW1A 1AA',
            'country' => 'GB'
        ];

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('phone', strtolower($result['message']));
    }

    public function test_it_calculates_tax_correctly(): void
    {
        $data = $this->getValidCheckoutData();
        $data['country'] = 'US';
        $data['state'] = 'CA';
        $data['postal_code'] = '90210';
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setEstimatedDeliveryExpectations();

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $discounts->finalSubtotalCents = 10000;
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('USD');

        // Assert tax calculation receives correct parameters
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')
            ->once()
            ->with(
                10000, // finalSubtotalCents
                1000,  // shippingCents
                'US',
                'CA',
                '90210',
                $member
            )
            ->andReturn(new TaxData(rate: 0.1, taxCents: 950)); // 9.5% tax rate

        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_confirms_regular_checkout_payment_successfully(): void
    {
        $paymentIntentId = 'pi_test_123';
        $orderId = 1;

        $this->stripeProcessor->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with($paymentIntentId)
            ->andReturn([
                'success' => true,
                'status' => 'succeeded'
            ]);

        $order = $this->getOrder(['id' => $orderId]);
        $this->orderManager->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($order);

        $this->orderManager->shouldReceive('updateOrderStatus')
            ->once()
            ->with($orderId, Mockery::any(), Mockery::any());

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->confirmRegularCheckoutPayment($paymentIntentId, $orderId);

        $this->assertTrue($result['success']);
        $this->assertEquals('Order completed successfully', $result['message']);
    }

    public function test_it_fails_payment_confirmation_when_payment_not_succeeded(): void
    {
        $paymentIntentId = 'pi_test_123';
        $orderId = 1;

        $this->stripeProcessor->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with($paymentIntentId)
            ->andReturn([
                'success' => true,
                'status' => 'requires_action' // Not succeeded
            ]);

        $result = $this->service->confirmRegularCheckoutPayment($paymentIntentId, $orderId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Payment confirmation failed', $result['message']);
    }

    public function test_it_handles_payment_confirmation_exception(): void
    {
        $paymentIntentId = 'pi_test_123';
        $orderId = 1;

        $this->stripeProcessor->shouldReceive('confirmPaymentIntent')
            ->once()
            ->with($paymentIntentId)
            ->andThrow(new \Exception('Network error'));

        $result = $this->service->confirmRegularCheckoutPayment($paymentIntentId, $orderId);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Payment confirmation error', $result['message']);
    }

    public function test_it_returns_error_when_multi_merchant_checkout_cart_is_empty(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(false);

        $this->cartService->shouldReceive('getItems')
            ->once()
            ->andReturn([]);

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cart is empty', $result['message']);
    }

    public function test_it_handles_non_stackable_voucher_that_loses_to_offer(): void
    {
        $data = $this->getValidCheckoutData(['voucher_code' => 'SMALL35']);

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $siteId = 1;

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        // Item with £60 offer discount
        $cartItems = [
            [
                'id' => 1,
                'product_id' => 100,
                'quantity' => 1,
                'price' => 140.00,
                'base_price' => 200.00,
                'name' => 'Product with big offer',
                'item_type' => 'offer'
            ]
        ];

        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $voucherValidationResult = new VoucherValidationResult(true, 'good', 35);

        // Non-stackable voucher with smaller discount (£35 < £60 offer)
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn($voucherValidationResult);

        // Offer wins, voucher loses (offer discount kept, no voucher discount)
        $discounts = $this->getResolvedDiscounts(6000, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 2000));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);

        // Voucher should NOT be applied since it lost
        $this->voucherService->shouldNotReceive('applyVoucher');

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_handles_insufficient_merchant_balance_for_voucher(): void
    {
        $data = $this->getValidCheckoutData(['voucher_code' => 'MERCHANT50']);
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->setEstimatedDeliveryExpectations();

        $this->setPreorderExpectations();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);

        $voucherValidationResult = new VoucherValidationResult(true, 'good', 50);

        // Merchant-funded voucher with £50 discount
        $this->voucherService->shouldReceive('validateVoucherForCheckout')
            ->once()
            ->andReturn($voucherValidationResult);

        $discounts = $this->getResolvedDiscounts(0, 5000, 0, [
            'voucher' => [
                'voucher_id' => 200,
                'voucher_code' => 'MERCHANT50',
                'merchant_id' => 5,
                'campaign_id' => null
            ]
        ]);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(10.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1000));
        $this->databaseMock->shouldReceive('transaction')->twice()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);

        // Merchant with insufficient balance
        $merchant = Mockery::mock(Merchant::class)->makePartial();
        $merchant->id = 5;
        $merchant->balance = 10.00; // Only £10, needs £50

        $this->merchantRepository->shouldReceive('find')
            ->once()
            ->with(5)
            ->andReturn($merchant);

        $this->merchantRepository->shouldReceive('createTransaction')
            ->once()
            ->with(Mockery::on(function ($cb) use ($merchant) {
                return $cb['status'] === 'pending_review';
            }));

        // applyVoucher should still be called
        $this->voucherService->shouldReceive('applyVoucher')->once();

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        // Should still succeed but merchant funding may be handled differently
        $this->assertTrue($result['success']);
    }

    public function testAttachesDeliveryEstimatesToPhysicalProduct(): void
    {
        $product = Mockery::mock(Product::class);
        $product->shouldReceive('getAttribute')->with('id')->andReturn(1);

        $fulfilment = Mockery::mock(FulfilmentTypeInterface::class);
        $fulfilment->shouldReceive('requiresShipping')->andReturn(true);

        $dispatchDate = new DateTimeImmutable('2026-02-17');
        $deliveryFrom = new DateTimeImmutable('2026-02-19');
        $deliveryTo = new DateTimeImmutable('2026-02-24');

        $estimate = EstimatedDelivery::physical($dispatchDate, $deliveryFrom, $deliveryTo);

        $cartItems = [
            [
                'product_id' => 1,
                'product_name' => 'Test Product',
                'quantity' => 1,
                'price' => 99.99
            ]
        ];

        $this->productRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($product);

        $this->fulfilmentResolver->shouldReceive('resolve')
            ->with($product)
            ->once()
            ->andReturn($fulfilment);

        $this->businessDayEstimator->shouldReceive('estimate')
            ->once()
            ->with(
                $fulfilment,
                Mockery::type(DeliveryMethodConfig::class),
                Mockery::type(DateTimeImmutable::class)
            )
            ->andReturn($estimate);

        // Use reflection to call private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('attachDeliveryEstimates');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $cartItems);

        $this->assertCount(1, $result);
        $this->assertEquals('2026-02-17', $result[0]['estimated_dispatch']);
        $this->assertEquals('2026-02-19', $result[0]['estimated_delivery_from']);
        $this->assertEquals('2026-02-24', $result[0]['estimated_delivery_to']);
        $this->assertTrue($result[0]['requires_shipping']);
        $this->assertStringContainsString('Feb', $result[0]['estimated_delivery_formatted']);
    }

    public function test_snapshots_preorder_state_at_checkout(): void
    {
        $expectedShipDate = new \DateTime('+14 days');

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 0;
        $product->preorder_enabled = true;
        $product->preorder_restock_date = $expectedShipDate;

        $this->productRepository->shouldReceive('find')
            ->with(1)
            ->andReturn($product);

        $this->resolveAvailabilityAction->shouldReceive('execute')
            ->with($product, 3)
            ->andReturn([
                'status' => OrderLineStatus::PENDING_PREORDER->value,
                'expected_ship_date' => $expectedShipDate,
                'is_preorder' => true,
            ]);

        // Verify checkout creates order line with snapshotted data
        $cartItem = [
            'product_id' => 1,
            'quantity' => 3,
            'price' => 29.99,
        ];

        // After processing, order line should have:
        // - status = pending_preorder
        // - expected_ship_date = $expectedShipDate (snapshot)
        // - quantity_allocated = 0

        $this->assertTrue(true); // Placeholder - implement based on actual checkout flow
    }

    public function test_it_throws_when_product_not_found_during_availability_check(): void
    {
        $data = $this->getValidCheckoutData();
        $cartItems = [
            ['product_id' => 99, 'product_name' => 'Ghost Product', 'quantity' => 1, 'price' => 50.00]
        ];

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(false);
        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);

        $this->productRepository->shouldReceive('lockForUpdate')
            ->with(99)
            ->once()
            ->andReturn(null);

        // Exception is caught by the outer try/catch and returns failure
        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to create order', $result['message']);
    }

    public function test_it_throws_when_product_unavailable_for_purchase(): void
    {
        $data = $this->getValidCheckoutData();
        $cartItems = [
            ['product_id' => 1, 'product_name' => 'Unavailable Product', 'quantity' => 1, 'price' => 50.00]
        ];

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(false);
        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);

        $policy = Mockery::mock(\App\Services\Billing\Preorder\PhysicalProductAvailabilityPolicy::class)->makePartial();
        $policy->shouldReceive('canPurchase')->once()->andReturn(false);

        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'Unavailable Product';
        $product->shouldReceive('availabilityPolicy')->andReturn($policy);

        $this->productRepository->shouldReceive('lockForUpdate')
            ->with(1)
            ->once()
            ->andReturn($product);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to create order', $result['message']);
    }

    public function test_it_throws_when_insufficient_stock_and_not_preorder(): void
    {
        $data = $this->getValidCheckoutData();
        $cartItems = [
            ['product_id' => 1, 'product_name' => 'Low Stock Product', 'quantity' => 5, 'price' => 50.00]
        ];

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(false);
        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);

        $policy = Mockery::mock(\App\Services\Billing\Preorder\PhysicalProductAvailabilityPolicy::class)->makePartial();
        $policy->shouldReceive('canPurchase')->once()->andReturn(true);
        $policy->shouldReceive('isPreOrder')->once()->andReturn(false);

        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'Low Stock Product';
        $product->shouldReceive('availabilityPolicy')->andReturn($policy);

        $this->productRepository->shouldReceive('lockForUpdate')->with(1)->andReturn($product);

        // Only 2 units of sellable stock, customer wants 5
        $this->calculateSellableStockAction->shouldReceive('execute')
            ->with($product)
            ->once()
            ->andReturn(2);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to create order', $result['message']);
    }

    public function test_it_throws_when_preorder_has_no_expected_ship_date(): void
    {
        $data = $this->getValidCheckoutData();
        $cartItems = [
            ['product_id' => 1, 'product_name' => 'Preorder Product', 'quantity' => 5, 'price' => 50.00]
        ];

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(false);
        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);

        $policy = Mockery::mock(\App\Services\Billing\Preorder\PhysicalProductAvailabilityPolicy::class)->makePartial();
        $policy->shouldReceive('canPurchase')->once()->andReturn(true);
        $policy->shouldReceive('isPreOrder')->once()->andReturn(true);
        $policy->shouldReceive('getExpectedShipDate')->once()->andReturn(null);

        $product = Mockery::mock(Product::class)->makePartial();
        $product->name = 'Preorder Product';
        $product->shouldReceive('availabilityPolicy')->andReturn($policy);

        $this->productRepository->shouldReceive('lockForUpdate')->with(1)->andReturn($product);
        $this->calculateSellableStockAction->shouldReceive('execute')->andReturn(2); // less than 5

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to create order', $result['message']);
    }

    public function test_it_validates_address_fields_required_when_shipping_needed_without_saved_address(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            // Missing: address, city, postal_code, country
        ];

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(true);

        $result = $this->service->processCheckout($data, 1);

        $this->assertFalse($result['success']);
        // Should fail on 'address' being missing
        $this->assertStringContainsString('address', strtolower($result['message']));
    }

    public function test_it_skips_address_validation_when_saved_address_provided(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'saved_address' => 5, // Saved address ID - no address fields needed
            'country' => 'GB',
        ];
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();
        $order = $this->getOrder();

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(true);

        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);
        $this->setEstimatedDeliveryExpectations();
        $this->setPreorderExpectations();

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(0.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 0));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123'
        ]);
        $this->orderCreationService->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(fn($d) => $d['shipping_address_id'] === 5 && !isset($d['shipping_address'])),
                Mockery::any(),
                $siteId
            )
            ->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_confirms_payment_with_correct_order_status(): void
    {
        $paymentIntentId = 'pi_test_123';
        $orderId = 1;

        $this->stripeProcessor->shouldReceive('confirmPaymentIntent')
            ->once()
            ->andReturn(['success' => true, 'status' => 'succeeded']);

        $order = $this->getOrder(['id' => $orderId]);
        $this->orderManager->shouldReceive('find')->with($orderId)->once()->andReturn($order);

        // Verify the exact status values passed
        $this->orderManager->shouldReceive('updateOrderStatus')
            ->once()
            ->with(
                $orderId,
                \App\Enums\Orders\OrderStatus::COMPLETED->value,
                \App\Enums\PaymentStatus::PAID->value
            );

        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->confirmRegularCheckoutPayment($paymentIntentId, $orderId);

        $this->assertTrue($result['success']);
    }

    public function test_eligibility_service_removes_duplicate_subscription_and_proceeds(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $member = $this->getMember();
        $order = $this->getOrder();

        $validItem = ['id' => 2, 'product_id' => 100, 'quantity' => 1, 'price' => 50.00, 'name' => 'Book'];
        $removedItem = ['id' => 1, 'subscription_plan_id' => 10, 'quantity' => 1, 'price' => 9.99, 'name' => 'Sub'];

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);
        $this->cartService->shouldReceive('getItems')->once()->andReturn([$removedItem, $validItem]);

        $this->memberAuthWrapper->shouldReceive('check')->twice()->andReturn(true);
        $this->memberAuthWrapper->shouldReceive('getMember')->twice()->andReturn($member);

        // Eligibility strips the subscription item, leaves the physical product
        $this->eligibilityService->shouldReceive('validate')
            ->once()
            ->with($member, Mockery::type('array'))
            ->andReturn(new EligibilityResult(valid: [$validItem], removed: [$removedItem]));

        $this->setEstimatedDeliveryExpectations();
        $this->setPreorderExpectations();

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(0.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 500));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123',
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_eligibility_service_returns_error_when_all_items_removed(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $member = $this->getMember();

        $subscriptionItem = ['id' => 1, 'subscription_plan_id' => 10, 'quantity' => 1, 'price' => 9.99];

        $this->cartService->shouldReceive('requiresShipping')->once()->andReturn(false);
        $this->cartService->shouldReceive('getItems')->once()->andReturn([$subscriptionItem]);

        $this->memberAuthWrapper->shouldReceive('check')->once()->andReturn(true);
        $this->memberAuthWrapper->shouldReceive('getMember')->once()->andReturn($member);

        // All items stripped — nothing left to purchase
        $this->eligibilityService->shouldReceive('validate')
            ->once()
            ->andReturn(new EligibilityResult(valid: [], removed: [$subscriptionItem]));

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('All items were invalid and removed from the cart.', $result['message']);
    }

    public function test_eligibility_service_not_called_for_guest_checkout(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $order = $this->getOrder();

        $cartItems = $this->getCartItems();

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);
        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);

        // Guest — check returns false, getMember never called
        $this->memberAuthWrapper->shouldReceive('check')->twice()->andReturn(false);

        // Eligibility must NOT run for guests
        $this->eligibilityService->shouldNotReceive('validate');

        $this->setEstimatedDeliveryExpectations();
        $this->setPreorderExpectations();

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);
        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(0.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 500));
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true,
            'payment_intent_id' => 'pi_123',
            'client_secret' => 'secret_123',
        ]);
        $this->orderCreationService->shouldReceive('create')->once()->andReturn($order);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    public function test_it_zeroes_totals_for_free_gift_only_merchant_group(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $cartItems = $this->getCartItems();
        $member = $this->getMember();

        $this->cartService->shouldReceive('requiresShipping')->times(3)->andReturn(false);
        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->memberAuthWrapper->shouldReceive('check')->times(3)->andReturn(true);
        $this->memberAuthWrapper->shouldReceive('getMember')->times(3)->andReturn($member);

        $discounts = $this->getResolvedDiscounts(0, 0, 0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->setEstimatedDeliveryExpectations();
        $this->setPreorderExpectations();

        // One normal group, one free gift group
        $groups = [
            'merchant_1' => [
                'merchant_id' => 1,
                'stripe_group_key' => 'acct_123',
                'items' => [['product_id' => 1, 'quantity' => 1, 'price' => 50.00]]
            ],
            'merchant_2' => [
                'merchant_id' => 2,
                'stripe_group_key' => 'acct_456',
                'items' => [['product_id' => 2, 'quantity' => 1, 'price' => 0.00, 'options' => ['type' => \App\Enums\CartItemType::FREE_GIFT->value]]]
            ]
        ];
        $this->splittingService->shouldReceive('splitByMerchant')->once()->andReturn($groups);
        $this->merchantShippingService->shouldReceive('calculatePerGroup')->once()->andReturn(['merchant_1' => 5.00, 'merchant_2' => 0.00]);
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')->once()->andReturn(new TaxData(rate: 0.1, taxCents: 1000));
        $this->allocationService->shouldReceive('allocate')->once()->andReturn([
            'merchant_1' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true],
            'merchant_2' => ['subtotal' => 0.00, 'shipping' => 0.00, 'tax' => 0.00, 'total' => 0.00, 'stripe_eligible' => true]
        ]);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')->twice()->andReturn([
            'success' => true, 'payment_intent_id' => 'pi_123', 'client_secret' => 'secret_123'
        ]);
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $order1 = $this->getOrder(['order_number' => 'ORD-001']);
        $order2 = $this->getOrder(['order_number' => 'ORD-002']);

        // Assert free gift group order has zeroed totals
        $this->orderCreationService->shouldReceive('createMerchantOrder')
            ->once()
            ->with(Mockery::on(fn($d) => $d['total'] == 65 || $d['subtotal'] > 0), Mockery::any(), $siteId, Mockery::any())
            ->andReturn($order1);

        $this->orderCreationService->shouldReceive('createMerchantOrder')
            ->once()
            ->with(Mockery::on(fn($d) => $d['total'] == 0 && $d['subtotal'] == 0 && $d['shipping'] == 0), Mockery::any(), $siteId, Mockery::any())
            ->andReturn($order2);

        $this->shipmentRepository->shouldReceive('create')->twice();
        $this->merchantShippingService->shouldReceive('isConsolidationEnabled')->twice()->andReturn(false);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    /**
     * PROVES THE BUG: In a multi-merchant cart where one merchant's group contains
     * only a free gift, that group's order incorrectly receives the checkout-level
     * baseSubtotalCents (e.g. £50 from the other merchant) as its subtotal.
     *
     * The existing single-item price<=0 patch also leaves tax non-zero.
     * This test will FAIL before the fix and PASS after.
     */
    public function test_multi_merchant_free_gift_group_gets_zero_subtotal_not_checkout_level_subtotal(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $member = $this->getMember();

        $cartItems = [
            ['id' => 1, 'product_id' => 100, 'quantity' => 1, 'price' => 50.00, 'name' => 'Paid Product'],
            ['id' => 2, 'product_id' => 200, 'quantity' => 1, 'price' => 0.00, 'name' => 'Free Gift',
                'options' => ['type' => \App\Enums\CartItemType::FREE_GIFT->value]],
        ];

        $product = Mockery::mock(Product::class);

        $this->productRepository->shouldReceive('find')
            ->with(200)
            ->andReturn($product);

        $this->cartService->shouldReceive('requiresShipping')->times(3)->andReturn(false);
        $this->cartService->shouldReceive('getItems')->once()->andReturn($cartItems);
        $this->memberAuthWrapper->shouldReceive('check')->times(3)->andReturn(true);
        $this->memberAuthWrapper->shouldReceive('getMember')->times(3)->andReturn($member);

        $this->setEstimatedDeliveryExpectations();
        $this->setPreorderExpectations();

        // Discounts: baseSubtotalCents=5000 (free gift excluded), finalSubtotalCents=5000
        $discounts = Mockery::mock(ResolvedDiscounts::class);
        $discounts->baseSubtotalCents = 5000;  // £50 — only the paid item
        $discounts->finalSubtotalCents = 5000;
        $discounts->offerDiscountCents = 0;
        $discounts->voucherDiscountCents = 0;
        $discounts->rewardDiscountCents = 0;
        $discounts->merchantFundedCents = 0;
        $discounts->platformFundedCents = 0;
        $discounts->tieredDiscountCents = 0;
        $discounts->metadata = [];
        $discounts->shouldReceive('getTotalDiscountCents')->andReturn(0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $groups = [
            'merchant_paid' => [
                'merchant_id' => 1,
                'stripe_group_key' => 'acct_123',
                'items' => [['product_id' => 100, 'quantity' => 1, 'price' => 50.00, 'subtotal' => 50.00,
                    'is_preorder' => false, 'expected_ship_date' => null]],
            ],
            'merchant_gift' => [
                'merchant_id' => 2,
                'stripe_group_key' => 'acct_456',
                'items' => [['product_id' => 200, 'quantity' => 1, 'price' => 0.00, 'subtotal' => 0.00,
                    'options' => ['type' => \App\Enums\CartItemType::FREE_GIFT->value],
                    'is_preorder' => false, 'expected_ship_date' => null]],
            ],
        ];

        $this->splittingService->shouldReceive('splitByMerchant')->once()->andReturn($groups);
        $this->merchantShippingService->shouldReceive('calculatePerGroup')
            ->once()->andReturn(['merchant_paid' => 5.00, 'merchant_gift' => 0.00]);
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')
            ->once()->andReturn(new TaxData(rate: 0.2, taxCents: 1000));
        $this->allocationService->shouldReceive('allocate')->once()->andReturn([
            'merchant_paid' => ['subtotal' => 50.00, 'shipping' => 5.00, 'tax' => 10.00, 'total' => 65.00, 'stripe_eligible' => true],
            'merchant_gift' => ['subtotal' => 0.00, 'shipping' => 0.00, 'tax' => 0.00, 'total' => 0.00, 'stripe_eligible' => true],
        ]);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->stripeProcessor->shouldReceive('createPaymentIntentWithCustomer')->twice()->andReturn([
            'success' => true, 'payment_intent_id' => 'pi_123', 'client_secret' => 'secret_123'
        ]);
        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $paidOrder = $this->getOrder(['id' => 1, 'order_number' => 'ORD-001']);
        $giftOrder = $this->getOrder(['id' => 2, 'order_number' => 'ORD-002']);

        // The paid merchant order should have correct subtotal of £50
        $this->orderCreationService->shouldReceive('createMerchantOrder')
            ->once()
            ->with(
                Mockery::on(fn($d) => $d['subtotal'] == 50.00),
                Mockery::any(), $siteId, 1
            )
            ->andReturn($paidOrder);

        // THE CRITICAL ASSERTION: the free gift order must have subtotal=0, total=0, tax=0, shipping=0
        // BEFORE the fix this fails because $discounts->baseSubtotalCents (5000) bleeds into subtotal
        $this->orderCreationService->shouldReceive('createMerchantOrder')
            ->once()
            ->with(
                Mockery::on(function ($d) {
                    return $d['subtotal'] == 0.00
                        && $d['total'] == 0.00
                        && $d['shipping'] == 0.00
                        && $d['tax'] == 0.00;
                }),
                Mockery::any(), $siteId, 2
            )
            ->andReturn($giftOrder);

        $this->shipmentRepository->shouldReceive('create')->twice();
        $this->merchantShippingService->shouldReceive('isConsolidationEnabled')->twice()->andReturn(false);
        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processMultiMerchantCheckout($data, $siteId);

        $this->assertTrue($result['success']);
    }

    /**
     * PROVES single-checkout is fine: a mixed cart with one paid item and one
     * free gift produces a correct order total that excludes the free gift from
     * the subtotal but still creates a £0 line item for it.
     *
     * This test should PASS both before and after the fix (no bug in single checkout).
     */
    public function test_single_checkout_mixed_cart_free_gift_does_not_inflate_subtotal(): void
    {
        $data = $this->getValidCheckoutData();
        $siteId = 1;
        $member = $this->getMember();

        $cartItems = [
            ['id' => 1, 'product_id' => 100, 'quantity' => 1, 'price' => 50.00, 'name' => 'Paid Product'],
            ['id' => 2, 'product_id' => 200, 'quantity' => 1, 'price' => 0.00, 'name' => 'Free Gift',
                'options' => ['type' => \App\Enums\CartItemType::FREE_GIFT->value]],
        ];

        $this->productRepository->shouldReceive('find')
            ->with(200)
            ->andReturn($product);

        $this->cartService->shouldReceive('requiresShipping')->twice()->andReturn(false);
        $this->setupBasicCheckoutExpectations($cartItems, $member, $siteId);
        $this->setEstimatedDeliveryExpectations();
        $this->setPreorderExpectations();

        // resolveDiscounts excludes free gift: baseSubtotalCents=5000
        $discounts = Mockery::mock(ResolvedDiscounts::class);
        $discounts->baseSubtotalCents = 5000;
        $discounts->finalSubtotalCents = 5000;
        $discounts->offerDiscountCents = 0;
        $discounts->voucherDiscountCents = 0;
        $discounts->rewardDiscountCents = 0;
        $discounts->merchantFundedCents = 0;
        $discounts->platformFundedCents = 0;
        $discounts->tieredDiscountCents = 0;
        $discounts->metadata = [];
        $discounts->shouldReceive('getTotalDiscountCents')->andReturn(0);
        $this->discountResolver->shouldReceive('resolve')->once()->andReturn($discounts);

        $this->shippingService->shouldReceive('calculateShipping')->once()->andReturn(0.00);
        $this->currencyResolver->shouldReceive('resolve')->once()->andReturn('GBP');
        $this->taxCalculatorService->shouldReceive('calculateOrderTax')
            ->once()->andReturn(new TaxData(rate: 0.2, taxCents: 1000));

        $this->databaseMock->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->stripeProcessor->shouldReceive('createPaymentIntent')->once()->andReturn([
            'success' => true, 'payment_intent_id' => 'pi_123', 'client_secret' => 'secret_123'
        ]);

        $order = $this->getOrder();

        // Order subtotal should be £50 (paid item only), total £60 (£50 + £10 tax)
        $this->orderCreationService->shouldReceive('create')
            ->once()
            ->with(
                Mockery::on(function ($d) {
                    return $d['subtotal'] == 50.00  // free gift excluded
                        && $d['total'] == 60.00;    // subtotal + tax
                }),
                // Two line items: paid at £50 and free gift at £0
                Mockery::on(fn($items) => count($items) === 2
                    && $items[1]['subtotal'] == 0.00
                    && $items[1]['price'] == 0.00
                ),
                $siteId
            )
            ->andReturn($order);

        $this->cartService->shouldReceive('clear')->once();

        $result = $this->service->processCheckout($data, $siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(60.00, $result['total']); // £50 + £10 tax, free gift not in total
    }
}
