<?php

namespace App\Tests\Unit\Actions\Stock;

use App\Actions\Stock\FulfilSubscriptionAction;
use App\Exceptions\Stock\StockException;
use App\Models\IssueDelivery;
use App\Services\Stock\StockService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class FulfilSubscriptionActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private FulfilSubscriptionAction $action;
    private StockService|MockInterface $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = Mockery::mock(StockService::class);
        $this->action = new FulfilSubscriptionAction($this->stockService);
    }

    // ── reserve() ─────────────────────────────────────────────────────────────

    public function test_reserve_delegates_to_stock_service_and_returns_reservation_id(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->stock_quantity = 15;

        $this->stockService
            ->shouldReceive('reserve')
            ->once()
            ->with($issue, 2, 5)
            ->andReturn(42);

        $reservationId = $this->action->reserve($issue, 2);

        $this->assertEquals(42, $reservationId);
    }

    public function test_reserve_passes_custom_low_stock_threshold(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->stock_quantity = 15;

        $this->stockService
            ->shouldReceive('reserve')
            ->once()
            ->with($issue, 1, 3)
            ->andReturn(7);

        $reservationId = $this->action->reserve($issue, 1, 3);

        $this->assertEquals(7, $reservationId);
    }

    public function test_reserve_propagates_stock_exception_when_insufficient_stock(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->issue_title = 'Sold Out Issue';
        $issue->stock_quantity = 0;

        $this->stockService
            ->shouldReceive('reserve')
            ->once()
            ->andThrow(StockException::insufficientStock('Sold Out Issue', 0, 1));

        $this->expectException(StockException::class);
        $this->expectExceptionMessage("Insufficient stock for 'Sold Out Issue'");

        $this->action->reserve($issue, 1);
    }

    // ── confirm() ─────────────────────────────────────────────────────────────

    public function test_confirm_delegates_to_stock_service(): void
    {
        $this->stockService
            ->shouldReceive('confirm')
            ->once()
            ->with(42);

        $this->action->confirm(42);
    }

    public function test_confirm_propagates_stock_exception_for_unknown_reservation(): void
    {
        $this->stockService
            ->shouldReceive('confirm')
            ->once()
            ->with(999)
            ->andThrow(StockException::itemNotFound('reservation #999'));

        $this->expectException(StockException::class);
        $this->expectExceptionMessage('reservation #999');

        $this->action->confirm(999);
    }

    // ── release() ─────────────────────────────────────────────────────────────

    public function test_release_delegates_to_stock_service(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->stock_quantity = 3;

        $this->stockService
            ->shouldReceive('release')
            ->once()
            ->with($issue, 2);

        $this->action->release($issue, 2);
    }

    // ── reserve → confirm flow ────────────────────────────────────────────────

    public function test_full_reserve_then_confirm_flow(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->stock_quantity = 5;

        $this->stockService->shouldReceive('reserve')->once()->with($issue, 1, 5)->andReturn(1);
        $this->stockService->shouldReceive('confirm')->once()->with(1);

        $reservationId = $this->action->reserve($issue, 1);
        $this->action->confirm($reservationId);
    }

    // ── reserve → release flow ────────────────────────────────────────────────

    public function test_full_reserve_then_release_flow_on_payment_failure(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 10;
        $issue->stock_quantity = 5;

        $this->stockService->shouldReceive('reserve')->once()->with($issue, 2, 5)->andReturn(1);
        $this->stockService->shouldReceive('release')->once()->with($issue, 2);

        $this->action->reserve($issue, 2);
        $this->action->release($issue, 2);
    }
}