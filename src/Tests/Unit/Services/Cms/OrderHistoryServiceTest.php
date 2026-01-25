<?php

namespace App\Tests\Unit\Services\Cms;

use App\Models\OrderHistory;
use App\Repositories\Members\OrderHistoryRepository;
use App\Services\Members\OrderHistoryService;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class OrderHistoryServiceTest extends TestCase
{
    private $repository;
    private OrderHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = m::mock(OrderHistoryRepository::class);
        $this->service = new OrderHistoryService($this->repository);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testLogCreated(): void
    {
        $orderId = 1;
        $data = ['status' => 'pending'];
        $userId = 10;

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId, $userId) {
                return $data['order_id'] === $orderId
                    && $data['action'] === 'created'
                    && $data['user_id'] === $userId
                    && isset($data['changes']['new_data']);
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logCreated($orderId, $data, $userId);

        $this->assertSame($mockHistory, $result);
    }

    public function testLogStatusChanged(): void
    {
        $orderId = 1;
        $oldStatus = 'pending';
        $newStatus = 'processing';
        $userId = 10;
        $notes = 'Test notes';

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId, $oldStatus, $newStatus, $userId, $notes) {
                return $data['order_id'] === $orderId
                    && $data['action'] === 'status_changed'
                    && $data['user_id'] === $userId
                    && $data['notes'] === $notes
                    && $data['changes']['old_status'] === $oldStatus
                    && $data['changes']['new_status'] === $newStatus;
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logStatusChanged($orderId, $oldStatus, $newStatus, $userId, $notes);

        $this->assertSame($mockHistory, $result);
    }

    public function testLogCancelled(): void
    {
        $orderId = 1;
        $userId = 10;
        $reason = 'Customer request';

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId, $userId, $reason) {
                return $data['order_id'] === $orderId
                    && $data['action'] === 'cancelled'
                    && $data['user_id'] === $userId
                    && $data['notes'] === $reason;
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logCancelled($orderId, $userId, $reason);

        $this->assertSame($mockHistory, $result);
    }

    public function testLogRefunded(): void
    {
        $orderId = 1;
        $userId = 10;
        $reason = 'Defective product';

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId, $userId, $reason) {
                return $data['order_id'] === $orderId
                    && $data['action'] === 'refunded'
                    && $data['user_id'] === $userId
                    && $data['notes'] === $reason;
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logRefunded($orderId, $userId, $reason);

        $this->assertSame($mockHistory, $result);
    }

    public function testLogUpdated(): void
    {
        $orderId = 1;
        $oldData = ['status' => 'pending', 'total' => 100];
        $newData = ['status' => 'processing', 'total' => 100];
        $userId = 10;

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId, $userId) {
                return $data['order_id'] === $orderId
                    && $data['action'] === 'updated'
                    && $data['user_id'] === $userId
                    && isset($data['changes']['status'])
                    && $data['changes']['status']['old'] === 'pending'
                    && $data['changes']['status']['new'] === 'processing';
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logUpdated($orderId, $oldData, $newData, $userId);

        $this->assertSame($mockHistory, $result);
    }

    public function testLogItemsUpdated(): void
    {
        $orderId = 1;
        $userId = 10;

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId, $userId) {
                return $data['order_id'] === $orderId
                    && $data['action'] === 'items_updated'
                    && $data['user_id'] === $userId;
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logItemsUpdated($orderId, $userId);

        $this->assertSame($mockHistory, $result);
    }

    public function testGetOrderHistory(): void
    {
        $orderId = 1;
        $mockCollection = m::mock(\App\Framework\Support\Collection::class);

        $this->repository->shouldReceive('getHistoryForOrder')
            ->once()
            ->with(1)
            ->andReturn($mockCollection);

        $result = $this->service->getOrderHistory($orderId);

        $this->assertSame($mockCollection, $result);
    }

    public function testLogUpdatedOnlyLogsChangedFields(): void
    {
        $orderId = 1;
        $oldData = ['status' => 'pending', 'total' => 100, 'notes' => 'old notes'];
        $newData = ['status' => 'pending', 'total' => 150, 'notes' => 'old notes'];
        $userId = 10;

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId, $userId) {
                // Should only have 'total' in changes, not 'status' or 'notes'
                return $data['order_id'] === $orderId
                    && $data['action'] === 'updated'
                    && $data['user_id'] === $userId
                    && isset($data['changes']['total'])
                    && !isset($data['changes']['status'])
                    && !isset($data['changes']['notes'])
                    && $data['changes']['total']['old'] === 100
                    && $data['changes']['total']['new'] === 150;
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logUpdated($orderId, $oldData, $newData, $userId);

        $this->assertSame($mockHistory, $result);
    }

    public function testLogUpdatedWithNoChanges(): void
    {
        $orderId = 1;
        $oldData = ['status' => 'pending', 'total' => 100];
        $newData = ['status' => 'pending', 'total' => 100];
        $userId = 10;

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId, $userId) {
                return $data['order_id'] === $orderId
                    && $data['action'] === 'updated'
                    && $data['user_id'] === $userId
                    && $data['changes'] === [];
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logUpdated($orderId, $oldData, $newData, $userId);

        $this->assertSame($mockHistory, $result);
    }

    public function testLogItemsUpdatedWithoutUser(): void
    {
        $orderId = 1;

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId) {
                return $data['order_id'] === $orderId
                    && $data['action'] === 'items_updated'
                    && $data['user_id'] === null;
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logItemsUpdated($orderId);

        $this->assertSame($mockHistory, $result);
    }

    public function testLogCancelledWithoutReason(): void
    {
        $orderId = 1;
        $userId = 10;

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId, $userId) {
                return $data['order_id'] === $orderId
                    && $data['action'] === 'cancelled'
                    && $data['user_id'] === $userId
                    && $data['notes'] === null;
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logCancelled($orderId, $userId);

        $this->assertSame($mockHistory, $result);
    }

    public function testLogCreatedWithoutUser(): void
    {
        $orderId = 1;
        $data = ['status' => 'pending', 'total' => 100];

        $mockHistory = m::mock(OrderHistory::class);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(m::on(function ($data) use ($orderId) {
                return $data['order_id'] === $orderId
                    && $data['action'] === 'created'
                    && $data['user_id'] === null
                    && isset($data['changes']['new_data']);
            }))
            ->andReturn($mockHistory);

        $result = $this->service->logCreated($orderId, $data);

        $this->assertSame($mockHistory, $result);
    }
}