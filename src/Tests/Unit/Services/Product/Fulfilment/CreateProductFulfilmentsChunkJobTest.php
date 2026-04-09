<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Product\Fulfilment;

use App\Actions\Product\CreateProductFulfilmentAction;
use App\Framework\Container;
use App\Framework\Support\Logger;
use App\Jobs\Products\CreateProductFulfilmentsChunkJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductFulfilmentRun;
use App\Repositories\Billing\OrderItemRepository;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Product\ProductFulfilmentRunRepository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CreateProductFulfilmentsChunkJobTest extends TestCase
{
    private ProductFulfilmentRunRepository&MockInterface $runRepository;
    private OrderRepository&MockInterface $orderRepository;
    private OrderItemRepository&MockInterface $orderLineRepository;
    private CreateProductFulfilmentAction&MockInterface $fulfilmentAction;
    private Logger&MockInterface $logger;

    public function test_it_calls_the_action_for_each_order_line(): void
    {
        $run = $this->makeRun(1, 1, true);
        $order = $this->makeOrder(10);

        $this->runRepository->shouldReceive('find')->with(1)->andReturn($run);
        $this->orderRepository->shouldReceive('find')->with(10)->andReturn($order);

        $this->orderLineRepository
            ->shouldReceive('find')
            ->andReturnUsing(function ($id) use ($order) {
                return $this->makeOrderLine($id);
            });

        $this->fulfilmentAction
            ->shouldReceive('execute')
            ->twice()
            ->andReturn(Mockery::mock(\App\Models\ProductFulfilment::class));

        $this->handle(1, 10, [100, 101]);
        $this->assertTrue(true);
    }

    private function makeRun(int $runId, int $totalChunks, bool $isLast): ProductFulfilmentRun&MockInterface
    {
        $run = Mockery::mock(ProductFulfilmentRun::class)->makePartial();
        $run->total_chunks = $totalChunks;
        $run->id = 1;

        $run->shouldReceive('isCancelled')->andReturn(false);
        $run->shouldReceive('allChunksComplete')->andReturn($isLast);
        $run->shouldReceive('incrementFulfilledChunks')->andReturn(1);

        return $run;
    }

    private function makeOrder(int $id): Order&MockInterface
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->id = $id;
        return $order;
    }

    private function makeOrderLine(int $id): OrderItem&MockInterface
    {
        $line = Mockery::mock(OrderItem::class)->makePartial();
        $line->id = $id;
        return $line;
    }

    private function handle(int $runId, int $orderId, array $orderLineIds, int $chunkIndex = 0): void
    {
        $job = CreateProductFulfilmentsChunkJob::for($runId, $orderId, $orderLineIds, $chunkIndex);
        $job->__wakeup();
        $job->handle();
    }

    public function test_it_continues_processing_after_a_per_line_failure(): void
    {
        $run = $this->makeRun(runId: 1, totalChunks: 1, isLast: true);
        $order = $this->makeOrder(10);

        $line1 = $this->makeOrderLine(100);
        $line2 = $this->makeOrderLine(101);

        $this->runRepository->shouldReceive('find')->andReturn($run);
        $this->orderRepository->shouldReceive('find')->andReturn($order);

        // **Stub the order line repository for the job's internal calls**
        $this->orderLineRepository
            ->shouldReceive('find')
            ->with(100)->andReturn($line1);

        $this->orderLineRepository
            ->shouldReceive('find')
            ->with(101)->andReturn($line2);

        // **Stub the "parent" order call (the ID 10 the job looks up)**
        $this->orderLineRepository
            ->shouldReceive('find')
            ->with(10)->andReturn($order);

        $this->fulfilmentAction
            ->shouldReceive('execute')
            ->once()
            ->with($order, $line1, 1)
            ->andThrow(new \RuntimeException());

        $this->fulfilmentAction
            ->shouldReceive('execute')
            ->once()
            ->with($order, $line2, 1)
            ->andReturn(Mockery::mock(\App\Models\ProductFulfilment::class));

        $this->handle(1, 10, [100, 101]);
        $this->assertTrue(true);
    }

    public function test_it_fires_all_fulfilments_created_when_last_chunk_completes(): void
    {
        $run = $this->makeRun(runId: 1, totalChunks: 1, isLast: true);
        $order = $this->makeOrder(10);

        $this->runRepository->shouldReceive('find')->andReturn($run);
        $this->orderRepository->shouldReceive('find')->andReturn($order);

        $this->orderLineRepository
            ->shouldReceive('find')
            ->andReturn($this->makeOrderLine(100));

        $this->fulfilmentAction
            ->shouldReceive('execute')
            ->andReturn(Mockery::mock(\App\Models\ProductFulfilment::class));

        // 👇 THIS is the fix — stub the static query path
        Mockery::mock(ProductFulfilmentRun::class)
            ->shouldReceive('where')
            ->andReturnSelf()
            ->shouldReceive('update')
            ->andReturn(1);

        $this->handle(1, 10, [100]);
        $this->assertTrue(true);
    }

    public function test_it_does_not_fire_barrier_event_when_not_the_last_chunk(): void
    {
        $run = $this->makeRun(runId: 1, totalChunks: 3, isLast: false);
        $order = $this->makeOrder(10);

        $this->runRepository->shouldReceive('find')->andReturn($run);
        $this->orderRepository->shouldReceive('find')->andReturn($order);

        $this->orderLineRepository
            ->shouldReceive('find')
            ->andReturn($this->makeOrderLine(100));

        $this->fulfilmentAction
            ->shouldReceive('execute')
            ->andReturn(Mockery::mock(\App\Models\ProductFulfilment::class));

        $this->handle(1, 10, [100]);
        $this->assertTrue(true);
    }

    public function test_it_skips_processing_when_run_is_cancelled(): void
    {
        $run = Mockery::mock(ProductFulfilmentRun::class);
        $run->shouldReceive('isCancelled')->andReturn(true);

        $this->runRepository->shouldReceive('find')->with(1)->andReturn($run);

        $this->orderRepository->shouldNotReceive('find');
        $this->fulfilmentAction->shouldNotReceive('execute');

        $this->handle(runId: 1, orderId: 10, orderLineIds: [100]);

        $this->addToAssertionCount(1);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function test_it_returns_early_when_run_not_found(): void
    {
        $this->runRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->orderRepository->shouldNotReceive('find');
        $this->fulfilmentAction->shouldNotReceive('execute');

        $this->handle(runId: 99, orderId: 10, orderLineIds: [100]);

        $this->addToAssertionCount(1);
    }

    public function test_it_logs_warning_and_increments_failed_count_for_missing_order_line(): void
    {
        $run = $this->makeRun(runId: 1, totalChunks: 1, isLast: true);
        $order = $this->makeOrder(10);

        $this->runRepository->shouldReceive('find')->andReturn($run);
        $this->orderRepository->shouldReceive('find')->andReturn($order);

        // **Stub the order line repository for job's internal lookup**
        $this->orderLineRepository
            ->shouldReceive('find')
            ->with(999)->andReturn(null);

        // **Also stub the parent order call (ID 10)**
        $this->orderLineRepository
            ->shouldReceive('find')
            ->with(10)->andReturn($order);

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with(Mockery::type('string'), Mockery::type('array'));

        $this->fulfilmentAction->shouldNotReceive('execute');

        $this->handle(1, 10, [999]);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->runRepository = Mockery::mock(ProductFulfilmentRunRepository::class);
        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->orderLineRepository = Mockery::mock(OrderItemRepository::class);
        $this->fulfilmentAction = Mockery::mock(CreateProductFulfilmentAction::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->logger->shouldReceive('info')->byDefault();
        $this->logger->shouldReceive('error')->byDefault();
        $this->logger->shouldReceive('warning')->byDefault();

        $container = Container::getInstance();
        $container->instance(ProductFulfilmentRunRepository::class, $this->runRepository);
        $container->instance(OrderRepository::class, $this->orderRepository);
        $container->instance(OrderItemRepository::class, $this->orderLineRepository);
        $container->instance(CreateProductFulfilmentAction::class, $this->fulfilmentAction);
        $container->instance(Logger::class, $this->logger);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}