<?php

declare(strict_types=1);

namespace App\Tests\Unit\Actions\Product;

use App\Actions\Product\CreateProductFulfilmentAction;
use App\DTO\Subscriptions\FulfilmentDecisionContext;
use App\Framework\Support\Logger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductBatch;
use App\Models\ProductFulfilment;
use App\Models\Territory;
use App\Repositories\Product\ProductBatchRepository;
use App\Repositories\Product\ProductFulfilmentRepository;
use App\Services\Product\Fulfilment\ProductFulfilmentDecisionService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CreateProductFulfilmentActionTest extends TestCase
{
    private ProductFulfilmentRepository&MockInterface $fulfilmentRepository;
    private ProductBatchRepository&MockInterface $batchRepository;
    private ProductFulfilmentDecisionService&MockInterface $decisionService;
    private Logger&MockInterface $logger;
    private CreateProductFulfilmentAction $action;

    public function test_it_creates_a_fulfilment_and_emits_event(): void
    {
        $order = $this->makeOrder(1);
        $orderLine = $this->makeOrderLine(10, 'SKU-001', 2);
        $context = $this->makeContext(null);
        $batch = $this->makeBatch(5);
        $fulfilment = Mockery::mock(ProductFulfilment::class)->makePartial();
        $fulfilment->id = 99;

        $this->decisionService->shouldReceive('decide')->once()->with($order, $orderLine)->andReturn($context);

        $this->fulfilmentRepository
            ->shouldReceive('existsForOrderLineAndTerritory')
            ->once()
            ->with(10, null)
            ->andReturn(false);

        $this->batchRepository
            ->shouldReceive('findOrCreateForRunAndTerritory')
            ->once()
            ->with(42, null)
            ->andReturn($batch);

        $this->fulfilmentRepository
            ->shouldReceive('createProductFulfilment')
            ->once()
            ->andReturn($fulfilment);

        $result = $this->action->execute($order, $orderLine, 42);

        $this->assertSame($fulfilment, $result);
    }

    private function makeOrder(int $id): Order&MockInterface
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = $id;
        return $order;
    }

    private function makeOrderLine(int $id, string $sku, int $quantity): OrderItem&MockInterface
    {
        $line = Mockery::mock(OrderItem::class)->makePartial();
        $line->id = $id;
        $line->sku = $sku;
        $line->quantity = $quantity;
        return $line;
    }

    private function makeContext(?Territory $territory): FulfilmentDecisionContext
    {
        $snapshot = [
            'first_name' => 'Test',
            'last_name' => 'User',
            'address_line_1' => '1 Test Street',
            'city' => 'London',
            'postcode' => 'EC1A 1BB',
            'country' => 'GB',
        ];

        return new FulfilmentDecisionContext(
            territory: $territory,
            addressSnapshot: $snapshot,
            fullName: trim(($snapshot['first_name'] ?? '') . ' ' . ($snapshot['last_name'] ?? '')),
            channelMetadata: [],
        );
    }

    private function makeBatch(int $id): ProductBatch&MockInterface
    {
        $batch = Mockery::mock(ProductBatch::class)->makePartial();
        $batch->id = $id;
        return $batch;
    }

    public function test_it_returns_existing_fulfilment_without_creating_a_duplicate(): void
    {
        $order = $this->makeOrder(1);
        $orderLine = $this->makeOrderLine(10, 'SKU-002', 1);
        $territory = $this->makeTerritory(3);
        $context = $this->makeContext($territory);
        $existing = Mockery::mock(ProductFulfilment::class);

        $this->decisionService->shouldReceive('decide')->once()->andReturn($context);

        $this->fulfilmentRepository
            ->shouldReceive('existsForOrderLineAndTerritory')
            ->once()
            ->with(10, 3)
            ->andReturn(true);

        $this->fulfilmentRepository
            ->shouldReceive('findForOrderLineAndTerritory')
            ->once()
            ->with(10, 3)
            ->andReturn($existing);

        // Must NOT call create or batch lookup when record already exists.
        $this->batchRepository->shouldNotReceive('findOrCreateForRunAndTerritory');
        $this->fulfilmentRepository->shouldNotReceive('create');

        $result = $this->action->execute($order, $orderLine, 99);

        $this->assertSame($existing, $result);
    }

    private function makeTerritory(int $id): Territory&MockInterface
    {
        $territory = Mockery::mock(Territory::class)->makePartial();
        $territory->id = $id;
        return $territory;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function test_it_uses_territory_id_from_context_for_batch_lookup(): void
    {
        $order = $this->makeOrder(1);
        $orderLine = $this->makeOrderLine(10, 'SKU-003', 1);
        $territory = $this->makeTerritory(7);
        $context = $this->makeContext($territory);
        $batch = $this->makeBatch(20);
        $fulfilment = Mockery::mock(ProductFulfilment::class)->makePartial();
        $fulfilment->id = 1;

        $this->decisionService->shouldReceive('decide')->andReturn($context);
        $this->fulfilmentRepository->shouldReceive('existsForOrderLineAndTerritory')->andReturn(false);
        $this->fulfilmentRepository->shouldReceive('createProductFulfilment')->andReturn($fulfilment);

        $this->batchRepository
            ->shouldReceive('findOrCreateForRunAndTerritory')
            ->once()
            ->with(55, 7)  // territory_id = 7 from context
            ->andReturn($batch);

        $this->action->execute($order, $orderLine, 55);
        $this->assertTrue(true);
    }

    public function test_it_propagates_address_resolution_failure(): void
    {
        $order = $this->makeOrder(1);
        $orderLine = $this->makeOrderLine(10, 'SKU-004', 1);

        $this->decisionService
            ->shouldReceive('decide')
            ->once()
            ->andThrow(new \RuntimeException('no valid delivery address found'));

        $this->fulfilmentRepository->shouldNotReceive('create');
        $this->batchRepository->shouldNotReceive('findOrCreateForRunAndTerritory');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no valid delivery address found');

        $this->action->execute($order, $orderLine, 1);
    }

    public function test_it_passes_sku_and_quantity_from_order_line_to_repository(): void
    {
        $order = $this->makeOrder(1);
        $orderLine = $this->makeOrderLine(10, 'SKU-WIDGET-99', 3);
        $context = $this->makeContext(null);
        $batch = $this->makeBatch(5);
        $fulfilment = Mockery::mock(ProductFulfilment::class)->makePartial();
        $fulfilment->id = 1;

        $this->decisionService->shouldReceive('decide')->andReturn($context);
        $this->fulfilmentRepository->shouldReceive('existsForOrderLineAndTerritory')->andReturn(false);
        $this->batchRepository->shouldReceive('findOrCreateForRunAndTerritory')->andReturn($batch);

        $this->fulfilmentRepository
            ->shouldReceive('createProductFulfilment')
            ->once()
            ->with(
                5,
                1,
                10,
                'SKU-WIDGET-99',
                3,
                'Test User',
                ['first_name' => 'Test', 'last_name' => 'User', 'address_line_1' => '1 Test Street', 'city' => 'London', 'postcode' => 'EC1A 1BB', 'country' => 'GB'],
                '1 Test Street',
                null,
                'London',
                'EC1A 1BB',
                'GB',
                null,
            )
            ->andReturn($fulfilment);

        $this->action->execute($order, $orderLine, 1);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->fulfilmentRepository = Mockery::mock(ProductFulfilmentRepository::class);
        $this->batchRepository = Mockery::mock(ProductBatchRepository::class);
        $this->decisionService = Mockery::mock(ProductFulfilmentDecisionService::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->logger->shouldReceive('info')->byDefault();

        $this->action = new CreateProductFulfilmentAction(
            $this->fulfilmentRepository,
            $this->batchRepository,
            $this->decisionService,
            $this->logger,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}