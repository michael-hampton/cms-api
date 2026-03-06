<?php

declare(strict_types=1);

namespace App\DTO\Merchant;

/**
 * Immutable snapshot of merchant engagement analytics.
 *
 * Passed directly to the dashboard view — no raw query results leak
 * past the service boundary.
 */
final class AnalyticsSnapshot
{
    public function __construct(
        public readonly int   $days,

        // ── Offer clicks ──────────────────────────────────────────────────────
        public readonly int   $offerClicks,
        public readonly int   $offerRenders,
        public readonly float $offerClickDelta,   // % change vs prior period

        // ── Deal clicks ───────────────────────────────────────────────────────
        public readonly int   $dealClicks,
        public readonly int   $dealRenders,
        public readonly float $dealClickDelta,

        // ── Product views ─────────────────────────────────────────────────────
        public readonly int   $productViews,
        public readonly int   $productViewsUnique,
        public readonly float $productViewDelta,

        // ── Daily series (for charts) ─────────────────────────────────────────
        /** @var array<int, array{date: string, clicks: int, renders: int}> */
        public readonly array $offerClicksByDay,
        /** @var array<int, array{date: string, clicks: int, renders: int}> */
        public readonly array $dealClicksByDay,
        /** @var array<int, array{date: string, views: int, unique: int}> */
        public readonly array $productViewsByDay,

        // ── Per-entity breakdowns (for tables) ────────────────────────────────
        /** @var array<int, array{offer_id: int, product_name: string, clicks: int, renders: int, ctr: float}> */
        public readonly array $topOffers,
        /** @var array<int, array{product_id: int, product_name: string, clicks: int, renders: int, ctr: float}> */
        public readonly array $topDealProducts,
        /** @var array<int, array{product_id: int, product_name: string, views: int, unique_users: int}> */
        public readonly array $topViewedProducts,
    )
    {
    }

    // ── Computed helpers used in the view ─────────────────────────────────────

    public function offerCtr(): float
    {
        return $this->offerRenders > 0
            ? round(($this->offerClicks / $this->offerRenders) * 100, 1)
            : 0.0;
    }

    public function dealCtr(): float
    {
        return $this->dealRenders > 0
            ? round(($this->dealClicks / $this->dealRenders) * 100, 1)
            : 0.0;
    }

    public function totalEngagement(): int
    {
        return $this->offerClicks + $this->dealClicks + $this->productViews;
    }

    public function offerClickIsUp(): bool
    {
        return $this->offerClickDelta >= 0;
    }

    public function dealClickIsUp(): bool
    {
        return $this->dealClickDelta >= 0;
    }

    public function productViewIsUp(): bool
    {
        return $this->productViewDelta >= 0;
    }

    /**
     * JSON-encode the daily series for inline <script> blocks.
     */
    public function offerClicksJson(): string
    {
        return json_encode($this->offerClicksByDay, JSON_THROW_ON_ERROR);
    }

    public function dealClicksJson(): string
    {
        return json_encode($this->dealClicksByDay, JSON_THROW_ON_ERROR);
    }

    public function productViewsJson(): string
    {
        return json_encode($this->productViewsByDay, JSON_THROW_ON_ERROR);
    }
}