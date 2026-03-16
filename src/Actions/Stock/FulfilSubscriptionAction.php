<?php

namespace App\Actions\Stock;

use App\Exceptions\Stock\StockException;
use App\Models\IssueDelivery;
use App\Services\Stock\StockService;

/**
 * High-level use case: reserve and confirm/release stock for a print subscription issue.
 *
 * Phase 1 (inside caller's transaction):
 *   Call reserve() — decrements stock, returns a reservation ID.
 *
 * Phase 3 success (called after payment confirmed):
 *   Call confirm($reservationId) — fires StockConfirmed.
 *
 * Phase 3 failure (called after payment failure):
 *   Call release($issue, $quantity) — restores stock, fires StockReleased.
 *
 * The Phase 1 transaction boundary is owned by OneTimeSubscriptionCheckoutService.
 * confirm() and release() each own their own single-write transactions internally
 * via StockService.
 */
class FulfilSubscriptionAction
{
    public function __construct(
        private readonly StockService $stockService,
    )
    {
    }

    /**
     * Reserve stock for the given issue. MUST be called inside the caller's
     * open transaction (Phase 1).
     *
     * @return int Reservation ID — must be stored and passed to confirm() or release().
     * @throws StockException if the issue has insufficient stock.
     */
    public function reserve(IssueDelivery $issue, int $quantity, int $lowStockThreshold = 5): int
    {
        return $this->stockService->reserve($issue, $quantity, $lowStockThreshold);
    }

    /**
     * Confirm a reservation after successful payment (Phase 3 success path).
     *
     * @throws StockException if the reservation ID is unknown.
     */
    public function confirm(int $reservationId): void
    {
        $this->stockService->confirm($reservationId);
    }

    /**
     * Release (restore) reserved stock after payment failure (Phase 3 failure path).
     */
    public function release(IssueDelivery $issue, int $quantity): void
    {
        $this->stockService->release($issue, $quantity);
    }
}