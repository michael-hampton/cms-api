<?php

namespace App\Tests\Unit\Repositories\Billing;

use App\Models\Model;
use App\Repositories\Billing\OrderRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OrderRepositoryMonthlyStatsTest extends FunctionalTestCase
{
    use CreatesTestData;

    private OrderRepository $repository;
    private Model $merchant;

    public function test_returns_zero_when_merchant_has_no_orders(): void
    {
        $result = $this->repository->monthlyStatsForMerchant($this->merchant->id);

        $this->assertSame(0.0, $result->totalRevenue);
        $this->assertSame(0, $result->totalOrders);
    }

    public function test_counts_only_completed_and_paid_orders(): void
    {
        // These should be counted
        $completedOrder = $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 100.00,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        // These should NOT be counted
        $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total' => 200.00,
        ]);
        $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'completed',
            'payment_status' => 'unpaid',
            'total' => 300.00,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->repository->monthlyStatsForMerchant($this->merchant->id);

        $this->assertSame(1, $result->totalOrders);
        $this->assertSame(100.00, $result->totalRevenue);
    }

    public function test_sums_revenue_across_multiple_completed_orders(): void
    {
        foreach ([50.00, 75.00, 125.00] as $total) {
            $this->createOrder([
                'merchant_id' => $this->merchant->id,
                'status' => 'completed',
                'payment_status' => 'paid',
                'total' => $total,
                'completed_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $result = $this->repository->monthlyStatsForMerchant($this->merchant->id);

        $this->assertSame(3, $result->totalOrders);
        $this->assertSame(250.00, $result->totalRevenue);
    }

    public function test_excludes_orders_from_other_merchants(): void
    {
        $otherMerchant = $this->createMerchant();

        $this->createOrder([
            'merchant_id' => $otherMerchant->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 500.00,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->repository->monthlyStatsForMerchant($this->merchant->id);

        $this->assertSame(0, $result->totalOrders);
        $this->assertSame(0.0, $result->totalRevenue);
    }

    public function test_excludes_orders_completed_in_a_prior_month(): void
    {
        $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 999.00,
            // completed two months ago — outside the current calendar month window
            'completed_at' => date('Y-m-d H:i:s', strtotime('-2 months')),
        ]);

        $result = $this->repository->monthlyStatsForMerchant($this->merchant->id);

        $this->assertSame(0, $result->totalOrders);
    }

    public function test_monthsAgo_queries_the_correct_prior_month(): void
    {
        // One order completed last month
        $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 400.00,
            'completed_at' => date('Y-m-d H:i:s', strtotime('first day of last month')),
        ]);

        // One order completed this month — must NOT be included when monthsAgo = 1
        $this->createOrder([
            'merchant_id' => $this->merchant->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total' => 800.00,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);

        $result = $this->repository->monthlyStatsForMerchant($this->merchant->id, monthsAgo: 1);

        $this->assertSame(1, $result->totalOrders);
        $this->assertSame(400.00, $result->totalRevenue);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new OrderRepository();
        $this->merchant = $this->createMerchant();
    }
}