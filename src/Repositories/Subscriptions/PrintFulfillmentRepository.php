<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\PrintFulfillmentStatus;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\PrintFulfillment;
use App\Repositories\Repository;

class PrintFulfillmentRepository extends Repository
{
    /**
     * Persist a print fulfilment record.
     *
     * territory_id records which territory edition this subscriber receives.
     * A null territory_id means the subscriber receives the global/default edition.
     */
    public function createFullfilment(
        int     $batchId,
        int     $issuesDeliveredId,
        int     $subscriptionId,
        string  $fullName,
        array   $addressSnapshot,
        string  $addressLine1,
        ?string $addressLine2,
        string  $city,
        string  $postcode,
        string $country,
        ?int   $territoryId = null,
    ): Model
    {
        return PrintFulfillment::create([
            'batch_id' => $batchId,
            'issues_delivered_id' => $issuesDeliveredId,
            'subscription_id' => $subscriptionId,
            'full_name' => $fullName,
            'delivery_address_snapshot' => $addressSnapshot,
            'address_line_1' => $addressLine1,
            'address_line_2' => $addressLine2,
            'city' => $city,
            'postcode' => $postcode,
            'country' => $country,
            'territory_id' => $territoryId,
            'status' => PrintFulfillmentStatus::QUEUED->value,
        ]);
    }

    /**
     * @return PrintFulfillment[]
     */
    public function findByBatch(int $batchId): array
    {
        return PrintFulfillment::where('batch_id', $batchId)->get()->all();
    }

    /**
     * Return fulfilments for a given issue delivery, grouped by territory_id.
     * Used by BatchBuilderService to construct one batch per territory.
     *
     * DB-level groupBy avoids loading all fulfilments into memory (large-volume NFR).
     * The groupBy key is the raw territory_id value (null becomes empty string "").
     *
     * @return Collection<Collection<PrintFulfillment>>
     */
    public function findByIssueDeliveryGroupedByTerritory(int $issueDeliveryId): Collection
    {

        return PrintFulfillment::query()
            ->whereHas('issuesDelivered', function ($q) use ($issueDeliveryId) {
                $q->where('issue_delivery_id', $issueDeliveryId);
            })
            ->get()
            ->groupBy('territory_id');
    }

    /**
     * Check whether a fulfilment already exists for this subscription + issues_delivered + territory.
     * Idempotency guard in PrintDeliveryChannel — prevents duplicate physical shipments
     * when a queue job is retried after a partial failure.
     */
    public function existsForSubscriptionDeliveryAndTerritory(
        int  $subscriptionId,
        int  $issuesDeliveredId,
        ?int $territoryId,
    ): bool
    {
        return PrintFulfillment::where('subscription_id', $subscriptionId)
            ->where('issues_delivered_id', $issuesDeliveredId)
            ->when(
                is_null($territoryId),
                fn($q) => $q->whereNull('territory_id'),
                fn($q) => $q->where('territory_id', $territoryId)
            )
            ->exists();
    }

    public function markAllExported(int $batchId): void
    {
        PrintFulfillment::where('batch_id', $batchId)
            ->update(['status' => PrintFulfillmentStatus::EXPORTED->value]);
    }

    public function findByIssuesDeliveredAndBatch(
        int $issuesDeliveredId,
        int $printBatchId,
    ): ?PrintFulfillment
    {
        return PrintFulfillment::where('issues_delivered_id', $issuesDeliveredId)
            ->where('batch_id', $printBatchId)
            ->first();
    }

    public function findBySubscriptionDeliveryAndTerritory(
        int  $subscriptionId,
        int  $issuesDeliveredId,
        ?int $territoryId,
    ): ?PrintFulfillment
    {
        return PrintFulfillment::where('subscription_id', $subscriptionId)
            ->where('issues_delivered_id', $issuesDeliveredId)
            ->when(
                is_null($territoryId),
                fn($q) => $q->whereNull('territory_id'),
                fn($q) => $q->where('territory_id', $territoryId),
            )
            ->first();
    }

    protected function getModelClass(): string
    {
        return PrintFulfillment::class;
    }
}