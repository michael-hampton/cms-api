<?php

namespace App\Tests\Unit\Repositories\Billing;

use App\Models\OrderHistory;
use App\Repositories\Billing\OrderHistoryRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class OrderHistoryRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private OrderHistoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new OrderHistoryRepository();
    }

    public function testGetByOrderIdReturnsHistory(): void
    {
        $order = $this->createOrder();

        OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'created',
            'changes' => ['new_data' => []],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'status_changed',
            'changes' => ['old_status' => 'pending', 'new_status' => 'processing'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $history = $this->repository->getHistoryForOrder($order->id);

        $this->assertCount(2, $history);
        $this->assertEquals('status_changed', $history->last()->action); // Most recent first
    }

    public function testGetRecentHistoryReturnsLimitedResults(): void
    {
        $order = $this->createOrder();

        for ($i = 0; $i < 10; $i++) {
            OrderHistory::create([
                'order_id' => $order->id,
                'action' => 'updated',
                'changes' => [],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        $history = $this->repository->getRecentHistory(5);

        $this->assertCount(5, $history);
    }
}