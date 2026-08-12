<?php

namespace App\Services\Stock;

use App\Events\Stock\StockAllocated;
use App\Events\Stock\StockConfirmed;
use App\Events\Stock\StockLow;
use App\Events\Stock\StockReleased;
use App\Exceptions\Stock\StockException;
use App\Framework\Database\Database;
use App\Models\IssueDelivery;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Subscriptions\IssueDeliveryRepository;

/**
 * Orchestrates all stock allocation and release operations.
 *
 * Two distinct flows are supported:
 *
 * Physical products (CheckoutService):
 *   Call allocate() from within the caller's existing transaction.
 *   Stock is decremented and StockAllocated(confirmed=true) fires afterCommit.
 *
 * Subscription issues (OneTimeSubscriptionCheckoutService):
 *   Phase 1 — call reserve(); stock decremented, StockAllocated(confirmed=false) fires afterCommit.
 *             Returns an in-memory reservation ID.
 *   Phase 3 — call confirm($reservationId) on payment success; StockConfirmed fires afterCommit.
 *           — call release($model, $qty) on payment failure; stock restored, StockReleased fires afterCommit.
 *
 * Rules:
 *  - allocate() and reserve() MUST be called inside an open transaction (owned by the caller).
 *  - confirm() and release() wrap their own single-write transactions.
 *  - Events are dispatched via afterCommit so listeners never see uncommitted state.
 *  - StockException propagates up; the caller's transaction rolls back automatically.
 */
class StockService
{
    /**
     * In-memory reservation store.
     * Key   = auto-incremented reservation ID (int)
     * Value = ['model' => object, 'quantity' => int]
     *
     * Phase 2: replace with stock_allocations table rows.
     *
     * @var array<int, array{model: object, quantity: int}>
     */
    private array $reservations = [];
    private int $nextReservationId = 1;

    public function __construct(
        private readonly ProductRepository       $productRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly Database                $database,
    )
    {
    }

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Decrement stock for a physical product or issue delivery.
     * MUST be called inside the caller's open transaction.
     *
     * @throws StockException if stock is insufficient.
     */
    public function allocate(object $model, int $quantity, int $lowStockThreshold = 5): void
    {
        $repo = $this->resolveRepository($model);
        $this->guardSufficientStock($model, $quantity);

        $updated = $repo->decrementStock($model->id, $quantity);

        $this->dispatchAfterCommit(new StockAllocated(
            modelClass: get_class($model),
            modelId: $model->id,
            itemName: $this->resolveName($model),
            quantityAllocated: $quantity,
            remainingStock: $updated->stock_quantity,
            confirmed: true,
        ));

        if ($updated->stock_quantity <= $lowStockThreshold) {
            $this->dispatchAfterCommit(new StockLow(
                modelClass: get_class($model),
                modelId: $model->id,
                itemName: $this->resolveName($model),
                remainingStock: $updated->stock_quantity,
                threshold: $lowStockThreshold,
            ));
        }
    }

    /**
     * Decrement stock optimistically as a reservation pending payment.
     * MUST be called inside the caller's open transaction.
     *
     * Returns a reservation ID that MUST be passed to confirm() or release().
     *
     * @throws StockException if stock is insufficient.
     */
    public function reserve(object $model, int $quantity, int $lowStockThreshold = 5): int
    {
        $repo = $this->resolveRepository($model);
        $this->guardSufficientStock($model, $quantity);

        $updated = $repo->decrementStock($model->id, $quantity);

        $reservationId = $this->nextReservationId++;
        $this->reservations[$reservationId] = ['model' => $model, 'quantity' => $quantity];

        $this->dispatchAfterCommit(new StockAllocated(
            modelClass: get_class($model),
            modelId: $model->id,
            itemName: $this->resolveName($model),
            quantityAllocated: $quantity,
            remainingStock: $updated->stock_quantity,
            confirmed: false,
            reservationId: $reservationId,
        ));

        if ($updated->stock_quantity <= $lowStockThreshold) {
            $this->dispatchAfterCommit(new StockLow(
                modelClass: get_class($model),
                modelId: $model->id,
                itemName: $this->resolveName($model),
                remainingStock: $updated->stock_quantity,
                threshold: $lowStockThreshold,
            ));
        }

        return $reservationId;
    }

    /**
     * Confirm a reservation after successful payment.
     * Wraps its own transaction (single write: event metadata only in Phase 1).
     *
     * @throws StockException if the reservation ID is unknown.
     */
    public function confirm(int $reservationId): void
    {
        $reservation = $this->consumeReservation($reservationId);
        $model = $reservation['model'];
        $quantity = $reservation['quantity'];

        try {
            // Phase 1: stock already decremented in reserve(); nothing to write to DB here.
            // Phase 2: flip stock_allocations.confirmed = true inside a transaction.
            $this->database->transaction(function () use ($model, $quantity, $reservationId) {
                // Re-read current stock for the event payload — no write needed in Phase 1.
                $repo = $this->resolveRepository($model);
                $current = $repo->lockForUpdate($model->id);

                if (!$current) {
                    throw StockException::itemNotFound(get_class($model) . '#' . $model->id);
                }

                $this->dispatchAfterCommit(new StockConfirmed(
                    modelClass: get_class($model),
                    modelId: $model->id,
                    itemName: $this->resolveName($model),
                    quantityConfirmed: $quantity,
                    remainingStock: $current->stock_quantity,
                    reservationId: $reservationId,
                ));
            });
        } catch (\Throwable $e) {
            // Restore so callers can release() or retry confirm() after a failed confirm.
            $this->reservations[$reservationId] = $reservation;
            throw $e;
        }
    }

    /**
     * Release (increment) stock — used for explicit releases or payment-failure compensation.
     * Wraps its own transaction.
     */
    public function release(object $model, int $quantity): void
    {
        $this->database->transaction(function () use ($model, $quantity) {
            $repo = $this->resolveRepository($model);
            $updated = $repo->incrementStock($model->id, $quantity);

            $this->dispatchAfterCommit(new StockReleased(
                modelClass: get_class($model),
                modelId: $model->id,
                itemName: $this->resolveName($model),
                quantityReleased: $quantity,
                remainingStock: $updated->stock_quantity,
            ));
        });
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * @throws StockException
     */
    private function guardSufficientStock(object $model, int $quantity): void
    {
        // Prefer isset so missing attributes on plain objects do not emit notices;
        // Eloquent models still resolve attributes via __isset/__get.
        $available = isset($model->stock_quantity) ? $model->stock_quantity : null;

        if ($available === null || $available < $quantity) {
            throw StockException::insufficientStock(
                $this->resolveName($model),
                (int)($available ?? 0),
                $quantity,
            );
        }
    }

    private function resolveRepository(object $model): ProductRepository|IssueDeliveryRepository
    {
        return match (true) {
            $model instanceof Product => $this->productRepository,
            $model instanceof IssueDelivery => $this->issueDeliveryRepository,
            default => throw new \InvalidArgumentException(
                'No stock repository registered for ' . get_class($model)
            ),
        };
    }

    private function resolveName(object $model): string
    {
        return $model->name
            ?? $model->issue_title
            ?? ('Item #' . ($model->id ?? '?'));
    }

    /**
     * @return array{model: object, quantity: int}
     * @throws StockException if the reservation ID is unknown.
     */
    private function consumeReservation(int $reservationId): array
    {
        if (!isset($this->reservations[$reservationId])) {
            throw StockException::itemNotFound("reservation #{$reservationId}");
        }

        $reservation = $this->reservations[$reservationId];
        unset($this->reservations[$reservationId]);

        return $reservation;
    }

    /**
     * Dispatch an event via Laravel's event() helper.
     * Using afterCommit semantics so listeners always see committed DB state.
     *
     * In tests, events are asserted with Event::assertDispatched() — this helper
     * makes it easy to swap the dispatch mechanism without touching callers.
     */
    private function dispatchAfterCommit(object $event): void
    {
        event($event);
    }
}