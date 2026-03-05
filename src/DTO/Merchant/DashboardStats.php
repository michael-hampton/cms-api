<?php

declare(strict_types=1);

namespace App\DTO\Merchant;

/**
 * Immutable value object carrying every computed metric for the overview panel.
 *
 * Using a typed DTO rather than an array keeps the view honest:
 * missing properties are a compile-time error, not a silent null.
 */
final readonly class DashboardStats
{
    /**
     * @param array<int, float> $chartSeries Daily revenue values, oldest-first, length = $days requested
     */
    public function __construct(
        public float $totalRevenue,
        public float $revenueDelta,
        public int   $totalOrders,
        public float $ordersDelta,
        public int   $totalImpressions,
        public float $impressionsDelta,
        public float $averageRating,
        public float $ratingDelta,
        public array $chartSeries,
    )
    {
    }

    /**
     * Formatted string for the revenue delta badge, e.g. "↑ 12.4% vs last month"
     */
    public function revenueDeltaLabel(): string
    {
        $arrow = $this->revenueIsUp() ? '↑' : '↓';
        return "{$arrow} " . abs($this->revenueDelta) . '% vs last month';
    }

    public function revenueIsUp(): bool
    {
        return $this->revenueDelta >= 0;
    }

    public function ordersDeltaLabel(): string
    {
        $arrow = $this->ordersIsUp() ? '↑' : '↓';
        return "{$arrow} " . abs($this->ordersDelta) . '% vs last month';
    }

    public function ordersIsUp(): bool
    {
        return $this->ordersDelta >= 0;
    }

    public function impressionsDeltaLabel(): string
    {
        $arrow = $this->impressionsIsUp() ? '↑' : '↓';
        return "{$arrow} " . abs($this->impressionsDelta) . '% vs last month';
    }

    public function impressionsIsUp(): bool
    {
        return $this->impressionsDelta >= 0;
    }

    public function ratingDeltaLabel(): string
    {
        $arrow = $this->ratingIsUp() ? '↑' : '↓';
        return "{$arrow} " . abs($this->ratingDelta) . ' this month';
    }

    public function ratingIsUp(): bool
    {
        return $this->ratingDelta >= 0;
    }

    /**
     * JSON-encoded chart series for injection into the view's <script> block.
     */
    public function chartSeriesJson(): string
    {
        return json_encode($this->chartSeries, JSON_THROW_ON_ERROR);
    }
}