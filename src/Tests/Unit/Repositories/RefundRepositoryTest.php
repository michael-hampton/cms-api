<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Refund;
use App\Models\RefundItem;
use App\Repositories\Members\RefundRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RefundRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private RefundRepository $repository;

    public function testSearchReturnsPaginatedResults(): void
    {
        $order = $this->createOrder(['status' => 'completed', 'payment_status' => 'paid']);
        $this->createRefund(['order_id' => $order->id]);

        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
        $this->assertGreaterThan(0, count($result->getData()));
    }

    protected function createRefund(array $overrides = []): Refund
    {
        return Refund::create(array_merge([
            'order_id' => 1,
            'refund_type' => 'full',
            'refund_amount' => 100.00,
            'reason' => 'customer_request',
            'status' => 'pending',
            'site_id' => $this->siteId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $overrides));
    }

    public function testFindByOrderIdReturnsRefunds(): void
    {
        $order = $this->createOrder(['status' => 'completed', 'payment_status' => 'paid']);

        $refund1 = $this->createRefund([
            'order_id' => $order->id,
            'refund_amount' => 50.00
        ]);

        $refund2 = $this->createRefund([
            'order_id' => $order->id,
            'refund_amount' => 30.00
        ]);

        $refunds = $this->repository->findByOrderId($order->id);

        $this->assertCount(2, $refunds);
        $this->assertEquals($order->id, $refunds->first()->order_id);
        $this->assertEquals($order->id, $refunds->last()->order_id);
    }

    public function testGetByStatusReturnsRefundsWithStatus(): void
    {
        $order1 = $this->createOrder(['status' => 'completed', 'payment_status' => 'paid']);
        $order2 = $this->createOrder(['status' => 'completed', 'payment_status' => 'paid']);

        $this->createRefund([
            'order_id' => $order1->id,
            'status' => 'pending'
        ]);

        $this->createRefund([
            'order_id' => $order2->id,
            'status' => 'processed'
        ]);

        $pendingRefunds = $this->repository->getByStatus('pending');

        $this->assertGreaterThanOrEqual(1, $pendingRefunds->count());
        foreach ($pendingRefunds as $refund) {
            $this->assertEquals('pending', $refund->status);
        }
    }

    public function testGetTotalRefundedAmountCalculatesCorrectly(): void
    {
        $order = $this->createOrder([
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 200.00
        ]);

        $this->createRefund([
            'order_id' => $order->id,
            'refund_amount' => 50.00,
            'status' => 'processed'
        ]);

        $this->createRefund([
            'order_id' => $order->id,
            'refund_amount' => 30.00,
            'status' => 'processed'
        ]);

        // This should not be counted
        $this->createRefund([
            'order_id' => $order->id,
            'refund_amount' => 20.00,
            'status' => 'pending'
        ]);

        $total = $this->repository->getTotalRefundedAmount($order->id);

        $this->assertEquals(80.00, $total);
    }

    public function testCreateRefundItemCreatesItem(): void
    {
        $order = $this->createOrder(['status' => 'completed', 'payment_status' => 'paid']);
        $refund = $this->createRefund(['order_id' => $order->id]);
        $product = $this->createProduct();
        $orderItem = $this->createOrderItem($order->id);

        $itemData = [
            'refund_id' => $refund->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'order_item_id' => $orderItem->id,
            'quantity' => 2,
            'refund_quantity' => 1,
            'unit_price' => 100.00,
            'refund_amount' => 100.00
        ];

        $item = $this->repository->createRefundItem($itemData);

        $this->assertNotNull($item);
        $this->assertEquals($refund->id, $item->refund_id);
        $this->assertEquals(1, $item->refund_quantity);
    }

    public function testGetRefundItemsReturnsItems(): void
    {
        $order = $this->createOrder(['status' => 'completed', 'payment_status' => 'paid']);
        $refund = $this->createRefund(['order_id' => $order->id]);
        $product = $this->createProduct();
        $orderItem = $this->createOrderItem($order->id);
        $orderItem2 = $this->createOrderItem($order->id);

        RefundItem::create([
            'refund_id' => $refund->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'order_item_id' => $orderItem->id,
            'quantity' => 2,
            'refund_quantity' => 1,
            'unit_price' => 50.00,
            'refund_amount' => 50.00
        ]);

        RefundItem::create([
            'refund_id' => $refund->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'order_item_id' => $orderItem2->id,
            'quantity' => 1,
            'refund_quantity' => 1,
            'unit_price' => 30.00,
            'refund_amount' => 30.00
        ]);

        $items = $this->repository->getRefundItems($refund->id);

        $this->assertCount(2, $items);
    }

    public function testUpdateRefundStatusUpdatesStatus(): void
    {
        $order = $this->createOrder(['status' => 'completed', 'payment_status' => 'paid']);
        $user = $this->createUser();
        $refund = $this->createRefund([
            'order_id' => $order->id,
            'status' => 'pending',
            'processed_by' => $user->id,
        ]);

        $result = $this->repository->updateRefundStatus($refund->id, 'processed', 1);

        $this->assertTrue($result);

        $updatedRefund = Refund::find($refund->id);
        $this->assertEquals('processed', $updatedRefund->status);
        $this->assertEquals(1, $updatedRefund->processed_by);
        $this->assertNotNull($updatedRefund->processed_at);
    }

    public function testDeleteRefundItemsDeletesItems(): void
    {
        $order = $this->createOrder(['status' => 'completed', 'payment_status' => 'paid']);
        $refund = $this->createRefund(['order_id' => $order->id]);
        $product = $this->createProduct();
        $orderItem = $this->createOrderItem($order->id);

        RefundItem::create([
            'refund_id' => $refund->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'order_item_id' => $orderItem->id,
            'quantity' => 2,
            'refund_quantity' => 1,
            'unit_price' => 50.00,
            'refund_amount' => 50.00
        ]);

        $result = $this->repository->deleteRefundItems($refund->id);

        $this->assertTrue($result);

        $items = RefundItem::where('refund_id', $refund->id)->get();
        $this->assertCount(0, $items);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RefundRepository();
    }
}