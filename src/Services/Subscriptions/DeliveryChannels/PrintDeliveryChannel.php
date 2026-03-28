<?php

namespace App\Services\Subscriptions\DeliveryChannels;

use App\DTO\Subscriptions\FulfilmentDecisionContext;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\DeliveryChannelInterface;
use App\Services\Subscriptions\Printing\PrintAddressResolver;

/**
 * Delivery channel for print subscriptions.
 *
 * Responsibilities (each delegated to a collaborator):
 *   - Guard: subscription has an ID.
 *   - Resolve delivery address (PrintAddressResolver).
 *   - Create PrintBatch record (PrintBatchRepository).
 *   - Guard: IssuesDelivered record exists.
 *   - Idempotency guard: skip if fulfilment already exists for this
 *     subscription + issues_delivered + territory combination.
 *   - Create PrintFulfillment record with address snapshot and territory
 *     (PrintFulfillmentRepository).
 *   - Register post-commit callback to dispatch ExportPrintBatchJob.
 *
 * This channel is called inside a transaction managed by DeliverIssueDeliveryJob.
 * The export job is dispatched only after the outer transaction commits so the
 * queue worker always sees committed fulfilment rows.
 *
 * Territory is recorded on the fulfilment when a FulfilmentDecisionContext is
 * supplied. When no context is given (legacy path) the fulfilment is written
 * without a territory (global/default edition).
 */
class PrintDeliveryChannel implements DeliveryChannelInterface
{
    public function __construct(
        private readonly PrintBatchRepository       $batchRepository,
        private readonly PrintFulfillmentRepository $fulfillmentRepository,
        private readonly IssuesDeliveredRepository  $issuesDeliveredRepository,
        private readonly PrintAddressResolver       $addressResolver,
        private readonly Database                   $database,
        private readonly Logger                     $logger,
    )
    {
    }

    /**
     * Process a single print delivery.
     *
     * @param Subscription $subscription The subscriber being fulfilled.
     * @param IssueDelivery $issueDelivery The issue being delivered.
     * @param FulfilmentDecisionContext|null $context Optional territory/address context
     *                                                       pre-resolved by FulfilmentDecisionService.
     *                                                       When null the channel resolves the address
     *                                                       itself and writes no territory.
     *
     * @throws \RuntimeException When the subscription has no ID.
     * @throws \RuntimeException When no valid delivery address exists.
     * @throws \RuntimeException When the IssuesDelivered record cannot be found.
     */
    public function send(
        Subscription               $subscription,
        IssueDelivery              $issueDelivery,
        ?FulfilmentDecisionContext $context = null,
    ): void
    {
        if (!$subscription->id) {
            throw new \RuntimeException(
                'PrintDeliveryChannel: subscription has no ID — cannot create fulfilment record'
            );
        }

        // Resolve address: prefer the pre-computed context snapshot, fall back to
        // resolving fresh. The context path avoids a redundant address lookup when
        // FulfilmentDecisionService has already done the work.
        if ($context !== null) {
            $resolved = $this->buildResolvedFromContext($context, $subscription);
        } else {
            $resolved = $this->addressResolver->resolve($subscription);
        }

        $batch = $this->batchRepository->createForIssueDelivery($issueDelivery->id);

        $issuesDelivered = $this->issuesDeliveredRepository->findBySubscriptionAndDelivery(
            $subscription->id,
            $issueDelivery->id,
        );

        if (!$issuesDelivered) {
            throw new \RuntimeException(
                "PrintDeliveryChannel: IssuesDelivered record not found for subscription #{$subscription->id} "
                . "and issue delivery #{$issueDelivery->id}"
            );
        }

        // Idempotency guard — if a fulfilment already exists for this
        // subscription + issues_delivered + territory combination, a previous job
        // attempt succeeded before the job was marked complete on the queue.
        // Skip silently rather than creating a duplicate physical shipment.
        if ($this->fulfillmentRepository->existsForSubscriptionDeliveryAndTerritory(
            $subscription->id,
            $issuesDelivered->id,
            $context?->territoryId(),
        )) {
            $this->logger->info('PrintDeliveryChannel: fulfilment already exists — skipping duplicate', [
                'subscription_id' => $subscription->id,
                'issue_delivery_id' => $issueDelivery->id,
                'issues_delivered_id' => $issuesDelivered->id,
                'territory_id' => $context?->territoryId(),
            ]);
            return;
        }

        $this->fulfillmentRepository->createFullfilment(
            batchId: $batch->id,
            issuesDeliveredId: $issuesDelivered->id,
            subscriptionId: $subscription->id,
            fullName: $resolved['full_name'],
            addressSnapshot: $resolved['snapshot'],
            addressLine1: $resolved['address_line_1'],
            addressLine2: $resolved['address_line_2'] ?? null,
            city: $resolved['city'],
            postcode: $resolved['postcode'],
            country: $resolved['country'],
            territoryId: $context?->territoryId(),
        );

        $batchId = $batch->id;
        $issueDeliveryId = $issueDelivery->id;

        // Register the export job to dispatch after the outer transaction commits.
        // This guarantees the queue worker always sees committed fulfilment rows.
        $this->database->afterCommit(
            static function () use ($batchId, $issueDeliveryId): void {
                dispatch(\App\Jobs\Subscriptions\ExportPrintBatchJob::for(), $batchId, $issueDeliveryId);
            }
        );

        $this->logger->info('PrintDeliveryChannel: fulfilment created', [
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'batch_id' => $batch->id,
            'territory_id' => $context?->territoryId(),
        ]);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * Build a resolved-address array from a pre-computed decision context.
     * Uses the snapshot directly when it is complete to avoid a second address lookup.
     */
    private function buildResolvedFromContext(
        FulfilmentDecisionContext $context,
        Subscription              $subscription,
    ): array
    {
        $snapshot = $context->addressSnapshot;

        if (!empty($snapshot['address_line_1'])) {
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

        // Snapshot is incomplete — fall back to a fresh resolution.
        return $this->addressResolver->resolve($subscription);
    }
}