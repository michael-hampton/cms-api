<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\PrintFulfillmentStatus;
use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\PrintFulfillment;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class PrintFulfillmentRepository extends Repository
{
    private readonly SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('print-fulfilment');
        $this->searchEngine = new SearchEngine($config);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = PrintFulfillment::with(['subscription', 'subscriptionIssueFulfilment', 'batch', 'batch.issueDelivery']);
        return $this->searchEngine->search($query, $criteria);
    }

    /**
     * Persist a print fulfilment record.
     *
     * territory_id records which territory edition this subscriber receives.
     * A null territory_id means the subscriber receives the global/default edition.
     */
    public function createFullfilment(
        int     $batchId,
        int     $subscriptionIssueFulfilmentId,
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
            'subscription_issue_fulfilment_id' => $subscriptionIssueFulfilmentId,
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
            ->whereHas('subscriptionIssueFulfilment', function ($q) use ($issueDeliveryId) {
                $q->where('issue_delivery_id', $issueDeliveryId);
            })
            ->get()
            ->groupBy('territory_id');
    }

    /**
     * Check whether a fulfilment already exists for this subscription + subscription_issue_fulfilments + territory.
     * Idempotency guard in PrintDeliveryChannel — prevents duplicate physical shipments
     * when a queue job is retried after a partial failure.
     */
    public function existsForSubscriptionDeliveryAndTerritory(
        int  $subscriptionId,
        int  $subscriptionIssueFulfilmentId,
        ?int $territoryId,
    ): bool
    {
        return PrintFulfillment::where('subscription_id', $subscriptionId)
            ->where('subscription_issue_fulfilment_id', $subscriptionIssueFulfilmentId)
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

    /**
     * @param int[] $subscriptionIssueFulfilmentIds
     * @return Collection<PrintFulfillment>
     */
    public function findBySubscriptionIssueFulfilmentIds(array $subscriptionIssueFulfilmentIds): Collection
    {
        if (empty($subscriptionIssueFulfilmentIds)) {
            return new Collection([]);
        }

        return PrintFulfillment::whereIn('subscription_issue_fulfilment_id', $subscriptionIssueFulfilmentIds)
            ->get();
    }

    /**
     * Marks only the given rows as exported — unlike markAllExported(), this
     * does NOT touch every fulfilment in a batch. Used by
     * BackIssueReplacementCopyDispatchService, which exports individual
     * back-issue rows that may share a batch with unrelated standard rows
     * still awaiting the normal Label Run export.
     *
     * @param int[] $printFulfillmentIds
     */
    public function markExported(array $printFulfillmentIds): void
    {
        if (empty($printFulfillmentIds)) {
            return;
        }

        PrintFulfillment::whereIn('id', $printFulfillmentIds)
            ->update(['status' => PrintFulfillmentStatus::EXPORTED->value]);
    }

    public function findBySubscriptionIssueFulfilmentAndBatch(
        int $subscriptionIssueFulfilmentId,
        int $printBatchId,
    ): ?PrintFulfillment
    {
        return PrintFulfillment::where('subscription_issue_fulfilment_id', $subscriptionIssueFulfilmentId)
            ->where('batch_id', $printBatchId)
            ->first();
    }

    public function findBySubscriptionDeliveryAndTerritory(
        int  $subscriptionId,
        int  $subscriptionIssueFulfilmentId,
        ?int $territoryId,
    ): ?PrintFulfillment
    {
        return PrintFulfillment::where('subscription_id', $subscriptionId)
            ->where('subscription_issue_fulfilment_id', $subscriptionIssueFulfilmentId)
            ->when(
                is_null($territoryId),
                fn($q) => $q->whereNull('territory_id'),
                fn($q) => $q->where('territory_id', $territoryId),
            )
            ->first();
    }

    public function listForBatch(int $batchId, array $filters): Collection
    {
        return PrintFulfillment::where('batch_id', $batchId)
            ->when(!empty($filters['status']), function ($query) use ($filters) {
                return $query->where('status', $filters['status']);
            })
            ->get();
    }

    protected function getModelClass(): string
    {
        return PrintFulfillment::class;
    }
}