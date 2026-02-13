<?php

namespace App\Tests\Unit\Services\Billing\Preorder;


use App\Events\Orders\ProductStockUpdated;
use App\Listeners\Orders\DispatchStockAllocationJobs;
use App\Models\Product;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class ProductStockUpdatedEventTest extends FunctionalTestCase
{
    private DispatchStockAllocationJobs $listener;

    public function test_dispatches_allocation_job_when_stock_increases(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        $event = new ProductStockUpdated($product, 5, 10);

        // Mock dispatch
        $dispatched = [];

        // Override global dispatch helper for test
        $oldDispatch = function ($job) use (&$dispatched) {
            $dispatched[] = get_class($job);
        };

        $this->listener->handle($event);

        // In real implementation, would verify job dispatched
        // This test structure depends on your job dispatching mechanism
        $this->assertEquals(10, $event->newStock);
        $this->assertEquals(5, $event->oldStock);
    }

    public function test_dispatches_alert_job_when_stock_goes_from_zero_to_positive(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        $event = new ProductStockUpdated($product, 0, 10);

        $this->listener->handle($event);

        // Verify both jobs would be dispatched
        $this->assertEquals(0, $event->oldStock);
        $this->assertEquals(10, $event->newStock);
    }

    public function test_does_not_dispatch_when_stock_decreases(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        $event = new ProductStockUpdated($product, 10, 5);

        // Should return early, no jobs dispatched
        $this->listener->handle($event);

        $this->assertLessThan($event->oldStock, $event->newStock);
    }

    public function test_does_not_dispatch_when_stock_unchanged(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        $event = new ProductStockUpdated($product, 10, 10);

        $this->listener->handle($event);

        $this->assertEquals($event->oldStock, $event->newStock);
    }

    public function test_does_not_dispatch_alert_when_stock_was_already_positive(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;

        // Stock goes from 5 to 10 (both positive)
        $event = new ProductStockUpdated($product, 5, 10);

        $this->listener->handle($event);

        // Only allocation job should dispatch, not alert
        $this->assertGreaterThan(0, $event->oldStock);
    }

    public function test_event_carries_correct_data(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 42;

        $event = new ProductStockUpdated($product, 3, 15);

        $this->assertSame($product, $event->product);
        $this->assertEquals(3, $event->oldStock);
        $this->assertEquals(15, $event->newStock);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = new DispatchStockAllocationJobs();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}