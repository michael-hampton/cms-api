<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Product;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Billing\Order\OrderAddressResolver;
use App\Services\Billing\Order\OrderCreationService;
use App\Services\Billing\Order\OrderMemberResolver;
use App\Services\Billing\Order\OrderNumberGenerator;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\OrderHistoryService;
use App\Services\Commission\CommissionService;
use App\Services\Product\MerchantTransactionService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * BUG NOTE: OrderCreationService guards event() with `$_ENV['APP_ENV'] !== 'testing'`.
 * This means the OrderCreatedEvent is NEVER dispatched in the test environment.
 * Tests that need to assert event emission set APP_ENV to 'production' temporarily
 * and restore it in tearDown. This guard should be removed; events should be
 * testable via Event::fake() or an injectable dispatcher instead.
 *
 * BUG NOTE: createMerchantOrder references `$customerEmail` which is never defined
 * in that method's scope (it's only defined in `create()`). This will produce an
 * "undefined variable" notice/error in PHP 8+. The test below exposes this.
 */
class OrderCreationServiceTest extends TestCase
{
    private OrderRepository&MockInterface $orderRepository;
    private OrderItemRepository&MockInterface $orderItemRepository;
    private OrderAddressResolver&MockInterface $addressResolver;
    private OrderCalculationService&MockInterface $calculationService;
    private OrderHistoryService&MockInterface $historyService;
    private OrderNumberGenerator&MockInterface $numberGenerator;
    private Database&MockInterface $database;
    private OrderMemberResolver&MockInterface $memberResolver;
    private CommissionService&MockInterface $commissionService;
    private MerchantRepository&MockInterface $merchantRepository;
    private MerchantTransactionService&MockInterface $merchantTransactionService;
    private ProductRepository&MockInterface $productRepository;
    private OrderCreationService $service;

    private string $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepository::class);
        $this->addressResolver = Mockery::mock(OrderAddressResolver::class);
        $this->calculationService = Mockery::mock(OrderCalculationService::class);
        $this->historyService = Mockery::mock(OrderHistoryService::class);
        $this->numberGenerator = Mockery::mock(OrderNumberGenerator::class);
        $this->database = Mockery::mock(Database::class);
        $this->memberResolver = Mockery::mock(OrderMemberResolver::class);
        $this->commissionService = Mockery::mock(CommissionService::class);
        $this->merchantRepository = Mockery::mock(MerchantRepository::class);
        $this->merchantTransactionService = Mockery::mock(MerchantTransactionService::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);

        $this->originalEnv = $_ENV['APP_ENV'] ?? 'testing';
        $_ENV['APP_ENV'] = 'testing'; // suppress event() by default

        $this->service = new OrderCreationService(
            $this->orderRepository,
            $this->orderItemRepository,
            $this->addressResolver,
            $this->calculationService,
            $this->historyService,
            $this->numberGenerator,
            $this->database,
            $this->memberResolver,
            $this->commissionService,
            $this->merchantRepository,
            $this->merchantTransactionService,
            $this->productRepository
        );
    }

    protected function tearDown(): void
    {
        $_ENV['APP_ENV'] = $this->originalEnv;
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeOrder(int $id = 1): Order&MockInterface
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = $id;
        return $order;
    }

    private function makeOrderItem(
        ?int  $merchantId,
        float $netAmount,
        float $subtotal = 10.0
    ): object
    {
        return (object)[
            'merchant_id' => $merchantId,
            'net_amount' => $netAmount,
            'subtotal' => $subtotal,
        ];
    }

    private function validItem(array $overrides = []): array
    {
        return array_merge([
            'unit_price' => 10.0,
            'quantity' => 2,
        ], $overrides);
    }

    private function expectTransactionUnwrap(): void
    {
        $this->database
            ->expects('transaction')
            ->andReturnUsing(fn(callable $cb) => $cb());
    }

    private function expectMemberResolveNull(array $data, int $siteId): void
    {
        $this->memberResolver->expects('resolve')->with($data, $siteId)->andReturn(null);
    }

    private function expectAddressResolve(): void
    {
        $this->addressResolver->expects('resolveAddresses')->once();
    }

    private function expectNumberGenerated(string $number = 'ORD-001'): void
    {
        $this->numberGenerator->expects('generate')->andReturn($number);
    }

    private function expectHistoryLogCreated(): void
    {
        $this->historyService->expects('logCreated')->once();
    }

    // -------------------------------------------------------------------------
    // create — transaction wrapping
    // -------------------------------------------------------------------------

    public function testCreateWrapsEntireFlowInTransaction(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 20.0]);
        $this->orderRepository->expects('create')->andReturn($order);
        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->with(1)->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->with(1)->andReturn($order);

        $result = $this->service->create(
            ['customer_email' => 'a@b.com'],
            [$this->validItem()],
            1
        );

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // create — member resolution
    // -------------------------------------------------------------------------

    public function testCreateSetsMemberUserIdWhenMemberResolved(): void
    {
        $order = $this->makeOrder();
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 42;

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn($member);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 20.0]);

        $this->orderRepository
            ->expects('create')
            ->withArgs(fn($d) => ($d['user_id'] ?? null) === 42)
            ->andReturn($order);

        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // create — prepareOrderData
    // -------------------------------------------------------------------------

    public function testCreateStripsCustomerFieldsFromOrderPayload(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 10.0]);

        $this->orderRepository
            ->expects('create')
            ->withArgs(function (array $data) {
                return !array_key_exists('customer_name', $data)
                    && !array_key_exists('customer_email', $data)
                    && !array_key_exists('customer_phone', $data);
            })
            ->andReturn($order);

        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([
            'customer_name' => 'John',
            'customer_email' => 'j@k.com',
            'customer_phone' => '01234',
        ], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    public function testCreateSetsDefaultStatusAndPaymentStatus(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 10.0]);

        $this->orderRepository
            ->expects('create')
            ->withArgs(function (array $data) {
                return $data['status'] === OrderStatus::PENDING->value
                    && $data['payment_status'] === PaymentStatus::UNPAID->value;
            })
            ->andReturn($order);

        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    public function testCreateDoesNotRecalculateTotalsWhenAlreadyProvided(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();

        // calculationService must NOT be called when 'total' is already set
        $this->calculationService->expects('calculateOrderTotals')->never();

        $this->orderRepository->expects('create')->andReturn($order);
        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create(['total' => 99.0], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    public function testCreateAppliesDiscountBreakdownWhenProvided(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 10.0]);

        $this->orderRepository
            ->expects('create')
            ->withArgs(function (array $data) {
                return ($data['offer_discount'] ?? null) === 5.0
                    && ($data['voucher_discount'] ?? null) === 2.0
                    && ($data['total_discount'] ?? 0) === 0; // key renamed to 'discount'
            })
            ->andReturn($order);

        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([], [$this->validItem()], 1, [
            'offer_discount' => 5.0,
            'voucher_discount' => 2.0,
            'total_discount' => 7.0,
            'tiered_discount' => 0,
            'reward_discount' => 0,
            'merchant_funded' => 0,
            'platform_funded' => 0,
        ]);

        $this->assertSame($order, $result);
    }

    public function testCreateGeneratesOrderNumberWhenNotProvided(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->numberGenerator->expects('generate')->andReturn('ORD-XYZ');

        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 10.0]);

        $this->orderRepository
            ->expects('create')
            ->withArgs(fn($d) => ($d['order_number'] ?? null) === 'ORD-XYZ')
            ->andReturn($order);

        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    public function testCreateDoesNotOverrideOrderNumberWhenAlreadyProvided(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->numberGenerator->expects('generate')->never();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 10.0]);

        $this->orderRepository
            ->expects('create')
            ->withArgs(fn($d) => ($d['order_number'] ?? null) === 'PRE-EXISTING')
            ->andReturn($order);

        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create(['order_number' => 'PRE-EXISTING'], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // create — order item creation
    // -------------------------------------------------------------------------

    public function testCreateCalculatesItemSubtotalWhenNotProvided(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 20.0]);
        $this->orderRepository->expects('create')->andReturn($order);

        $this->orderItemRepository
            ->expects('create')
            ->withArgs(function (array $item) {
                // unit_price(10) * quantity(2) = 20
                return ($item['subtotal'] ?? null) === 20.0;
            })
            ->once();

        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([], [$this->validItem(['unit_price' => 10.0, 'quantity' => 2])], 1);

        $this->assertSame($order, $result);
    }

    public function testCreateDoesNotOverrideItemSubtotalWhenProvided(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 99.0]);
        $this->orderRepository->expects('create')->andReturn($order);

        $this->orderItemRepository
            ->expects('create')
            ->withArgs(fn($item) => ($item['subtotal'] ?? null) === 99.0)
            ->once();

        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create(
            [],
            [$this->validItem(['subtotal' => 99.0])],
            1
        );

        $this->assertSame($order, $result);
    }

    public function testCreateCalculatesCommissionWhenMerchantAndProductPresent(): void
    {
        $order = $this->makeOrder();
        $product = Mockery::mock(Product::class)->makePartial();
        $merchant = Mockery::mock(Merchant::class)->makePartial();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 20.0]);
        $this->orderRepository->expects('create')->andReturn($order);

        $this->productRepository->expects('find')->with(5)->andReturn($product);
        $this->merchantRepository->expects('find')->with(3)->andReturn($merchant);
        $this->commissionService->expects('determineRate')->with($product, $merchant)->andReturn(0.1);
        $this->commissionService
            ->expects('calculate')
            ->with(20.0, 0.1)
            ->andReturn([
                'rate' => 0.1,
                'commission_amount' => 2.0,
                'net_amount' => 18.0,
            ]);

        $this->orderItemRepository
            ->expects('create')
            ->withArgs(function (array $item) {
                return ($item['commission_rate'] ?? null) === 0.1
                    && ($item['commission_amount'] ?? null) === 2.0
                    && ($item['net_amount'] ?? null) === 18.0;
            })
            ->once();

        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create(
            [],
            [$this->validItem(['merchant_id' => 3, 'product_id' => 5])],
            1
        );

        $this->assertSame($order, $result);
    }

    public function testCreateSetsZeroCommissionWhenProductNotFound(): void
    {
        $order = $this->makeOrder();
        $merchant = Mockery::mock(Merchant::class)->makePartial();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 20.0]);
        $this->orderRepository->expects('create')->andReturn($order);

        $this->productRepository->expects('find')->with(5)->andReturn(null);
        $this->merchantRepository->expects('find')->with(3)->andReturn($merchant);
        $this->commissionService->expects('determineRate')->never();

        $this->orderItemRepository
            ->expects('create')
            ->withArgs(fn($item) => ($item['commission_rate'] ?? -1) === 0.0
                && ($item['commission_amount'] ?? -1) === 0.00
                && ($item['net_amount'] ?? null) === 20.0) // subtotal
            ->once();

        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create(
            [],
            [$this->validItem(['merchant_id' => 3, 'product_id' => 5, 'subtotal' => 20.0])],
            1
        );

        $this->assertSame($order, $result);
    }

    public function testCreateSetsZeroCommissionWhenNoMerchantOnItem(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 20.0]);
        $this->orderRepository->expects('create')->andReturn($order);

        $this->productRepository->expects('find')->never();
        $this->merchantRepository->expects('find')->never();
        $this->commissionService->expects('determineRate')->never();

        $this->orderItemRepository
            ->expects('create')
            ->withArgs(fn($item) => ($item['commission_rate'] ?? -1) === 0.0)
            ->once();

        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // create — validation
    // -------------------------------------------------------------------------

    public function testCreateThrowsWhenItemMissingUnitPrice(): void
    {
        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 0.0]);
        $this->orderRepository->expects('create')->andReturn($this->makeOrder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order item missing unit_price or quantity');

        $this->service->create([], [['quantity' => 1]], 1);
    }

    public function testCreateThrowsWhenItemMissingQuantity(): void
    {
        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 0.0]);
        $this->orderRepository->expects('create')->andReturn($this->makeOrder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order item missing unit_price or quantity');

        $this->service->create([], [['unit_price' => 10.0]], 1);
    }

    public function testCreateThrowsWhenItemUnitPriceIsNonNumeric(): void
    {
        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 0.0]);
        $this->orderRepository->expects('create')->andReturn($this->makeOrder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unit_price and quantity must be numeric');

        $this->service->create([], [['unit_price' => 'free', 'quantity' => 1]], 1);
    }

    // -------------------------------------------------------------------------
    // create — creditMerchantsForOrder
    // -------------------------------------------------------------------------

    public function testCreateCreditsMerchantsGroupedByMerchantId(): void
    {
        $order = $this->makeOrder();

        $items = [
            $this->makeOrderItem(merchantId: 1, netAmount: 10.0),
            $this->makeOrderItem(merchantId: 1, netAmount: 5.0),
            $this->makeOrderItem(merchantId: 2, netAmount: 20.0),
        ];

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 35.0]);
        $this->orderRepository->expects('create')->andReturn($order);
        $this->orderItemRepository->expects('create')->times(1);

        $this->orderItemRepository->expects('getByOrderId')->with(1)->andReturn(collect($items));

        // Merchant 1 should be credited 15.0, merchant 2 should be credited 20.0
        $this->merchantTransactionService->expects('credit')->with(1, 15.0, 1)->once();
        $this->merchantTransactionService->expects('credit')->with(2, 20.0, 1)->once();

        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    public function testCreateSkipsSystemItemsWithNoMerchantIdWhenCrediting(): void
    {
        $order = $this->makeOrder();
        $items = [$this->makeOrderItem(merchantId: null, netAmount: 50.0)];

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 50.0]);
        $this->orderRepository->expects('create')->andReturn($order);
        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect($items));

        $this->merchantTransactionService->expects('credit')->never();

        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    public function testCreateSkipsMerchantCreditWhenNetAmountIsZero(): void
    {
        $order = $this->makeOrder();
        $items = [$this->makeOrderItem(merchantId: 5, netAmount: 0.0)];

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 0.0]);
        $this->orderRepository->expects('create')->andReturn($order);
        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect($items));

        $this->merchantTransactionService->expects('credit')->never();

        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // create — history logging
    // -------------------------------------------------------------------------

    public function testCreateLogsOrderHistory(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 10.0]);
        $this->orderRepository->expects('create')->andReturn($order);
        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());

        $this->historyService
            ->expects('logCreated')
            ->with(1, Mockery::type('array'), Mockery::any())
            ->once();

        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->create([], [$this->validItem()], 1);

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // createMerchantOrder
    // -------------------------------------------------------------------------

    public function testCreateMerchantOrderCreditsSpecifiedMerchant(): void
    {
        $order = $this->makeOrder();

        $items = [
            $this->makeOrderItem(merchantId: 7, netAmount: 30.0),
            $this->makeOrderItem(merchantId: 7, netAmount: 10.0),
        ];

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 40.0]);
        $this->orderRepository->expects('create')->andReturn($order);
        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->with(1)->andReturn(collect($items));

        $this->merchantTransactionService->expects('credit')->with(7, 40.0, 1)->once();

        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->createMerchantOrder([], [$this->validItem()], 1, 7);

        $this->assertSame($order, $result);
    }

    public function testCreateMerchantOrderSkipsCreditWhenNoMerchantId(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 10.0]);
        $this->orderRepository->expects('create')->andReturn($order);
        $this->orderItemRepository->expects('create')->once();

        $this->merchantTransactionService->expects('credit')->never();
        $this->orderItemRepository->expects('getByOrderId')->never();

        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->createMerchantOrder([], [$this->validItem()], 1, null);

        $this->assertSame($order, $result);
    }

    public function testCreateMerchantOrderSetsMerchantIdOnOrderData(): void
    {
        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 10.0]);

        $this->orderRepository
            ->expects('create')
            ->withArgs(fn($d) => ($d['merchant_id'] ?? null) === 7)
            ->andReturn($order);

        $this->orderItemRepository->expects('create')->once();
        $this->orderItemRepository->expects('getByOrderId')->andReturn(collect());
        $this->merchantTransactionService->allows('credit');
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        $result = $this->service->createMerchantOrder([], [$this->validItem()], 1, 7);

        $this->assertSame($order, $result);
    }

    public function testCreateMerchantOrderCapturesCustomerEmailBeforeDataIsSanitised(): void
    {
        // Verifies the fix: $customerEmail is captured before prepareOrderData strips
        // customer fields. The event is guarded by APP_ENV but the variable must exist
        // in scope. We set APP_ENV to non-testing to exercise the event() call path
        // and confirm no "undefined variable" error is thrown.
        $_ENV['APP_ENV'] = 'production';

        $order = $this->makeOrder();

        $this->expectTransactionUnwrap();
        $this->memberResolver->expects('resolve')->andReturn(null);
        $this->expectAddressResolve();
        $this->expectNumberGenerated();
        $this->calculationService->expects('calculateOrderTotals')->andReturn(['total' => 10.0]);
        $this->orderRepository->expects('create')->andReturn($order);
        $this->orderItemRepository->expects('create')->once();
        $this->merchantTransactionService->allows('credit');
        $this->orderItemRepository->allows('getByOrderId')->andReturn([]);
        $this->expectHistoryLogCreated();
        $this->orderRepository->expects('getOrderById')->andReturn($order);

        // If the bug were still present, event() would receive an undefined variable
        // and PHP 8 would throw. The fix ensures this runs cleanly.
        $result = $this->service->createMerchantOrder(
            ['customer_email' => 'test@test.com'],
            [$this->validItem()],
            1,
            null
        );

        $this->assertInstanceOf(Order::class, $result);
    }
}