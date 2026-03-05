<?php

namespace App\Tests\Unit\Repositories\Billing;

use App\Framework\Support\Collection;
use App\Models\Merchant;
use App\Models\Order;
use App\Repositories\Commission\CommissionRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CommissionRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private CommissionRepository $repository;
    private Merchant $merchant;

    public function test_summary_returns_zeros_when_merchant_has_no_orders(): void
    {
        $result = $this->repository->summaryForMerchant($this->merchant->id);

        $this->assertSame(0.0, $result['gross_sales']);
        $this->assertSame(0.0, $result['commission_total']);
        $this->assertSame(0.0, $result['net_earnings']);
        $this->assertSame(0.0, $result['blended_rate']);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    public function test_summary_aggregates_from_order_items_columns(): void
    {
        // Two line items: each £100 total, £10 commission, £90 net
        $this->createSettledOrderWithItem();
        $this->createSettledOrderWithItem();

        $result = $this->repository->summaryForMerchant($this->merchant->id);

        $this->assertSame(200.00, $result['gross_sales']);
        $this->assertSame(20.00, $result['commission_total']);
        $this->assertSame(180.00, $result['net_earnings']);
    }

    // ── summaryForMerchant ────────────────────────────────────────────────────

    /**
     * Creates a completed+paid order with one line item that carries
     * commission data. All monetary amounts default to sensible values
     * that make arithmetic assertions easy to write.
     */
    private function createSettledOrderWithItem(array $orderOverrides = [], array $itemOverrides = []): Order
    {
        $order = $this->createOrder(array_merge([
            'merchant_id' => $this->merchant->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
            'completed_at' => date('Y-m-d H:i:s'),
        ], $orderOverrides));

        $this->createOrderItem($order->id, array_merge([
            'total' => 100.00,
            'commission_rate' => 0.10,   // 10 %
            'commission_amount' => 10.00,
            'net_amount' => 90.00,
            'product_name' => 'Widget',
        ], $itemOverrides));

        return $order;
    }

    public function test_summary_blended_rate_is_commission_over_gross_as_percentage(): void
    {
        // £500 gross, £50 commission = 10 %
        $this->createSettledOrderWithItem(
            ['total' => 500.00],
            ['total' => 500.00, 'commission_amount' => 50.00, 'net_amount' => 450.00]
        );

        $result = $this->repository->summaryForMerchant($this->merchant->id);

        $this->assertSame(10.0, $result['blended_rate']);
    }

    public function test_summary_blended_rate_is_zero_when_no_sales(): void
    {
        $result = $this->repository->summaryForMerchant($this->merchant->id);

        $this->assertSame(0.0, $result['blended_rate']);
    }

    public function test_summary_excludes_pending_orders(): void
    {
        $pendingOrder = $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 200.00,
        ]);
        $this->createOrderItem($pendingOrder->id, [
            'total' => 200.00,
            'commission_amount' => 20.00,
            'net_amount' => 180.00,
        ]);

        $result = $this->repository->summaryForMerchant($this->merchant->id);

        $this->assertSame(0.0, $result['gross_sales']);
    }

    public function test_summary_excludes_orders_from_other_merchants(): void
    {
        $other = $this->createMerchant();
        $order = $this->createOrder([
            'merchant_id' => $other->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 999.00,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
        $this->createOrderItem($order->id, [
            'total' => 999.00,
            'commission_amount' => 99.90,
            'net_amount' => 899.10,
        ]);

        $result = $this->repository->summaryForMerchant($this->merchant->id);

        $this->assertSame(0.0, $result['gross_sales']);
    }

    public function test_summary_excludes_orders_outside_current_month(): void
    {
        $oldOrder = $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
            'completed_at' => date('Y-m-d H:i:s', strtotime('-2 months')),
        ]);
        $this->createOrderItem($oldOrder->id, [
            'total' => 100.00,
            'commission_amount' => 10.00,
            'net_amount' => 90.00,
        ]);

        $result = $this->repository->summaryForMerchant($this->merchant->id);

        $this->assertSame(0.0, $result['gross_sales']);
    }

    public function test_by_product_returns_empty_collection_when_no_orders(): void
    {
        $result = $this->repository->byProductForMerchant($this->merchant->id);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    // ── byProductForMerchant ──────────────────────────────────────────────────

    public function test_by_product_groups_line_items_by_product(): void
    {
        // Two items for the same product across two orders
        $this->createSettledOrderWithItem([], ['product_name' => 'Gadget', 'total' => 80.00, 'commission_amount' => 8.00, 'net_amount' => 72.00]);
        $this->createSettledOrderWithItem([], ['product_name' => 'Gadget', 'total' => 120.00, 'commission_amount' => 12.00, 'net_amount' => 108.00]);
        // One item for a different product
        $this->createSettledOrderWithItem([], ['product_name' => 'Widget', 'total' => 50.00, 'commission_amount' => 5.00, 'net_amount' => 45.00]);

        $result = $this->repository->byProductForMerchant($this->merchant->id);

        $this->assertCount(2, $result);
    }

    public function test_by_product_orders_results_by_revenue_descending(): void
    {
        $this->createSettledOrderWithItem([], ['product_name' => 'Cheap', 'total' => 10.00, 'commission_amount' => 1.00, 'net_amount' => 9.00]);
        $this->createSettledOrderWithItem([], ['product_name' => 'Expensive', 'total' => 500.00, 'commission_amount' => 50.00, 'net_amount' => 450.00]);

        $result = $this->repository->byProductForMerchant($this->merchant->id);
        $firstProduct = $result->first();

        $this->assertSame('Expensive', $firstProduct['product_name']);
    }

    public function test_by_product_sums_commission_amounts_per_product(): void
    {
        // Two orders for the same product
        $this->createSettledOrderWithItem([], ['product_name' => 'Gizmo', 'total' => 100.00, 'commission_amount' => 10.00, 'net_amount' => 90.00]);
        $this->createSettledOrderWithItem([], ['product_name' => 'Gizmo', 'total' => 200.00, 'commission_amount' => 20.00, 'net_amount' => 180.00]);

        $result = $this->repository->byProductForMerchant($this->merchant->id);
        $row = $result->first();

        $this->assertSame(30.00, (float)$row['commission_amount']);
        $this->assertSame(300.00, (float)$row['revenue']);
    }

    public function test_rates_returns_empty_collection_when_no_settled_orders(): void
    {
        $result = $this->repository->ratesByMerchant($this->merchant->id);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    // ── ratesByMerchant ───────────────────────────────────────────────────────

    public function test_rates_returns_one_row_per_distinct_commission_rate(): void
    {
        $this->createSettledOrderWithItem([], ['commission_rate' => 0.10, 'product_name' => 'Widget A']);
        $this->createSettledOrderWithItem([], ['commission_rate' => 0.15, 'product_name' => 'Widget B']);
        // Another item at the same 10% rate — should NOT create a second row
        $this->createSettledOrderWithItem([], ['commission_rate' => 0.10, 'product_name' => 'Widget C']);

        $result = $this->repository->ratesByMerchant($this->merchant->id);

        $this->assertCount(2, $result);
    }

    public function test_rates_includes_commission_rate_and_line_count(): void
    {
        $this->createSettledOrderWithItem([], ['commission_rate' => 0.10, 'product_name' => 'Widget']);

        $result = $this->repository->ratesByMerchant($this->merchant->id);
        $row = $result->first();

        $this->assertArrayHasKey('commission_rate', $row);
        $this->assertArrayHasKey('line_count', $row);
        $this->assertSame(0.1, (float)$row['commission_rate']);
        $this->assertSame(1, (int)$row['line_count']);
    }

    public function test_rates_excludes_other_merchants(): void
    {
        $other = $this->createMerchant();
        $order = $this->createOrder([
            'merchant_id' => $other->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
        $this->createOrderItem($order->id, ['commission_rate' => 0.20, 'product_name' => 'Other Widget']);

        $result = $this->repository->ratesByMerchant($this->merchant->id);

        $this->assertTrue($result->isEmpty());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CommissionRepository();
        $this->merchant = $this->createMerchant();
    }
}