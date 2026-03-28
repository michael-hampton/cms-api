<?php

declare(strict_types=1);

namespace App\Repositories\Product;

use App\Enums\Products\ProductFulfilmentStatus;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\ProductFulfilment;
use App\Repositories\Repository;

/**
 * Persistence for ProductFulfilment records.
 *
 * Parallel to PrintFulfillmentRepository. No business logic lives here.
 */
class ProductFulfilmentRepository extends Repository
{
    public function createProductFulfilment(
        int     $productBatchId,
        int     $orderId,
        int     $orderLineId,
        string  $sku,
        int     $quantity,
        string  $fullName,
        array   $addressSnapshot,
        string  $addressLine1,
        ?string $addressLine2,
        string  $city,
        string  $postcode,
        string  $country,
        ?int    $territoryId = null,
    ): Model
    {
        return ProductFulfilment::create([
            'product_batch_id' => $productBatchId,
            'order_id' => $orderId,
            'order_line_id' => $orderLineId,
            'sku' => $sku,
            'quantity' => $quantity,
            'full_name' => $fullName,
            'delivery_address_snapshot' => $addressSnapshot,
            'address_line_1' => $addressLine1,
            'address_line_2' => $addressLine2,
            'city' => $city,
            'postcode' => $postcode,
            'country' => $country,
            'territory_id' => $territoryId,
            'status' => ProductFulfilmentStatus::QUEUED->value,
        ]);
    }

    /**
     * Idempotency guard — true when a fulfilment already exists for this
     * order line + territory. Prevents duplicate physical shipments on retry.
     */
    public function existsForOrderLineAndTerritory(
        int  $orderLineId,
        ?int $territoryId,
    ): bool
    {
        return ProductFulfilment::where('order_line_id', $orderLineId)
            ->when(
                is_null($territoryId),
                fn($q) => $q->whereNull('territory_id'),
                fn($q) => $q->where('territory_id', $territoryId),
            )
            ->exists();
    }

    /**
     * Find the fulfilment for a specific order line + territory.
     * Returns null when not yet created (caller must create it).
     */
    public function findForOrderLineAndTerritory(
        int  $orderLineId,
        ?int $territoryId,
    ): ?ProductFulfilment
    {
        return ProductFulfilment::where('order_line_id', $orderLineId)
            ->when(
                is_null($territoryId),
                fn($q) => $q->whereNull('territory_id'),
                fn($q) => $q->where('territory_id', $territoryId),
            )
            ->first();
    }

    /**
     * @return ProductFulfilment[]
     */
    public function findByBatch(int $productBatchId): array
    {
        return ProductFulfilment::where('product_batch_id', $productBatchId)
            ->get()
            ->all();
    }

    /**
     * Group fulfilments for a fulfilment run by territory_id.
     * Used by ProductBatchBuilderService.
     *
     * @return Collection<Collection<ProductFulfilment>>
     */
    public function findByRunGroupedByTerritory(int $fulfilmentRunId): Collection
    {
        return ProductFulfilment::query()
            ->whereHas('productBatch', fn($q) => $q->where('fulfilment_run_id', $fulfilmentRunId))
            ->get()
            ->groupBy('territory_id');
    }

    public function markAllExported(int $productBatchId): void
    {
        ProductFulfilment::where('product_batch_id', $productBatchId)
            ->update(['status' => ProductFulfilmentStatus::EXPORTED->value]);
    }

    protected function getModelClass(): string
    {
        return ProductFulfilment::class;
    }
}