<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Order;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\Order\OrderAddressResolver;
use App\Services\Billing\Order\OrderCreationService;
use App\Services\Billing\Order\OrderEmailNotifier;
use App\Services\Billing\Order\OrderMemberResolver;
use App\Services\Billing\Order\OrderNumberGenerator;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\OrderHistoryService;
use Mockery;
use PHPUnit\Framework\TestCase;

class OrderCreationServiceTest extends TestCase
{
    private OrderRepository $orderRepository;
    private OrderItemRepository $orderItemRepository;
    private OrderMemberResolver $memberResolver;
    private OrderAddressResolver $addressResolver;
    private OrderCalculationService $calculationService;
    private OrderHistoryService $historyService;
    private OrderEmailNotifier $emailNotifier;
    private OrderNumberGenerator $numberGenerator;
    private Database $database;
    private OrderCreationService $service;

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
        $this->database = Mockery::mock(Database::class);

        $this->service = new OrderCreationService(
            $this->orderRepository,
            $this->orderItemRepository,
            $this->addressResolver,
            $this->calculationService,
            $this->historyService,
            $this->numberGenerator,
            $this->database,
            $this->memberResolver,
        );
    }

    public function test_it_creates_order_with_new_member()
    {
        //Event::fake();

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

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 456;

        // Mock transaction wrapper
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Mock member resolution (creates new member)
        $this->memberResolver->shouldReceive('resolve')
            ->once()
            ->with($data, 1)
            ->andReturn($member);

        // Mock address resolution
        $this->addressResolver->shouldReceive('resolveAddresses')
            ->once()
            ->with(Mockery::on(function ($arg) use ($member) {
                return $arg['user_id'] === $member->id;
            }), $member, 1);

        // Mock order number generation
        $this->numberGenerator->shouldReceive('generate')
            ->once()
            ->andReturn('ORD-12345');

        // Mock calculation
        $this->calculationService->shouldReceive('calculateOrderTotals')
            ->once()
            ->andReturn([
                'subtotal' => 200.00,
                'tax' => 20.00,
                'total' => 220.00,
            ]);

        // Mock order creation
        $this->orderRepository->shouldReceive('create')
            ->once()
            ->andReturn($order);

        // Mock item creation
        $this->orderItemRepository->shouldReceive('create')
            ->once();

        // Mock history logging
        $this->historyService->shouldReceive('logCreated')
            ->once();

        // Mock fetching order with relationships
        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($order->id)
            ->andReturn($order);

        // Execute
        $result = $this->service->create($data, $items, 1);

        // Assert
        $this->assertEquals($order, $result);
//        Event::assertDispatched(OrderCreatedEvent::class, function ($event) use ($order) {
//            return $event->order->id === $order->id
//                && $event->customerEmail === 'john@example.com';
//        });
    }

    public function test_it_creates_order_with_existing_member()
    {
        //Event::fake();

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

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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
        //Event::fake();

        $data = [
            'customer_name' => 'Guest User',
            'customer_email' => 'guest@example.com',
        ];

        $items = [
            ['product_id' => 1, 'quantity' => 1, 'unit_price' => 25.00]
        ];

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 999;

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Member resolver returns null for guest
        $this->memberResolver->shouldReceive('resolve')
            ->once()
            ->andReturn(null);

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
        //Event::fake([OrderCreatedEvent::class]);

        $data = [
            'customer_email' => 'merchant@example.com',
            'subtotal' => 500.00,
            'tax' => 50.00,
            'total' => 550.00, // Pre-calculated
        ];

        $items = [
            ['product_id' => 1, 'quantity' => 5, 'unit_price' => 100.00]
        ];

        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = 111;

        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->memberResolver->shouldReceive('resolve')->once()->andReturn(null);
        $this->addressResolver->shouldReceive('resolveAddresses')->once();
        $this->numberGenerator->shouldReceive('generate')->once()->andReturn('ORD-MERCHANT-001');

        // Should NOT call calculationService
        $this->calculationService->shouldNotReceive('calculateOrderTotals');

        $this->orderRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['total'] === 550.00 // Pre-calculated total preserved
                    && $arg['merchant_id'] === 999;
            }))
            ->andReturn($order);

        $this->orderItemRepository->shouldReceive('create')->once();
        $this->historyService->shouldReceive('logCreated')->once();
        $this->orderRepository->shouldReceive('getOrderById')->once()->andReturn($order);

        $result = $this->service->createMerchantOrder($data, $items, 1, 999);

        $this->assertEquals($order, $result);
        //Event::assertNotDispatched(OrderCreatedEvent::class); // No email for merchant orders
    }

    public function test_it_validates_item_data_before_creation()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Order item missing unit_price or quantity');

        $data = ['customer_email' => 'test@example.com'];
        $items = [
            ['product_id' => 1] // Missing unit_price and quantity
        ];

        $this->database->shouldReceive('transaction')
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}