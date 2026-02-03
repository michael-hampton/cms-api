<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\Models\Order;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\Order\OrderNumberGenerator;
use Mockery;
use PHPUnit\Framework\TestCase;

class OrderNumberGeneratorTest extends TestCase
{
    private OrderRepository $orderRepository;
    private OrderNumberGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->generator = new OrderNumberGenerator($this->orderRepository);
    }

    public function test_it_generates_unique_order_number_on_first_attempt()
    {
        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null); // Number is unique

        $orderNumber = $this->generator->generate();

        $this->assertStringStartsWith('ORD-', $orderNumber);
        $this->assertMatchesRegularExpression('/^ORD-\d+-\d{4}$/', $orderNumber);
    }

    public function test_it_retries_when_order_number_collision_occurs()
    {
        // First attempt: collision
        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(Mockery::mock(Order::class)); // Exists

        // Second attempt: success
        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null); // Unique

        $orderNumber = $this->generator->generate();

        $this->assertStringStartsWith('ORD-', $orderNumber);
    }

    public function test_it_uses_uuid_fallback_after_max_retries()
    {
        // All 5 attempts result in collision
        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->times(5)
            ->andReturn(Mockery::mock(Order::class));

        $orderNumber = $this->generator->generate();

        // UUID fallback format: ORD-{16 hex chars}
        $this->assertStringStartsWith('ORD-', $orderNumber);
        $this->assertMatchesRegularExpression('/^ORD-[a-f0-9]{16}$/', $orderNumber);
    }

    public function test_it_generates_different_numbers_on_consecutive_calls()
    {
        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->andReturn(null);

        $number1 = $this->generator->generate();

        // Small delay to ensure timestamp difference
        usleep(1000);

        $number2 = $this->generator->generate();

        $this->assertNotEquals($number1, $number2);
    }

    public function test_it_includes_timestamp_in_order_number()
    {
        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->once()
            ->andReturn(null);

        $beforeTime = time();
        $orderNumber = $this->generator->generate();
        $afterTime = time();

        // Extract timestamp from order number (format: ORD-{timestamp}-{random})
        preg_match('/^ORD-(\d+)-\d{4}$/', $orderNumber, $matches);
        $this->assertNotEmpty($matches);

        $timestamp = (int)$matches[1];
        $this->assertGreaterThanOrEqual($beforeTime, $timestamp);
        $this->assertLessThanOrEqual($afterTime, $timestamp);
    }

    public function test_it_includes_random_suffix_in_order_number()
    {
        $this->orderRepository->shouldReceive('findByOrderNumber')
            ->andReturn(null);

        $number1 = $this->generator->generate();
        $number2 = $this->generator->generate();

        // Extract random suffix
        preg_match('/-(\d{4})$/', $number1, $matches1);
        preg_match('/-(\d{4})$/', $number2, $matches2);

        $this->assertNotEmpty($matches1);
        $this->assertNotEmpty($matches2);

        $suffix1 = (int)$matches1[1];
        $suffix2 = (int)$matches2[1];

        // Both should be 4-digit numbers between 1000-9999
        $this->assertGreaterThanOrEqual(1000, $suffix1);
        $this->assertLessThanOrEqual(9999, $suffix1);
        $this->assertGreaterThanOrEqual(1000, $suffix2);
        $this->assertLessThanOrEqual(9999, $suffix2);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}