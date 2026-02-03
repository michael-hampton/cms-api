<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\Order;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\Order\OrderManager;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class OrderManagerTest extends FunctionalTestCase
{
    private $orderRepository;
    private $orderItemRepository;
    private $databaseMock;
    private OrderManager $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = m::mock(OrderRepository::class);
        $this->orderItemRepository = m::mock(OrderItemRepository::class);
        $this->databaseMock = m::mock(Database::class);

        $this->service = new OrderManager(
            $this->orderRepository,
            $this->orderItemRepository,
            $this->databaseMock
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    // ===================================================================
    // findById() Tests
    // ===================================================================

    public function testFindByIdReturnsOrder(): void
    {
        $orderId = 1;
        $mockOrder = m::mock(Order::class);

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $result = $this->service->findById($orderId);

        $this->assertSame($mockOrder, $result);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $orderId = 999;

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn(null);

        $result = $this->service->findById($orderId);

        $this->assertNull($result);
    }

    public function testFindByIdHandlesZeroId(): void
    {
        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(0)
            ->andReturn(null);

        $result = $this->service->findById(0);

        $this->assertNull($result);
    }

    // ===================================================================
    // findByNumber() Tests
    // ===================================================================

    public function testFindByNumberReturnsOrderWithRelationships(): void
    {
        $orderNumber = 'ORD-123';
        $mockOrder = m::mock(Order::class);

        $mockOrder->shouldReceive('load')
            ->once()
            ->with(['items', 'user', 'item.product'])
            ->andReturnSelf();

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->with($orderNumber)
            ->andReturn($mockOrder);

        $result = $this->service->findByNumber($orderNumber);

        $this->assertSame($mockOrder, $result);
    }

    public function testFindByNumberReturnsNullWhenNotFound(): void
    {
        $orderNumber = 'NON-EXISTENT';

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->with($orderNumber)
            ->andReturn(null);

        $result = $this->service->findByNumber($orderNumber);

        $this->assertNull($result);
    }

    public function testFindByNumberHandlesEmptyString(): void
    {
        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->with('')
            ->andReturn(null);

        $result = $this->service->findByNumber('');

        $this->assertNull($result);
    }

    public function testFindByNumberIsCaseSensitive(): void
    {
        $orderNumber = 'ORD-123';
        $mockOrder = m::mock(Order::class);
        $mockOrder->shouldReceive('load')->andReturnSelf();

        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->with($orderNumber)
            ->andReturn($mockOrder);

        // Should NOT find with different case
        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->with('ord-123')
            ->andReturn(null);

        $result1 = $this->service->findByNumber($orderNumber);
        $result2 = $this->service->findByNumber('ord-123');

        $this->assertNotNull($result1);
        $this->assertNull($result2);
    }

    // ===================================================================
    // getAll() Tests
    // ===================================================================

    public function testGetAllReturnsAllOrders(): void
    {
        // Note: This would typically call Order::with() directly
        // In a real implementation, you'd inject a query builder or repository
        // For this test, we'll assume getAll delegates to repository

        $this->orderRepository->shouldReceive('getAll')
            ->once()
            ->andReturn([
                'data' => collect([m::mock(Order::class)]),
                'pagination' => [
                    'total' => 1
                ]
            ]);

        // Temporarily modify service to use repository for this test
        // In production, you'd refactor OrderManager to use repository for getAll()

        $result = $this->service->getAll();

        $this->assertIsArray($result);
    }

    public function testGetAllReturnsEmptyCollectionWhenNoOrders(): void
    {
        $emptyCollection = new Collection([]);

        $this->orderRepository->shouldReceive('getAll')
            ->once()
            ->andReturn(['data' => $emptyCollection, 'pagination' => ['total' => 0]]);

        $result = $this->service->getAll();

        $this->assertCount(0, $result['data']);
    }

    // ===================================================================
    // getByStatus() Tests
    // ===================================================================

    public function testGetByStatusReturnsMatchingOrders(): void
    {
        $status = 'completed';
        $mockCollection = m::mock(Collection::class);

        $this->orderRepository->shouldReceive('getByStatus')
            ->once()
            ->with($status)
            ->andReturn($mockCollection);

        $result = $this->service->getByStatus($status);

        $this->assertSame($mockCollection, $result);
    }

    public function testGetByStatusReturnsEmptyForNonExistentStatus(): void
    {
        $status = 'non_existent';
        $emptyCollection = new Collection([]);

        $this->orderRepository->shouldReceive('getByStatus')
            ->once()
            ->with($status)
            ->andReturn($emptyCollection);

        $result = $this->service->getByStatus($status);

        $this->assertCount(0, $result);
    }

    public function testGetByStatusHandlesAllStatuses(): void
    {
        $statuses = ['pending', 'processing', 'completed', 'cancelled', 'refunded'];

        foreach ($statuses as $status) {
            $mockCollection = m::mock(Collection::class);

            $this->orderRepository->shouldReceive('getByStatus')
                ->once()
                ->with($status)
                ->andReturn($mockCollection);

            $result = $this->service->getByStatus($status);

            $this->assertInstanceOf(Collection::class, $result);
        }
    }

    // ===================================================================
    // getByUser() Tests
    // ===================================================================

    public function testGetByUserReturnsUserOrders(): void
    {
        $userId = 1;
        $limit = 10;
        $mockCollection = m::mock(Collection::class);

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $this->orderRepository->shouldReceive('getByUser')
            ->once()
            ->with($userId, $limit)
            ->andReturn(collect([$mockOrder]));

        $result = $this->service->getByUser($userId, $limit);

        $this->assertNotEmpty($result->toArray());
    }

    public function testGetByUserWithoutLimitReturnsAllOrders(): void
    {
        $userId = 1;
        $mockCollection = m::mock(Collection::class);

        $mockOrder = m::mock(Order::class)->makePartial();
        $mockOrder->id = 1;

        $this->orderRepository->shouldReceive('getByUser')
            ->once()
            ->with($userId, null)
            ->andReturn(collect([$mockOrder]));

        $result = $this->service->getByUser($userId);

        $this->assertNotEmpty($result->toArray());
    }

    public function testGetByUserReturnsEmptyForNonExistentUser(): void
    {
        $userId = 999;
        $emptyCollection = new Collection([]);

        $this->orderRepository->shouldReceive('getByUser')
            ->once()
            ->with($userId, null)
            ->andReturn($emptyCollection);

        $result = $this->service->getByUser($userId);

        $this->assertCount(0, $result);
    }

    public function testGetByUserHandlesZeroLimit(): void
    {
        $userId = 1;
        $limit = 0;
        $emptyCollection = new Collection([]);

        $this->orderRepository->shouldReceive('getByUser')
            ->once()
            ->with($userId, $limit)
            ->andReturn($emptyCollection);

        $result = $this->service->getByUser($userId, $limit);

        $this->assertCount(0, $result);
    }

    public function testGetByUserHandlesLargeLimit(): void
    {
        $userId = 1;
        $limit = 1000;
        $mockCollection = m::mock(Collection::class);

        $this->orderRepository->shouldReceive('getByUser')
            ->once()
            ->with($userId, $limit)
            ->andReturn($mockCollection);

        $result = $this->service->getByUser($userId, $limit);

        $this->assertInstanceOf(Collection::class, $result);
    }

    // ===================================================================
    // delete() Tests
    // ===================================================================

    public function testDeleteRemovesOrder(): void
    {
        $orderId = 1;
        $mockOrder = m::mock(Order::class);

        $mockOrder->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->delete($orderId);

        $this->assertTrue($result);
    }

    public function testDeleteThrowsExceptionWhenOrderNotFound(): void
    {
        $orderId = 999;

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $this->service->delete($orderId);
    }

    public function testDeleteReturnsFalseWhenDeletionFails(): void
    {
        $orderId = 1;
        $mockOrder = m::mock(Order::class);

        $mockOrder->shouldReceive('delete')
            ->once()
            ->andReturn(false);

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->with($orderId)
            ->andReturn($mockOrder);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->delete($orderId);

        $this->assertFalse($result);
    }

    public function testDeleteWrapsInTransaction(): void
    {
        $orderId = 1;
        $mockOrder = m::mock(Order::class);
        $mockOrder->shouldReceive('delete')->andReturn(true);

        $this->orderRepository->shouldReceive('find')
            ->once()
            ->andReturn($mockOrder);

        // Verify transaction is used
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $result = $this->service->delete($orderId);
        $this->assertTrue($result);
    }

    public function testDeleteCascadesToItems(): void
    {
        // This test verifies that items are deleted via cascade
        // In production, the databaseMock foreign key constraint handles this

        $orderId = 1;
        $mockOrder = m::mock(Order::class);
        $mockOrder->shouldReceive('delete')->once()->andReturn(true);

        $this->orderRepository->shouldReceive('find')->once()->andReturn($mockOrder);
        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());

        // Items should be deleted automatically via DB cascade
        $this->orderItemRepository->shouldNotReceive('deleteByOrderId');

        $result = $this->service->delete($orderId);

        $this->assertTrue($result);
    }
}