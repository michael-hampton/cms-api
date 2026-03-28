<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions\Print;

use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Models\PrintFulfillment;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\Printing\FulfilmentDecisionService;
use App\Services\Subscriptions\Printing\PrintAddressResolver;

/**
 * Creates a PrintFulfillment record for a single subscription + issue delivery.
 *
 * Extracted from PrintDeliveryChannel (which previously lived inside
 * DeliveryChannelInterface — an inappropriate interface for physical fulfilment).
 *
 * This action is called by CreatePrintFulfillmentsJob, one call per subscription.
 * It must be idempotent: calling it twice for the same subscription + delivery
 * must produce one record, not two.
 *
 * Responsibilities:
 *   1. Find the IssuesDelivered record (throws if missing).
 *   2. Idempotency guard: return existing fulfillment if already created.
 *   3. Resolve delivery address (PrintAddressResolver).
 *   4. Resolve territory (FulfilmentDecisionService — optional; null = global batch).
 *   5. Create PrintFulfillment record.
 *
 * This action does NOT:
 *   - Create PrintBatch records (BatchBuilderService owns that)
 *   - Dispatch any jobs
 *   - Know about LabelRuns
 */
class CreatePrintFulfillmentAction
{
    public function __construct(
        private readonly IssuesDeliveredRepository  $issuesDeliveredRepository,
        private readonly PrintFulfillmentRepository $fulfillmentRepository,
        private readonly PrintBatchRepository       $batchRepository,
        private readonly PrintAddressResolver       $addressResolver,
        private readonly FulfilmentDecisionService  $decisionService,
        private readonly Logger                     $logger,
    )
    {
    }

    /**
     * Execute the fulfillment creation for one subscription.
     *
     * @throws \RuntimeException If the IssuesDelivered record is missing,
     *                           or if address resolution fails.
     */
    public function execute(Subscription $subscription, IssueDelivery $issueDelivery): PrintFulfillment
    {
        $issuesDelivered = $this->issuesDeliveredRepository->findBySubscriptionAndDelivery(
            $subscription->id,
            $issueDelivery->id,
        );

        if (!$issuesDelivered) {
            throw new \RuntimeException(
                "CreatePrintFulfillmentAction: IssuesDelivered not found for "
                . "subscription #{$subscription->id} and issue delivery #{$issueDelivery->id}"
            );
        }

        // Idempotency guard — if a fulfillment already exists for this
        // subscription + issues_delivered + territory, a previous attempt
        // already succeeded. Return the existing record.
        $context = $this->decisionService->decide($subscription, $issueDelivery);

        $existing = $this->fulfillmentRepository->existsForSubscriptionDeliveryAndTerritory(
            $subscription->id,
            $issuesDelivered->id,
            $context->territoryId(),
        );

        if ($existing) {
            $this->logger->info('CreatePrintFulfillmentAction: fulfillment already exists — skipping', [
                'subscription_id' => $subscription->id,
                'issue_delivery_id' => $issueDelivery->id,
                'issues_delivered_id' => $issuesDelivered->id,
                'territory_id' => $context->territoryId(),
            ]);

            // Return the existing record so the caller (job) can reference it.
            return $this->fulfillmentRepository->findBySubscriptionDeliveryAndTerritory(
                $subscription->id,
                $issuesDelivered->id,
                $context->territoryId(),
            );
        }

        $snapshot = $context->addressSnapshot;
        $resolved = $this->buildResolvedAddress($snapshot, $subscription);

        // Batch assignment is deferred — BatchBuilderService groups fulfillments
        // into batches by territory after all fulfillments are created.
        // batch_id is intentionally left null here.
        $fulfillment = $this->fulfillmentRepository->createFullfilment(
            batchId: null,
            issuesDeliveredId: $issuesDelivered->id,
            subscriptionId: $subscription->id,
            fullName: $resolved['full_name'],
            addressSnapshot: $resolved['snapshot'],
            addressLine1: $resolved['address_line_1'],
            addressLine2: $resolved['address_line_2'] ?? null,
            city: $resolved['city'],
            postcode: $resolved['postcode'],
            country: $resolved['country'],
            territoryId: $context->territoryId(),
        );

        $this->logger->info('CreatePrintFulfillmentAction: fulfillment created', [
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
            'fulfillment_id' => $fulfillment->id,
            'territory_id' => $context->territoryId(),
        ]);

        return $fulfillment;
    }

    // =========================================================================
    // Private
    // =========================================================================

    private function buildResolvedAddress(array $snapshot, Subscription $subscription): array
    {
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

        // Context snapshot is incomplete — fall back to fresh resolution.
        return $this->addressResolver->resolve($subscription);
    }
}