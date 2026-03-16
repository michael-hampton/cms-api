<?php

namespace App\Repositories\Stock;

/**
 * Common persistence contract for any model that owns a `stock_quantity` column.
 *
 * Implementations: ProductRepository, IssueDeliveryRepository.
 * StockService routes allocation/release calls through this interface.
 *
 * @template TModel of object
 */
interface StockRepositoryInterface
{
    /**
     * Lock the row for update and return the model, or null if not found.
     * Must be called from within an open transaction.
     *
     * @return TModel|null
     */
    public function lockForUpdate(int $id): ?object;

    /**
     * Atomically decrement stock_quantity by $quantity.
     * Returns the updated model.
     *
     * @return TModel
     */
    public function decrementStock(int $id, int $quantity): object;

    /**
     * Atomically increment stock_quantity by $quantity.
     * Returns the updated model.
     *
     * @return TModel
     */
    public function incrementStock(int $id, int $quantity): object;
}