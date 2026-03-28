<?php

declare(strict_types=1);

namespace App\Actions\Product;

use App\Events\Products\ProductFulfilmentCreated;
use App\Framework\Support\Logger;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductFulfilment;
use App\Repositories\Product\ProductBatchRepository;
use App\Repositories\Product\ProductFulfilmentRepository;
use App\Services\Product\Fulfilment\ProductFulfilmentDecisionService;

/**
 * Creates a ProductFulfilment record for a single order line.
 *
 * Parallel to CreatePrintFulfillmentAction in the print pipeline.
 * CreatePrintFulfillmentAction is closed for modification — this is a new
 * class with the same structural contract but operating on Order/OrderLine.
 *
 * Responsibilities:
 *   1. Resolve territory + address snapshot via ProductFulfilmentDecisionService.
 *   2. Idempotency guard: return existing fulfilment if already created for
 *      this order line + territory.
 *   3. Find or create a ProductBatch for this run + territory.
 *   4. Persist ProductFulfilment.
 *   5. Emit ProductFulfilmentCreated.
 *
 * This action does NOT:
 *   - Dispatch any jobs.
 *   - Know about fulfilment runs or chunks.
 *   - Format data for presentation.
 */
class CreateProductFulfilmentAction
{
    public function __construct(
        private readonly ProductFulfilmentRepository      $fulfilmentRepository,
        private readonly ProductBatchRepository           $batchRepository,
        private readonly ProductFulfilmentDecisionService $decisionService,
        private readonly Logger                           $logger,
    )
    {
    }

    /**
     * @throws \RuntimeException If address resolution fails.
     */
    public function execute(
        Order     $order,
        OrderItem $orderLine,
        int       $fulfilmentRunId,
    ): ProductFulfilment
    {
        $context = $this->decisionService->decide($order, $orderLine);

        // Idempotency guard — if a fulfilment already exists for this order
        // line + territory, a previous attempt already succeeded. Return it.
        if ($this->fulfilmentRepository->existsForOrderLineAndTerritory(
            $orderLine->id,
            $context->territoryId(),
        )) {
            $this->logger->info('CreateProductFulfilmentAction: fulfilment already exists — skipping', [
                'order_id' => $order->id,
                'order_line_id' => $orderLine->id,
                'territory_id' => $context->territoryId(),
            ]);

            return $this->fulfilmentRepository->findForOrderLineAndTerritory(
                $orderLine->id,
                $context->territoryId(),
            );
        }

        $snapshot = $context->addressSnapshot;
        $resolved = $this->buildResolvedAddress($snapshot);

        $batch = $this->batchRepository->findOrCreateForRunAndTerritory(
            $fulfilmentRunId,
            $context->territoryId(),
        );

        $fulfilment = $this->fulfilmentRepository->createProductFulfilment(
            productBatchId: $batch->id,
            orderId: $order->id,
            orderLineId: $orderLine->id,
            sku: $orderLine->sku,
            quantity: $orderLine->quantity,
            fullName: $resolved['full_name'],
            addressSnapshot: $resolved['snapshot'],
            addressLine1: $resolved['address_line_1'],
            addressLine2: $resolved['address_line_2'] ?? null,
            city: $resolved['city'],
            postcode: $resolved['postcode'],
            country: $resolved['country'],
            territoryId: $context->territoryId(),
        );

        $this->logger->info('CreateProductFulfilmentAction: fulfilment created', [
            'order_id' => $order->id,
            'order_line_id' => $orderLine->id,
            'fulfilment_id' => $fulfilment->id,
            'territory_id' => $context->territoryId(),
        ]);

        event(new ProductFulfilmentCreated($fulfilment));

        return $fulfilment;
    }

    private function buildResolvedAddress(array $snapshot): array
    {
        return [
            'full_name' => trim(($snapshot['first_name'] ?? '') . ' ' . ($snapshot['last_name'] ?? '')),
            'address_line_1' => $snapshot['address_line_1'],
            'address_line_2' => $snapshot['address_line_2'] ?? null,
            'city' => $snapshot['city'],
            'postcode' => $snapshot['postcode'],
            'country' => $snapshot['country'],
            'snapshot' => $snapshot,
        ];
    }
}