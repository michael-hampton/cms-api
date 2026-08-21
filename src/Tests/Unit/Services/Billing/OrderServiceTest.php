<?php

namespace App\Tests\Unit\Services\Billing;

use App\Models\Order;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\OrderService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class OrderServiceTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private OrderRepository&MockInterface $orderRepository;
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->service = new OrderService($this->orderRepository);
    }

    public function test_get_order_by_id_delegates_to_repository(): void
    {
        $order = Mockery::mock(Order::class);

        $this->orderRepository->expects('getOrderById')->with(42)->andReturn($order);

        $this->assertSame($order, $this->service->getOrderById(42));
    }

    public function test_get_order_by_number_loads_relations_when_found(): void
    {
        $order = Mockery::mock(Order::class);
        $order->expects('load')->with(['items', 'user', 'item.product']);

        $this->orderRepository->expects('findByOrderNumber')->with('ORD-1')->andReturn($order);

        $this->assertSame($order, $this->service->getOrderByNumber('ORD-1'));
    }

    public function test_get_order_by_number_returns_null_when_not_found(): void
    {
        $this->orderRepository->expects('findByOrderNumber')->with('missing')->andReturn(null);

        $this->assertNull($this->service->getOrderByNumber('missing'));
    }

    public function test_search_for_crm_delegates_to_repository(): void
    {
        $this->orderRepository
            ->expects('searchForCrm')
            ->with(7, ['status' => 'pending'])
            ->andReturn(['results' => []]);

        $this->assertSame(
            ['results' => []],
            $this->service->searchForCrm(7, ['status' => 'pending'])
        );
    }

    public function test_get_orders_by_status_delegates_to_repository(): void
    {
        $collection = collect([]);
        $this->orderRepository->expects('getByStatus')->with('pending')->andReturn($collection);

        $this->assertSame($collection, $this->service->getOrdersByStatus('pending'));
    }

    public function test_get_orders_by_user_delegates_to_repository(): void
    {
        $collection = collect([]);
        $this->orderRepository->expects('getByUser')->with(5, 10)->andReturn($collection);

        $this->assertSame($collection, $this->service->getOrdersByUser(5, 10));
    }

    public function test_find_delegates_to_repository(): void
    {
        $order = Mockery::mock(Order::class);
        $this->orderRepository->expects('find')->with(99)->andReturn($order);

        $this->assertSame($order, $this->service->find(99));
    }
}
