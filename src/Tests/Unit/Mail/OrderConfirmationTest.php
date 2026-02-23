<?php

namespace App\Tests\Unit\Mail;

use App\Framework\Mail\ArrayMailer;
use App\Mail\Orders\OrderConfirmation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class OrderConfirmationTest extends FunctionalTestCase
{
    public function testBuildsWithOrderData(): void
    {
        $order = $this->createMockOrder();
        $mailable = new OrderConfirmation($order);

        $mailable->build();

        $this->assertStringContainsString('Order Confirmation', $mailable->subject);
        $this->assertStringContainsString($order->order_number, $mailable->subject);
    }

    private function createMockOrder(): Order
    {
        $order = new Order();
        $order->id = 1;
        $order->order_number = 'ORD-12345';
        $order->status = 'pending';
        $order->subtotal = 99.99;
        $order->tax = 10.00;
        $order->shipping_cost = 0.00;
        $order->discount = 0.00;
        $order->total = 109.99;
        $order->created_at = '2024-01-15 10:00:00';

        $user = new User();
        $user->first_name = 'John';
        $user->last_name = 'Doe';
        $user->email = 'john@example.com';
        $order->user = $user;

        $item = new OrderItem();
        $item->product_name = 'Test Product';
        $item->quantity = 1;
        $item->unit_price = 99.99;
        $item->subtotal = 99.99;
        $item->total = 99.99;

        $order->items = [$item];

        return $order;
    }

    public function testContainsOrderNumber(): void
    {
        $order = $this->createMockOrder();
        $mailable = new OrderConfirmation($order);

        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString($order->order_number, $html);
    }

    public function testContainsCustomerName(): void
    {
        $order = $this->createMockOrder();
        $mailable = new OrderConfirmation($order);

        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('John Doe', $html);
    }

    public function testContainsOrderItems(): void
    {
        $order = $this->createMockOrder();
        $mailable = new OrderConfirmation($order);

        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Test Product', $html);
        $this->assertStringContainsString('99.99', $html);
    }

    public function testContainsOrderTotal(): void
    {
        $order = $this->createMockOrder();
        $mailable = new OrderConfirmation($order);

        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('109.99', $html);
    }

    public function testContainsShippingAddress(): void
    {
        $order = $this->createMockOrder();
        $order->shipping_address = json_encode([
            'name' => 'John Doe',
            'line1' => '123 Main St',
            'city' => 'Springfield',
            'state' => 'IL',
            'postal_code' => '62701',
            'country' => 'USA'
        ]);

        $mailable = new OrderConfirmation($order);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('123 Main St', $html);
        $this->assertStringContainsString('Springfield', $html);
    }

    public function testUsesMarkdownTemplate(): void
    {
        $order = $this->createMockOrder();
        $mailable = new OrderConfirmation($order);

        $mailable->build();

        $this->assertEquals('emails.orders.confirmation', $mailable->markdown);
    }

    public function testHandlesGuestOrderWithoutUser(): void
    {
        $order = $this->createMockOrder();
        $order->user = null;
        $order->user_id = null;

        $mailable = new OrderConfirmation($order);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Valued Customer', $html);
    }

    protected function setUp(): void
    {
        ArrayMailer::clear();
    }

    protected function tearDown(): void
    {
        ArrayMailer::clear();
    }
}