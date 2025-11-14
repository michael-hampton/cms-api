<?php

namespace App\Tests\Unit\Models;

use App\Models\OrderHistory;
use App\Models\Order;
use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OrderHistoryModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testOrderHistoryHasCorrectTable()
    {
        $history = new OrderHistory();
        $this->assertEquals('order_history', $history->getTable());
    }

    public function testOrderHistoryBelongsToOrder()
    {
        $order = $this->createOrder();
        $history = OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'created',
            'changes' => ['new_data' => []],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->assertInstanceOf(Order::class, $history->order);
        $this->assertEquals($order->id, $history->order->id);
    }

    public function testOrderHistoryBelongsToUser()
    {
        $order = $this->createOrder();
        $member = $this->createMember();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'status_changed',
            'user_id' => $member->id,
            'changes' => ['old_status' => 'pending', 'new_status' => 'processing'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->assertInstanceOf(Member::class, $history->user);
        $this->assertEquals($member->id, $history->user->id);
    }

    public function testOrderHistoryCanBeCreatedWithoutUser()
    {
        $order = $this->createOrder();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'created',
            'user_id' => null,
            'changes' => ['new_data' => []],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->assertNull($history->user_id);
        $this->assertNull($history->user);
    }

    public function testOrderHistoryCastsChangesToArray()
    {
        $order = $this->createOrder();
        $changes = ['old_status' => 'pending', 'new_status' => 'processing'];

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'status_changed',
            'changes' => $changes,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->assertIsArray($history->changes);
        $this->assertEquals($changes, $history->changes);
    }

    public function testOrderHistoryCastsCreatedAtToDateTime()
    {
        $order = $this->createOrder();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'created',
            'changes' => [],
            'created_at' => '2024-01-15 10:30:00'
        ]);

        $this->assertInstanceOf(\DateTime::class, $history->created_at);
    }

    public function testToArrayIncludesUserName()
    {
        $order = $this->createOrder();
        $member = $this->createMember(['first_name' => 'John', 'last_name' => 'Doe']);

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'status_changed',
            'user_id' => $member->id,
            'changes' => [],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $history->load(['user']);
        $array = $history->toArray();

        $this->assertArrayHasKey('user_name', $array);
        $this->assertEquals('John Doe', $array['user_name']);
    }

    public function testToArrayIncludesSystemWhenNoUser()
    {
        $order = $this->createOrder();

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'created',
            'user_id' => null,
            'changes' => [],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $array = $history->toArray();

        $this->assertArrayHasKey('user_name', $array);
        $this->assertEquals('System', $array['user_name']);
    }

    public function testOrderHistoryStoresNotes()
    {
        $order = $this->createOrder();
        $notes = 'Customer requested cancellation';

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'cancelled',
            'notes' => $notes,
            'changes' => [],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->assertEquals($notes, $history->notes);
    }

    public function testOrderHistoryHasNoTimestamps()
    {
        $history = new OrderHistory();
        $this->assertFalse($history->timestamps);
    }

    public function testOrderHistoryRecordsMultipleChanges()
    {
        $order = $this->createOrder();
        $changes = [
            'status' => ['old' => 'pending', 'new' => 'processing'],
            'total' => ['old' => 100.00, 'new' => 150.00],
            'notes' => ['old' => '', 'new' => 'Updated notes']
        ];

        $history = OrderHistory::create([
            'order_id' => $order->id,
            'action' => 'updated',
            'changes' => $changes,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->assertCount(3, $history->changes);
        $this->assertEquals('pending', $history->changes['status']['old']);
        $this->assertEquals('processing', $history->changes['status']['new']);
    }
}