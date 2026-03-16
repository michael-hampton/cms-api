<?php

namespace App\Tests\Unit\Actions\Stock;

use App\Actions\Stock\ApplyGiftPromotionAction;
use App\Actions\Stock\FulfilSubscriptionAction;
use App\Actions\Stock\PurchaseProductAction;
use App\Exceptions\Stock\StockException;
use App\Models\IssueDelivery;
use App\Models\Product;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ApplyGiftPromotionActionTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private ApplyGiftPromotionAction $action;
    private PurchaseProductAction|MockInterface $purchaseProductAction;
    private FulfilSubscriptionAction|MockInterface $fulfilSubscriptionAction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->purchaseProductAction = Mockery::mock(PurchaseProductAction::class);
        $this->fulfilSubscriptionAction = Mockery::mock(FulfilSubscriptionAction::class);

        $this->action = new ApplyGiftPromotionAction(
            $this->purchaseProductAction,
            $this->fulfilSubscriptionAction,
        );
    }

    // ── Physical product target ────────────────────────────────────────────────

    public function test_execute_calls_purchase_product_action_for_product_target(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($product, 1, 5);

        $this->fulfilSubscriptionAction->shouldNotReceive('reserve');

        $this->action->execute($product, 1);
    }

    public function test_execute_passes_quantity_and_threshold_for_product_target(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->with($product, 3, 2);

        $this->action->execute($product, 3, 2);
    }

    public function test_execute_propagates_stock_exception_for_product_target(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->name = 'Gift Book';
        $product->stock_quantity = 0;

        $this->purchaseProductAction
            ->shouldReceive('execute')
            ->once()
            ->andThrow(StockException::insufficientStock('Gift Book', 0, 1));

        $this->expectException(StockException::class);
        $this->expectExceptionMessage("Insufficient stock for 'Gift Book'");

        $this->action->execute($product, 1);
    }

    // ── Subscription issue target ──────────────────────────────────────────────

    public function test_execute_calls_fulfil_subscription_action_for_issue_target(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 5;
        $issue->stock_quantity = 20;

        $this->fulfilSubscriptionAction
            ->shouldReceive('reserve')
            ->once()
            ->with($issue, 1, 5)
            ->andReturn(99);

        $this->purchaseProductAction->shouldNotReceive('execute');

        $this->action->execute($issue, 1);
    }

    public function test_execute_passes_quantity_and_threshold_for_issue_target(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 5;
        $issue->stock_quantity = 20;

        $this->fulfilSubscriptionAction
            ->shouldReceive('reserve')
            ->once()
            ->with($issue, 2, 3)
            ->andReturn(1);

        $this->action->execute($issue, 2, 3);
    }

    public function test_execute_propagates_stock_exception_for_issue_target(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 5;
        $issue->issue_title = 'Gift Issue';
        $issue->stock_quantity = 0;

        $this->fulfilSubscriptionAction
            ->shouldReceive('reserve')
            ->once()
            ->andThrow(StockException::insufficientStock('Gift Issue', 0, 1));

        $this->expectException(StockException::class);
        $this->expectExceptionMessage("Insufficient stock for 'Gift Issue'");

        $this->action->execute($issue, 1);
    }

    // ── Type routing ──────────────────────────────────────────────────────────

    public function test_product_target_never_calls_fulfil_subscription_action(): void
    {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 1;
        $product->stock_quantity = 10;

        $this->purchaseProductAction->shouldReceive('execute')->once();
        $this->fulfilSubscriptionAction->shouldNotReceive('reserve');

        $this->action->execute($product, 1);
    }

    public function test_issue_target_never_calls_purchase_product_action(): void
    {
        $issue = Mockery::mock(IssueDelivery::class)->makePartial();
        $issue->id = 5;
        $issue->stock_quantity = 10;

        $this->fulfilSubscriptionAction->shouldReceive('reserve')->once()->andReturn(1);
        $this->purchaseProductAction->shouldNotReceive('execute');

        $this->action->execute($issue, 1);
    }
}