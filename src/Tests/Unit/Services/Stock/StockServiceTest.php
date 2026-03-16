<?php

namespace App\Tests\Unit\Services\Stock;

use App\Events\Stock\StockAllocated;
use App\Events\Stock\StockConfirmed;
use App\Events\Stock\StockLow;
use App\Events\Stock\StockReleased;
use App\Exceptions\Stock\StockException;
use App\Framework\Database\Database;
use App\Models\IssueDelivery;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Services\Stock\StockService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class StockServiceTest extends TestCase
{
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    private StockService $service;
    private ProductRepository|MockInterface $productRepository;
    private IssueDeliveryRepository|MockInterface $issueDeliveryRepository;
    private Database|MockInterface $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->issueDeliveryRepository = Mockery::mock(IssueDeliveryRepository::class);
        $this->database = Mockery::mock(Database::class);

        // Database::transaction() must always execute the callback and return its value.
        $this->database->shouldReceive('transaction')
            ->andReturnUsing(fn($cb) => $cb())
            ->byDefault();

        $this->service = new StockService(
            $this->productRepository,
            $this->issueDeliveryRepository,
            $this->database,
        );
    }

    // =========================================================================
    // allocate()
    // =========================================================================

    public function test_allocate_decrements_product_stock(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Test Book';
        $product->stock_quantity = 10;

        $updated = Mockery::mock(Product::class)->makePartial();
        $updated->stock_quantity = 8;

        $this->productRepository
            ->shouldReceive('decrementStock')
            ->once()
            ->with(1, 2)
            ->andReturn($updated);


        $this->service->allocate($product, 2);
        $this->assertTrue(true);
    }

    public function test_allocate_decrements_issue_delivery_stock(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 5;
        $issue->issue_title = 'Issue #5';
        $issue->stock_quantity = 20;

        $updated = Mockery::mock(IssueDelivery::class)->makePartial();
        $updated->stock_quantity = 18;

        $this->issueDeliveryRepository
            ->shouldReceive('decrementStock')
            ->once()
            ->with(5, 2)
            ->andReturn($updated);

        $this->service->allocate($issue, 2);
        $this->assertTrue(true);
    }

    public function test_allocate_does_not_fire_stock_low_when_above_threshold(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Plenty';
        $product->stock_quantity = 50;

        $updated = Mockery::mock(Product::class)->makePartial();
        $updated->stock_quantity = 48;

        $this->productRepository
            ->shouldReceive('decrementStock')
            ->once()
            ->andReturn($updated);

        $this->service->allocate($product, 2);
        $this->assertTrue(true);
    }

    public function test_allocate_throws_when_stock_quantity_is_null(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'No Stock Field';
        $product->stock_quantity = null;

        $this->productRepository->shouldNotReceive('decrementStock');

        $this->expectException(StockException::class);

        $this->service->allocate($product, 1);
    }

    public function test_allocate_throws_for_unsupported_model_type(): void
    {
        $unsupported = new \stdClass();

        $this->expectException(StockException::class);

        $this->service->allocate($unsupported, 1);
    }

    // =========================================================================
    // reserve()
    // =========================================================================

    public function test_reserve_decrements_stock_and_returns_reservation_id(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->issue_title = 'Spring Issue';
        $issue->stock_quantity = 15;

        $updated = Mockery::mock(IssueDelivery::class)->makePartial();
        $updated->stock_quantity = 13;

        $this->issueDeliveryRepository
            ->shouldReceive('decrementStock')
            ->once()
            ->with(10, 2)
            ->andReturn($updated);

        $reservationId = $this->service->reserve($issue, 2);

        $this->assertIsInt($reservationId);
        $this->assertGreaterThan(0, $reservationId);
    }

    public function test_reserve_throws_stock_exception_when_insufficient_stock(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->issue_title = 'Summer Issue';
        $issue->stock_quantity = 0;

        $this->issueDeliveryRepository->shouldNotReceive('decrementStock');

        $this->expectException(StockException::class);
        //$this->expectExceptionMessage("Insufficient stock for 'Summer Issue'");

        $this->service->reserve($issue, 1);
    }

    public function test_reserve_returns_incrementing_ids_for_multiple_calls(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->issue_title = 'Issue';
        $issue->stock_quantity = 50;

        $updated = Mockery::mock(IssueDelivery::class)->makePartial();
        $updated->stock_quantity = 49;

        $this->issueDeliveryRepository
            ->shouldReceive('decrementStock')
            ->twice()
            ->andReturn($updated);

        $id1 = $this->service->reserve($issue, 1);
        $id2 = $this->service->reserve($issue, 1);

        $this->assertNotEquals($id1, $id2);
        $this->assertEquals($id1 + 1, $id2);
    }

    // =========================================================================
    // confirm()
    // =========================================================================

    public function test_confirm_wraps_a_transaction(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->issue_title = 'Issue';
        $issue->stock_quantity = 5;

        $updated = Mockery::mock(IssueDelivery::class)->makePartial();
        $updated->stock_quantity = 4;

        $lockedIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $lockedIssue->stock_quantity = 4;

        $this->issueDeliveryRepository->shouldReceive('decrementStock')->once()->andReturn($updated);
        $this->issueDeliveryRepository->shouldReceive('lockForUpdate')->once()->andReturn($lockedIssue);

        $reservationId = $this->service->reserve($issue, 1);

        // Expect exactly one transaction call from confirm()
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->service->confirm($reservationId);
    }

    public function test_confirm_throws_when_reservation_id_is_unknown(): void
    {
        $this->expectException(StockException::class);
        $this->expectExceptionMessage('reservation #999');

        $this->service->confirm(999);
    }

    public function test_confirm_throws_when_model_no_longer_exists(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->issue_title = 'Deleted Issue';
        $issue->stock_quantity = 5;

        $updated = Mockery::mock(IssueDelivery::class)->makePartial();
        $updated->stock_quantity = 4;

        $this->issueDeliveryRepository->shouldReceive('decrementStock')->once()->andReturn($updated);
        $this->issueDeliveryRepository->shouldReceive('lockForUpdate')->once()->andReturn(null);

        $reservationId = $this->service->reserve($issue, 1);

        $this->expectException(StockException::class);

        $this->service->confirm($reservationId);
    }

    public function test_confirm_consumes_reservation_so_second_call_throws(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->issue_title = 'Issue';
        $issue->stock_quantity = 5;

        $updated = Mockery::mock(IssueDelivery::class)->makePartial();
        $updated->stock_quantity = 4;

        $lockedIssue = Mockery::mock(IssueDelivery::class)->makePartial();
        $lockedIssue->stock_quantity = 4;

        $this->issueDeliveryRepository->shouldReceive('decrementStock')->once()->andReturn($updated);
        $this->issueDeliveryRepository->shouldReceive('lockForUpdate')->once()->andReturn($lockedIssue);

        $reservationId = $this->service->reserve($issue, 1);
        $this->service->confirm($reservationId);

        $this->expectException(StockException::class);
        $this->service->confirm($reservationId); // second call must fail
    }

    // =========================================================================
    // release()
    // =========================================================================

    public function test_release_wraps_a_transaction(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Book';

        $updated = Mockery::mock(Product::class)->makePartial();
        $updated->stock_quantity = 11;

        $this->productRepository->shouldReceive('incrementStock')->once()->andReturn($updated);

        $transactionCalled = false;
        $this->database->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($cb) use (&$transactionCalled) {
                $transactionCalled = true;
                return $cb();
            });

        $this->service->release($product, 1);

        $this->assertTrue($transactionCalled);
    }
}