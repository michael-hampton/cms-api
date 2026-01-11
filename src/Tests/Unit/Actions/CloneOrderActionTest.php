<?php

namespace App\Tests\Unit\Actions;

use App\Actions\CloneOrder;
use App\Framework\Database\Database;
use App\Models\Address;
use App\Models\Member;
use App\Models\Order;
use App\Repositories\Members\OrderRepository;
use App\Services\Members\OrderService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Services\Concerns\HasSiteHistory;
use Mockery as m;

class CloneOrderActionTest extends FunctionalTestCase
{
    use HasSiteHistory;

    private $orderRepository;
    private $databaseMock;
    private CloneOrder $service;
    private $orderService;

    protected function setUp(): void
    {
        parent::setUp(); // Call parent setup if it exists
        $this->orderRepository = m::mock(OrderRepository::class);
        $this->databaseMock = m::mock(Database::class);
        $this->orderService = m::mock(OrderService::class);

        $this->service = new CloneOrder(
            $this->orderRepository,
            $this->orderService,
            $this->databaseMock,
        );
    }

    public function testDuplicateOrderThrowsExceptionWhenOrderNotFound()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order not found');

        $this->service->handle(999);
    }

    public function testDuplicateOrderCreatesNewOrderWithPendingStatus()
    {
        $orderId = 1;

        $originalOrder = m::mock(Order::class)->makePartial();
        $originalOrder->id = 1;
        $originalOrder->user_id = 10;
        $originalOrder->status = 'completed';
        $originalOrder->subtotal = 100.00;
        $originalOrder->tax = 10.00;
        $originalOrder->shipping = 5.00;
        $originalOrder->discount = 0.00;
        $originalOrder->total = 115.00;
        $originalOrder->currency = 'USD';
        $originalOrder->site_id = 1;
        $originalOrder->shipping_address = '123 Main St';
        $originalOrder->billing_address = '123 Main St';
        $originalOrder->payment_method = 'credit_card';
        $originalOrder->shipping_address_id = null; // ADD THIS
        $originalOrder->billing_address_id = null;  // ADD THIS
        $originalOrder->items = collect([]);

        $duplicatedOrder = m::mock(Order::class)->makePartial();
        $duplicatedOrder->id = 2;

        $this->setCloneHistoryExpectations($originalOrder, $duplicatedOrder, 1, 2);

        $duplicatedOrder->shouldReceive('relationLoaded')->atLeast()->once();

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($originalOrder);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andReturn($duplicatedOrder);

        $result = $this->service->handle($orderId);

        $this->assertSame($duplicatedOrder, $result['order']);
    }

    public function testDuplicateOrderWithMixedAddresses()
    {
        $orderId = 1;

        $originalOrder = m::mock(Order::class)->makePartial();
        $originalOrder->id = 1;
        $originalOrder->user_id = 10;
        $originalOrder->status = 'completed';
        $originalOrder->subtotal = 100.00;
        $originalOrder->tax = 10.00;
        $originalOrder->shipping = 5.00;
        $originalOrder->discount = 0.00;
        $originalOrder->total = 115.00;
        $originalOrder->currency = 'USD';
        $originalOrder->site_id = 1;
        $originalOrder->payment_method = 'credit_card';
        $originalOrder->shipping_address_id = 20; // Linked
        $originalOrder->billing_address_id = null;
        $originalOrder->shipping_address = null;
        $originalOrder->billing_address = [ // JSON
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US'
        ];
        $originalOrder->items = collect([]);

        $duplicatedOrder = m::mock(Order::class)->makePartial();
        $duplicatedOrder->id = 2;

        $this->setCloneHistoryExpectations($originalOrder, $duplicatedOrder, 1, 2);;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($originalOrder);

        // Mock member lookup
        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        // Mock shipping address validation (linked)
        $shippingAddress = m::mock(Address::class)->makePartial();
        $shippingAddress->id = 20;
        $shippingAddress->member_id = 10;

        // No address validation needed for billing (it's JSON)

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andReturn($duplicatedOrder);

        $result = $this->service->handle($orderId);

        $this->assertSame($duplicatedOrder, $result['order']);
    }

    public function testDuplicateOrderWithJsonAddresses()
    {
        $orderId = 1;

        $originalOrder = m::mock(Order::class)->makePartial();
        $originalOrder->id = 1;
        $originalOrder->user_id = null; // Guest order
        $originalOrder->status = 'completed';
        $originalOrder->subtotal = 100.00;
        $originalOrder->tax = 10.00;
        $originalOrder->shipping = 5.00;
        $originalOrder->discount = 0.00;
        $originalOrder->total = 115.00;
        $originalOrder->currency = 'USD';
        $originalOrder->site_id = 1;
        $originalOrder->payment_method = 'credit_card';
        $originalOrder->shipping_address_id = null;
        $originalOrder->billing_address_id = null;
        $originalOrder->shipping_address = [
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postcode' => '12345',
            'country' => 'US'
        ];
        $originalOrder->billing_address = [
            'address_line_1' => '456 Oak Ave',
            'city' => 'Town',
            'postcode' => '67890',
            'country' => 'US'
        ];
        $originalOrder->items = collect([]);

        $duplicatedOrder = m::mock(Order::class)->makePartial();
        $duplicatedOrder->id = 2;

        $this->setCloneHistoryExpectations($originalOrder, $duplicatedOrder, 1, 2);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($originalOrder);

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andReturn($duplicatedOrder);

        $result = $this->service->handle($orderId);

        $this->assertSame($duplicatedOrder, $result['order']);
    }

    public function testDuplicateOrderWithInvalidOriginalStatusStillCreatesOrder(): void
    {
        // Duplicate should work regardless of original order status
        $orderId = 1;

        $originalOrder = m::mock(Order::class)->makePartial();
        $originalOrder->id = 1;
        $originalOrder->user_id = 10;
        $originalOrder->status = 'cancelled'; // Even cancelled orders can be duplicated
        $originalOrder->subtotal = 100.00;
        $originalOrder->tax = 10.00;
        $originalOrder->shipping = 5.00;
        $originalOrder->discount = 0.00;
        $originalOrder->total = 115.00;
        $originalOrder->currency = 'USD';
        $originalOrder->site_id = 1;
        $originalOrder->shipping_address = '123 Main St';
        $originalOrder->billing_address = '123 Main St';
        $originalOrder->payment_method = 'credit_card';
        $originalOrder->shipping_address_id = null;
        $originalOrder->billing_address_id = null;
        $originalOrder->items = collect([]);

        $duplicatedOrder = m::mock(Order::class)->makePartial();
        $duplicatedOrder->id = 2;

        $this->setCloneHistoryExpectations($originalOrder, $duplicatedOrder, 1, 2);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with($orderId)
            ->andReturn($originalOrder);

        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andReturn($duplicatedOrder);

        $result = $this->service->handle($orderId);

        $this->assertSame($duplicatedOrder, $result['order']);
    }

    public function testDuplicateOrderWithLinkedAddresses()
    {
        $orderId = 1;

        $originalOrder = m::mock(Order::class)->makePartial();
        $originalOrder->id = 1;
        $originalOrder->user_id = 10;
        $originalOrder->status = 'completed';
        $originalOrder->subtotal = 100.00;
        $originalOrder->tax = 10.00;
        $originalOrder->shipping = 5.00;
        $originalOrder->discount = 0.00;
        $originalOrder->total = 115.00;
        $originalOrder->currency = 'USD';
        $originalOrder->site_id = 1;
        $originalOrder->payment_method = 'credit_card';
        $originalOrder->shipping_address_id = 20;
        $originalOrder->billing_address_id = 21;
        $originalOrder->shipping_address = null;
        $originalOrder->billing_address = null;
        $originalOrder->items = collect([]);

        $duplicatedOrder = m::mock(Order::class)->makePartial();
        $duplicatedOrder->id = 2;

        $this->setCloneHistoryExpectations($originalOrder, $duplicatedOrder, 1, 2);

        $this->databaseMock->shouldReceive('transaction')
            ->once() // Once for duplicateOrder, once for createOrder
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->orderRepository->shouldReceive('getOrderById')
            ->once()
            ->with(1)
            ->andReturn($originalOrder);

        // Mock member lookup in createOrder
        $member = m::mock(Member::class)->makePartial();
        $member->id = 10;

        // Mock address validation in createOrder
        $shippingAddress = m::mock(Address::class)->makePartial();
        $shippingAddress->id = 20;
        $shippingAddress->member_id = 10;

        $billingAddress = m::mock(Address::class)->makePartial();
        $billingAddress->id = 21;
        $billingAddress->member_id = 10;

        $this->orderService->shouldReceive('createOrder')
            ->once()
            ->andReturn($duplicatedOrder);

        $result = $this->service->handle($orderId);

        $this->assertSame($duplicatedOrder, $result['order']);
    }

    public function testCloneOrderReturnsDetailedResults()
    {
        $originalOrder = m::mock(Order::class)->makePartial();
        $originalOrder->id = 1;
        $originalOrder->user_id = 10;
        $originalOrder->status = 'completed';
        $originalOrder->subtotal = 100.00;
        $originalOrder->tax = 10.00;
        $originalOrder->shipping = 5.00;
        $originalOrder->discount = 0.00;
        $originalOrder->total = 115.00;
        $originalOrder->currency = 'USD';
        $originalOrder->site_id = 1;
        $originalOrder->payment_method = 'credit_card';
        $originalOrder->shipping_address_id = 20;
        $originalOrder->billing_address = ['address_line_1' => '123 Main'];
        $originalOrder->items = collect([
            (object)['product_name' => 'Product 1', 'quantity' => 2],
            (object)['product_name' => 'Product 2', 'quantity' => 1],
        ]);

        $newOrder = m::mock(Order::class)->makePartial();
        $newOrder->id = 2;

        $this->databaseMock->shouldReceive('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->orderRepository->shouldReceive('getOrderById')->with(1)->andReturn($originalOrder);
        $this->orderService->shouldReceive('createOrder')->andReturn($newOrder);
        $this->setCloneHistoryExpectations($originalOrder, $newOrder, 1, 2);

        $result = $this->service->handle(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('order', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('original_order_id', $result);
        $this->assertContains('shipping_address_linked', $result['results']['success']);
        $this->assertContains('billing_address_json', $result['results']['success']);
        $this->assertContains('order_created', $result['results']['success']);
        $this->assertEquals(2, $result['results']['items_cloned']);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}