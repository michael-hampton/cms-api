<?php

namespace App\Tests\Unit\Actions\Order;

use App\Actions\Order\BulkUpdateOrderStatus;
use App\Framework\Database\Database;
use App\Models\Order;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Members\OrderItemRepository;
use App\Repositories\Members\OrderRepository;
use App\Services\Members\OrderCalculationService;
use App\Services\Members\OrderHistoryService;
use App\Services\Members\OrderService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery as m;

class BulkUpdateOrderStatusAction extends FunctionalTestCase
{
    use HasSiteHistory;

    private $orderRepository;
    private $orderItemRepository;
    private $memberRepository;
    private $databaseMock;
    private BulkUpdateOrderStatus $service;
    private $addressRepository;
    private $orderCalculationService;

    private $historyService;

    private $orderService;

    protected function setUp(): void
    {
        parent::setUp(); // Call parent setup if it exists
        // Use Mockery::mock() instead of $this->createMock()
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->addressRepository = m::mock(AddressRepository::class);
        $this->orderCalculationService = m::mock(OrderCalculationService::class);
        $this->memberRepository = m::mock(MemberRepository::class);
        $this->orderItemRepository = m::mock(OrderItemRepository::class);
        $this->databaseMock = m::mock(Database::class);
        $this->historyService = m::mock(OrderHistoryService::class); // ADD THIS
        $this->orderService = m::mock(OrderService::class);

        $this->service = new BulkUpdateOrderStatus(
            $this->orderRepository,
            $this->databaseMock
        );
    }

    public function testBulkUpdateStatusSuccessfully(): void
    {
        $order1 = m::mock(Order::class)->makePartial();
        $order1->status = 'pending';
        $order1->completed_at = null;

        $order2 = m::mock(Order::class)->makePartial();
        $order2->status = 'pending';
        $order2->completed_at = null;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->orderRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($order1);

        $this->orderRepository->shouldReceive('find')
            ->with(2)
            ->once()
            ->andReturn($order2);

        $this->orderRepository->shouldReceive('update')
            ->twice()
            ->andReturn($order1, $order2);

        $result = $this->service->handle([1, 2], 'shipped');

        $this->assertCount(2, $result['updated']);
        $this->assertCount(0, $result['failed']);
        $this->assertEquals(2, $result['total']);
    }

    public function testBulkUpdateStatusHandlesNotFound(): void
    {
        $order1 = m::mock(Order::class)->makePartial();
        $order1->status = 'pending';

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

        $this->orderRepository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($order1);

        $this->orderRepository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->orderRepository->shouldReceive('update')
            ->once()
            ->andReturn($order1);

        $result = $this->service->handle([1, 999], 'shipped');

        $this->assertCount(1, $result['updated']);
        $this->assertCount(1, $result['failed']);
        $this->assertEquals('Order not found', $result['failed'][0]['reason']);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}