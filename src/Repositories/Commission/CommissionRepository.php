<?php

declare(strict_types=1);

namespace App\Repositories\Commission;

use App\Framework\Support\Collection;
use App\Models\OrderItem;

/**
 * Commission data is stored directly on order_items:
 *   - commission_rate    DECIMAL — the rate applied to this line item
 *   - commission_amount  DECIMAL — the platform's cut
 *   - net_amount         DECIMAL — what the merchant earns after commission
 *   - product_name       VARCHAR — denormalised; safe to use for display
 *
 * There are no separate commission_rates or order_commissions tables.
 * Rates are captured at order time and live alongside the line item.
 */
class CommissionRepository
{
    /**
     * Monthly commission summary for the merchant.
     *
     * Aggregates across all completed + paid orders for the current
     * calendar month. gross_sales = sum of order_items.total;
     * commission_total and net_earnings are the order_items columns
     * written at order-settlement time.
     *
     * @return array{
     *     gross_sales: float,
     *     commission_total: float,
     *     net_earnings: float,
     *     blended_rate: float,
     * }
     */
    public function summaryForMerchant(int $merchantId): array
    {
        $row = OrderItem::query()
            ->from('oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.merchant_id', $merchantId)
            ->where('o.status', 'completed')
            ->where('o.payment_status', 'paid')
            ->whereMonth('o.completed_at', now_datetime()->format('m'))
            ->whereYear('o.completed_at', now_datetime()->format('Y'))
            ->selectRaw('
                COALESCE(SUM(oi.total), 0)               AS gross_sales,
                COALESCE(SUM(oi.commission_amount), 0)   AS commission_total,
                COALESCE(SUM(oi.net_amount), 0)          AS net_earnings
            ')
            ->first();

        $grossSales = (float)($row['gross_sales'] ?? 0);
        $commissionTotal = (float)($row['commission_total'] ?? 0);
        $netEarnings = (float)($row['net_earnings'] ?? 0);

        $blendedRate = $grossSales > 0
            ? round(($commissionTotal / $grossSales) * 100, 2)
            : 0.0;

        return [
            'gross_sales' => $grossSales,
            'commission_total' => $commissionTotal,
            'net_earnings' => $netEarnings,
            'blended_rate' => $blendedRate,
        ];
    }

    /**
     * Per-product commission breakdown for the current calendar month,
     * ordered by revenue descending.
     *
     * product_name is read from order_items.product_name — the denormalised
     * column — so this query is safe even if the product record is deleted.
     * commission_rate is averaged per product for display; where a single
     * rate applies to all lines this is exact.
     *
     * Each row: product_name, revenue, avg_rate, commission_amount, net_amount
     */
    public function byProductForMerchant(int $merchantId): Collection
    {
        return OrderItem::query()
            ->from('oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.merchant_id', $merchantId)
            ->where('o.status', 'completed')
            ->where('o.payment_status', 'paid')
            ->whereMonth('o.completed_at', now_datetime()->format('m'))
            ->whereYear('o.completed_at', now_datetime()->format('Y'))
            ->groupBy('oi.product_id', 'oi.product_name')
            ->selectRaw('
                oi.product_name,
                ROUND(AVG(oi.commission_rate), 2)       AS avg_rate,
                COALESCE(SUM(oi.total), 0)              AS revenue,
                COALESCE(SUM(oi.commission_amount), 0)  AS commission_amount,
                COALESCE(SUM(oi.net_amount), 0)         AS net_amount
            ')
            ->orderByDesc('revenue')
            ->get();
    }

    /**
     * Distinct commission rates applied to this merchant's settled lines
     * this month. Since rates live on line items there is no separate rate
     * schedule table — this surfaces the actual rates charged.
     *
     * Each row: commission_rate (float), product_names (string), line_count (int)
     */
    public function ratesByMerchant(int $merchantId): Collection
    {
        return OrderItem::query()
            ->from('oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->where('o.merchant_id', $merchantId)
            ->where('o.status', 'completed')
            ->where('o.payment_status', 'paid')
            ->whereMonth('o.completed_at', now_datetime()->format('m'))
            ->whereYear('o.completed_at', now_datetime()->format('Y'))
            ->whereNotNull('oi.commission_rate')
            ->groupBy('oi.commission_rate')
            ->selectRaw("
                oi.commission_rate,
                GROUP_CONCAT(DISTINCT oi.product_name ORDER BY oi.product_name SEPARATOR ', ') AS product_names,
                COUNT(*) AS line_count
            ")
            ->orderBy('oi.commission_rate')
            ->get();
    }
}