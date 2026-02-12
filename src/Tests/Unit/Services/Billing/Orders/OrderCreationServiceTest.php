<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Billing\Order\OrderAddressResolver;
use App\Services\Billing\Order\OrderCreationService;
use App\Services\Billing\Order\OrderEmailNotifier;
use App\Services\Billing\Order\OrderMemberResolver;
use App\Services\Billing\Order\OrderNumberGenerator;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\OrderHistoryService;
use App\Services\Commission\CommissionService;
use App\Services\Product\MerchantTransactionService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class OrderCreationServiceTest extends FunctionalTestCase
{
    private OrderRepository $orderRepository;
    private OrderItemRepository $orderItemRepository;
    private OrderMemberResolver $memberResolver;
    private OrderAddressResolver $addressResolver;
    private OrderCalculationService $calculationService;
    private OrderHistoryService $historyService;
    private OrderEmailNotifier $emailNotifier;
    private OrderNumberGenerator $numberGenerator;
    private Database $databaseMock;
    private OrderCreationService $service;
    private readonly CommissionService $commissionService;
    private readonly MerchantRepository $merchantRepository;
    private MerchantTransactionService $merchantTransactionService;
    private ProductRepository $productRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->orderItemRepository = Mockery::mock(OrderItemRepository::class);
        $this->memberResolver = Mockery::mock(OrderMemberResolver::class);
        $this->addressResolver = Mockery::mock(OrderAddressResolver::class);
        $this->calculationService = Mockery::mock(OrderCalculationService::class);
        $this->historyService = Mockery::mock(OrderHistoryService::class);
        $this->emailNotifier = Mockery::mock(OrderEmailNotifier::class);
        $this->numberGenerator = Mockery::mock(OrderNumberGenerator::class);
        $this->commissionService = Mockery::mock(CommissionService::class);
        $this->merchantRepository = Mockery::mock(MerchantRepository::class);
        $this->merchantTransactionService = Mockery::mock(MerchantTransactionService::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new OrderCreationService(
            $this->orderRepository,
            $this->orderItemRepository,
            $this->addressResolver,
            $this->calculationService,
            $this->historyService,
            $this->numberGenerator,
            $this->databaseMock,
            $this->memberResolver,
            $this->commissionService,
            $this->merchantRepository,
            $this->merchantTransactionService,
            $this->productRepository,
        );
    }

    public function test_it_creates_order_with_new_member()
    {
        $data = [
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '1234567890',
        ];

        $items = [
            [
                'product_id' => 1,
                'quantity' => 2,
                'unit_price' => 100.00,
            ]
        ];

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;

        $this->actingAsMember($member);
        $this->setSendOrderEmailExpectations();

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 456;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderItemRepository->shouldReceive('getByOrderId')
            ->with(456)
            ->once()->andReturn(collect([Mockery::mock(OrderItem::class)->makePartial()]));

        $this->memberResolver->shouldReceive('resolve')
            ->once()
            ->with($data, 1)
            ->andReturn($member);

        $this->addressResolver->shouldReceive('resolveAddresses')
            ->once()
            ->with(Mockery::on(function ($arg) use ($member) {
                return $arg['user_id'] === $member->id;
            }), $member, 1);

        $this->numberGenerator->shouldReceive('generate')
            ->once()
            ->andReturn('ORD-12345');

        $this->calculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->andReturn([
                'subtotal' => 200.00,
                'tax' => 20.00,
                'total' => 220.00,
            ]);

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->andReturn($order);

        $this->orderItemRepository->shouldReceive('create')
            ->once();

        $this->historyService->shouldReceive('logCreated')
            ->once();

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($order->id)
            ->andReturn($order);

        $result = $this->service->create($data, $items, 1);

        $this->assertEquals($order, $result);
    }

    public function test_it_creates_order_with_existing_member()
    {
        $data = [
            'user_id' => 123,
            'customer_email' => 'existing@example.com',
        ];

        $items = [
            ['product_id' => 1, 'quantity' => 1, 'unit_price' => 50.00]
        ];

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 123;

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 789;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderItemRepository->shouldReceive('getByOrderId')
            ->with(789)
            ->once()->andReturn(collect([Mockery::mock(OrderItem::class)->makePartial()]));

        $this->memberResolver->shouldReceive('resolve')
            ->once()
            ->with($data, 1)
            ->andReturn($member);

        $this->addressResolver->shouldReceive('resolveAddresses')->once();
        $this->numberGenerator->shouldReceive('generate')->once()->andReturn('ORD-67890');
        $this->calculationService->shouldReceive('calculateOrderTotals')->once()->andReturn([
            'subtotal' => 50.00,
            'tax' => 5.00,
            'total' => 55.00,
        ]);
        $this->orderRepository->shouldReceive('create')->once()->andReturn($order);
        $this->orderItemRepository->shouldReceive('create')->once();
        $this->historyService->shouldReceive('logCreated')->once();
        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($order);

        $result = $this->service->create($data, $items, 1);

        $this->assertEquals($order, $result);
    }

    public function test_it_creates_order_for_guest()
    {
        $data = [
            'customer_name' => 'Guest User',
            'customer_email' => 'guest@example.com',
        ];

        $items = [
            ['product_id' => 1, 'quantity' => 1, 'unit_price' => 25.00]
        ];

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 999;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->memberResolver->shouldReceive('resolve')
            ->once()
            ->andReturn(null);

        $this->orderItemRepository->shouldReceive('getByOrderId')
            ->with(999)
            ->once()->andReturn(collect([Mockery::mock(OrderItem::class)->makePartial()]));

        $this->addressResolver->shouldReceive('resolveAddresses')->once();
        $this->numberGenerator->shouldReceive('generate')->once()->andReturn('ORD-GUEST-001');
        $this->calculationService->shouldReceive('calculateOrderTotals')->once()->andReturn([
            'subtotal' => 25.00,
            'tax' => 2.50,
            'total' => 27.50,
        ]);
        $this->orderRepository->shouldReceive('create')->once()->andReturn($order);
        $this->orderItemRepository->shouldReceive('create')->once();
        $this->historyService->shouldReceive('logCreated')->once();
        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($order);

        $result = $this->service->create($data, $items, 1);

        $this->assertEquals($order, $result);
    }

    public function test_it_creates_merchant_order_without_recalculating_totals()
    {
        $data = [
            'customer_email' => 'merchant@example.com',
            'subtotal' => 500.00,
            'tax' => 50.00,
            'total' => 550.00,
        ];

        $items = [
            ['product_id' => 1, 'quantity' => 5, 'unit_price' => 100.00]
        ];

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 111;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderItemRepository->shouldReceive('getByOrderId')
            ->with(111)
            ->once()->andReturn(collect([Mockery::mock(OrderItem::class)->makePartial()]));
        $this->memberResolver->shouldReceive('resolve')->once()->andReturn(null);
        $this->addressResolver->shouldReceive('resolveAddresses')->once();
        $this->numberGenerator->shouldReceive('generate')->once()->andReturn('ORD-MERCHANT-001');

        $this->calculationService->shouldNotReceive('calculateOrderTotals');

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['total'] === 550.00
                    && $arg['merchant_id'] === 999;
            }))
            ->andReturn($order);

        $this->orderItemRepository->shouldReceive('create')->once();
        $this->historyService->shouldReceive('logCreated')->once();
        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($order);

        $result = $this->service->createMerchantOrder($data, $items, 1, 999);

        $this->assertEquals($order, $result);
    }

    public function test_it_validates_item_data_before_creation()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order item missing unit_price or quantity');

        $data = ['customer_email' => 'test@example.com'];
        $items = [
            ['product_id' => 1]
        ];

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->memberResolver->shouldReceive('resolve')->once()->andReturn(null);
        $this->addressResolver->shouldReceive('resolveAddresses')->once();
        $this->numberGenerator->shouldReceive('generate')->once()->andReturn('ORD-001');
        $this->calculationService->shouldReceive('calculateOrderTotals')->once()->andReturn([
            'total' => 0
        ]);
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 1;
        $this->orderRepository->shouldReceive('create')->once()->andReturn($order);

        $this->service->create($data, $items, 1);
    }

    public function test_it_calculates_and_snapshots_commission_on_order_items()
    {
        $data = [
            'customer_email' => 'test@example.com',
        ];

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->merchant_id = 100;

        $items = [
            [
                'product_id' => 1,
                'merchant_id' => 100,
                'quantity' => 2,
                'unit_price' => 100.00,
                'subtotal' => 200.00,
            ]
        ];

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 456;

        $item = Mockery::mock(OrderItem::class)->makePartial();

        $this->orderItemRepository->shouldReceive('getByOrderId')->once()->andReturn(collect([$item]));

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $merchant = Mockery::mock(Merchant::class)->makePartial();

        $this->merchantRepository->shouldReceive('find')
            ->once()
            ->with(100)
            ->andReturn($merchant);

        $this->commissionService->shouldReceive('determineRate')
            ->once()
            ->with($product, $merchant)
            ->andReturn(0.10);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->memberResolver->shouldReceive('resolve')->once()->andReturn(null);
        $this->addressResolver->shouldReceive('resolveAddresses')->once();
        $this->numberGenerator->shouldReceive('generate')->once()->andReturn('ORD-12345');
        $this->calculationService->shouldReceive('calculateOrderTotals')->once()->andReturn([
            'subtotal' => 200.00,
            'total' => 200.00,
        ]);

        // CRITICAL: Commission service should be called
        $this->commissionService->shouldReceive('calculate')
            ->once()
            ->with(200.00, 0.1)
            ->andReturn([
                'rate' => 0.10,
                'commission_amount' => 20.00,
                'net_amount' => 180.00,
            ]);

        $this->orderRepository->shouldReceive('create')->once()->andReturn($order);

        // CRITICAL: Order item should be created with commission fields
        $this->orderItemRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($itemData) {
                return $itemData['commission_rate'] === 0.1
                    && $itemData['commission_amount'] === 20.00
                    && $itemData['net_amount'] === 180.00;
            }));

        $this->historyService->shouldReceive('logCreated')->once();
        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($order);

        $result = $this->service->create($data, $items, 1);

        $this->assertEquals($order, $result);
    }

    public function test_it_credits_merchant_with_net_amount_after_order_creation()
    {
        $data = [
            'customer_email' => 'test@example.com',
        ];

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->merchant_id = 100;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product);

        $merchant = Mockery::mock(Merchant::class)->makePartial();
        $merchant->id = 100;

        $this->commissionService->shouldReceive('determineRate')
            ->once()
            ->with($product, $merchant)
            ->andReturn(0.10);

        $items = [
            [
                'product_id' => 1,
                'merchant_id' => 100,
                'quantity' => 1,
                'unit_price' => 100.00,
                'subtotal' => 100.00,
            ]
        ];

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 456;

        $orderItem = Mockery::mock(OrderItem::class)->makePartial();
        $orderItem->id = 789;
        $orderItem->merchant_id = 100;
        $orderItem->net_amount = 90.00;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->memberResolver->shouldReceive('resolve')->once()->andReturn(null);
        $this->addressResolver->shouldReceive('resolveAddresses')->once();
        $this->numberGenerator->shouldReceive('generate')->once()->andReturn('ORD-12345');
        $this->calculationService->shouldReceive('calculateOrderTotals')->once()->andReturn([
            'subtotal' => 100.00,
            'total' => 100.00,
        ]);

        $this->commissionService->shouldReceive('calculate')
            ->once()
            ->andReturn([
                'rate' => 0.10,
                'amount' => 10.00,
                'net_amount' => 90.00,
            ]);

        $this->orderRepository->shouldReceive('create')->once()->andReturn($order);
        $this->orderItemRepository->shouldReceive('create')->once();
        $this->historyService->shouldReceive('logCreated')->once();

        // CRITICAL: Should fetch order items to get net amounts
        $this->orderItemRepository->shouldReceive('getByOrderId')
            ->once()
            ->with($order->id)
            ->andReturn(collect([$orderItem]));

        // CRITICAL: Should fetch merchant
        $this->merchantRepository->shouldReceive('find')
            ->once()
            ->with(100)
            ->andReturn($merchant);

        // CRITICAL: Should credit merchant with NET amount (not gross)
        $this->merchantTransactionService->shouldReceive('credit')
            ->once()
            ->with(
                100,
                90,  // Net amount only
                456
            );

        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($order);

        $result = $this->service->create($data, $items, 1);

        $this->assertEquals($order, $result);
    }

    public function test_it_handles_multiple_merchants_in_single_order()
    {
        $data = [
            'customer_email' => 'test@example.com',
        ];

        $product1 = Mockery::mock(Product::class)->makePartial();
        $product1->id = 1;
        $product1->merchant_id = 100;

        $product2 = Mockery::mock(Product::class)->makePartial();
        $product2->id = 2;
        $product2->merchant_id = 200;

        $merchant1 = Mockery::mock(Merchant::class)->makePartial();
        $merchant1->id = 100;

        $merchant2 = Mockery::mock(Merchant::class)->makePartial();
        $merchant2->id = 200;

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($product1);

        $this->productRepository->shouldReceive('find')
            ->once()
            ->with(2)
            ->andReturn($product2);

        $items = [
            [
                'product_id' => 1,
                'merchant_id' => 100,
                'quantity' => 1,
                'unit_price' => 100.00,
                'subtotal' => 100.00,
            ],
            [
                'product_id' => 2,
                'merchant_id' => 200,
                'quantity' => 1,
                'unit_price' => 50.00,
                'subtotal' => 50.00,
            ]
        ];

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 456;

        $orderItem1 = Mockery::mock(OrderItem::class)->makePartial();
        $orderItem1->merchant_id = 100;
        $orderItem1->net_amount = 90.00;

        $orderItem2 = Mockery::mock(OrderItem::class)->makePartial();
        $orderItem2->merchant_id = 200;
        $orderItem2->net_amount = 45.00;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->memberResolver->shouldReceive('resolve')->once()->andReturn(null);
        $this->addressResolver->shouldReceive('resolveAddresses')->once();
        $this->numberGenerator->shouldReceive('generate')->once()->andReturn('ORD-12345');
        $this->calculationService->shouldReceive('calculateOrderTotals')->once()->andReturn([
            'subtotal' => 150.00,
            'total' => 150.00,
        ]);

        // Commission calculated for both products
        $this->commissionService->shouldReceive('calculate')
            ->twice()
            ->andReturn(
                [
                    'rate' => 0.10,
                    'amount' => 10.00,
                    'net_amount' => 90.00,
                ],
                [
                    'rate' => 0.10,
                    'amount' => 5.00,
                    'net_amount' => 45.00,
                ]
            );

        $this->orderRepository->shouldReceive('create')->once()->andReturn($order);
        $this->orderItemRepository->shouldReceive('create')->twice();
        $this->historyService->shouldReceive('logCreated')->once();

        $this->commissionService->shouldReceive('determineRate')
            ->once()
            ->with($product1, $merchant1)
            ->andReturn(0.10);

        $this->commissionService->shouldReceive('determineRate')
            ->once()
            ->with($product2, $merchant2)
            ->andReturn(0.10);

        $this->orderItemRepository->shouldReceive('getByOrderId')
            ->once()
            ->andReturn(collect([$orderItem1, $orderItem2]));

        // CRITICAL: Both merchants should be credited separately
        $this->merchantRepository->shouldReceive('find')
            ->once()
            ->with(100)
            ->andReturn($merchant1);

        $this->merchantRepository->shouldReceive('find')
            ->once()
            ->with(200)
            ->andReturn($merchant2);

        $this->merchantTransactionService->shouldReceive('credit')
            ->once()
            ->with(100, 90, 456);

        $this->merchantTransactionService->shouldReceive('credit')
            ->once()
            ->with(200, 45, 456);

        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($order);

        $result = $this->service->create($data, $items, 1);

        $this->assertEquals($order, $result);
    }

    public function test_it_skips_commission_for_system_items_without_merchant()
    {
        $data = [
            'customer_email' => 'test@example.com',
        ];

        $items = [
            [
                'product_id' => null,  // System item
                'merchant_id' => null, // No merchant
                'product_name' => 'Shipping Fee',
                'quantity' => 1,
                'unit_price' => 10.00,
                'subtotal' => 10.00,
            ]
        ];

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 456;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->memberResolver->shouldReceive('resolve')->once()->andReturn(null);
        $this->addressResolver->shouldReceive('resolveAddresses')->once();
        $this->numberGenerator->shouldReceive('generate')->once()->andReturn('ORD-12345');
        $this->calculationService->shouldReceive('calculateOrderTotals')->once()->andReturn([
            'subtotal' => 10.00,
            'total' => 10.00,
        ]);

        // CRITICAL: Commission service should NOT be called for system items
        $this->commissionService->shouldNotReceive('calculate');

        $this->orderRepository->shouldReceive('create')->once()->andReturn($order);

        // CRITICAL: Item created with zero commission
        $this->orderItemRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($itemData) {
                return $itemData['commission_rate'] === 0.0
                    && $itemData['commission_amount'] === 0.0
                    && $itemData['net_amount'] === 10.00; // Full amount
            }));

        $this->historyService->shouldReceive('logCreated')->once();

        // No merchant transactions expected
        $this->orderItemRepository->shouldReceive('getByOrderId')
            ->once()
            ->andReturn(collect([]));

        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($order);

        $result = $this->service->create($data, $items, 1);

        $this->assertEquals($order, $result);
    }

    public function test_it_uses_discount_breakdown_when_provided()
    {
        $data = [
            'customer_email' => 'test@example.com',
        ];

        $items = [
            [
                'product_id' => 1,
                'quantity' => 1,
                'unit_price' => 100.00,
            ]
        ];

        $discountBreakdown = [
            'offer_discount' => 10.00,
            'tiered_discount' => 5.00,
            'voucher_discount' => 3.00,
            'reward_discount' => 2.00,
            'total_discount' => 20.00,
            'merchant_funded' => 13.00,
            'platform_funded' => 7.00,
        ];

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 456;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->memberResolver->shouldReceive('resolve')->once()->andReturn(null);
        $this->addressResolver->shouldReceive('resolveAddresses')->once();
        $this->numberGenerator->shouldReceive('generate')->once()->andReturn('ORD-12345');
        $this->calculationService->shouldReceive('calculateOrderTotals')->once()->andReturn([
            'total' => 100.00,
        ]);

        // CRITICAL: Order created with discount breakdown fields
        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($orderData) {
                return $orderData['offer_discount'] == 10
                    && $orderData['tiered_discount'] == 5
                    && $orderData['voucher_discount'] == 3
                    && $orderData['reward_discount'] == 2
                    && $orderData['discount'] == 20
                    && $orderData['merchant_funded'] == 13
                    && $orderData['platform_funded'] == 7;
            }))
            ->andReturn($order);

        $this->orderItemRepository->shouldReceive('create')->once();
        $this->historyService->shouldReceive('logCreated')->once();
        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($order);
        $this->orderItemRepository->shouldReceive('getByOrderId')
            ->with(456)
            ->once()->andReturn(collect([Mockery::mock(OrderItem::class)->makePartial()]));

        $result = $this->service->create($data, $items, 1, $discountBreakdown);

        $this->assertEquals($order, $result);
    }

    private function setSendOrderEmailExpectations()
    {
        $this->emailNotifier->shouldReceive('sendConfirmation');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}