<?php

namespace App\Tests\Unit\Services\Billing\Refunds;

use App\Exceptions\Orders\RefundAmountExceedsRemainingException;
use App\Models\Order;
use App\Repositories\Billing\RefundRepository;
use App\Services\Billing\Refund\RefundAmountValidator;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class RefundAmountValidatorTest extends TestCase
{
    private RefundRepository $refundRepository;
    private RefundAmountValidator $validator;

    public function testValidatesAmountWithinRemainingTotal(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(50.00);

        // Should not throw
        $this->validator->validateAmount($order, 100.00);

        $this->assertTrue(true); // Assertion to confirm no exception
    }

    public function testValidatesAmountExactlyEqualToRemaining(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(150.00);

        // Should not throw - exactly 50.00 remaining
        $this->validator->validateAmount($order, 50.00);

        $this->assertTrue(true);
    }

    public function testThrowsExceptionWhenAmountExceedsRemaining(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(150.00);

        $this->expectException(RefundAmountExceedsRemainingException::class);
        $this->expectExceptionMessage('Refund amount 100 exceeds remaining order total. Available: 50');

        $this->validator->validateAmount($order, 100.00);
    }

    public function testThrowsExceptionWhenNoRemainingAmount(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(200.00); // Fully refunded

        $this->expectException(RefundAmountExceedsRemainingException::class);
        $this->expectExceptionMessage('Refund amount 0.01 exceeds remaining order total. Available: 0');

        $this->validator->validateAmount($order, 0.01);
    }

    public function testGetRemainingAmountReturnsCorrectValue(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(75.00);

        $result = $this->validator->getRemainingAmount($order);

        $this->assertEquals(125.00, $result);
    }

    public function testGetRemainingAmountReturnsZeroWhenFullyRefunded(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(200.00);

        $result = $this->validator->getRemainingAmount($order);

        $this->assertEquals(0.0, $result);
    }

    public function testGetRemainingAmountReturnsZeroWhenOverRefunded(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(250.00); // Over-refunded (shouldn't happen, but defensive)

        $result = $this->validator->getRemainingAmount($order);

        $this->assertEquals(0.0, $result); // max(0, -50) = 0
    }

    public function testGetRemainingAmountForNewOrder(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 200.00;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(0.0);

        $result = $this->validator->getRemainingAmount($order);

        $this->assertEquals(200.00, $result);
    }

    public function testValidatesMultiplePartialRefunds(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 500.00;

        // First refund: 100.00
        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(0.0);

        $this->validator->validateAmount($order, 100.00);

        // Second refund: 200.00 (total now 300.00)
        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(100.00);

        $this->validator->validateAmount($order, 200.00);

        // Third refund: 200.00 (would total 500.00)
        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(300.00);

        $this->validator->validateAmount($order, 200.00);

        $this->assertTrue(true);
    }

    public function testHandlesDecimalPrecision(): void
    {
        $order = m::mock(Order::class)->makePartial();
        $order->id = 1;
        $order->total = 99.99;

        $this->refundRepository
            ->shouldReceive('getTotalRefundedAmount')
            ->once()
            ->with(1)
            ->andReturn(50.50);

        $result = $this->validator->getRemainingAmount($order);

        $this->assertEquals(49.49, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->refundRepository = m::mock(RefundRepository::class);
        $this->validator = new RefundAmountValidator($this->refundRepository);
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }
}