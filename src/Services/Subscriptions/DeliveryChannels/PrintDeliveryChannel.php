<?php

namespace App\Services\Subscriptions\DeliveryChannels;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\ExportPrintBatchJob;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Repositories\Subscriptions\PrintBatchRepository;
use App\Repositories\Subscriptions\PrintFulfillmentRepository;
use App\Services\Subscriptions\DeliveryChannelInterface;
use App\Services\Subscriptions\Printing\PrintAddressResolver;

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
     * Create a print fulfillment record and schedule the export job.
     *
     * Persistence happens here, inside the caller's transaction boundary
     * (DeliverIssueDeliveryJob wraps the call in Database::transaction()).
     *
     * The export job is registered via afterCommit() so the queue worker is
     * guaranteed to see the committed batch and fulfillment rows. Without this
     * guard, a fast worker could pick up the job before the outer transaction
     * is visible — a real race condition on high-throughput queues.
     */
    public function send(Subscription $subscription, IssueDelivery $issueDelivery): void
    {
        $this->guardSubscription($subscription);

        $resolved = $this->addressResolver->resolve($subscription);

        $batch = $this->batchRepository->createForIssueDelivery($issueDelivery->id);

        $fulfillment = $this->fulfillmentRepository->create(
            batchId: $batch->id,
            issuesDeliveredId: $this->resolveIssuesDeliveredId($subscription, $issueDelivery),
            subscriptionId: $subscription->id,
            fullName: $resolved['full_name'],
            addressSnapshot: $resolved['snapshot'],
            addressLine1: $resolved['address_line_1'],
            addressLine2: $resolved['address_line_2'],
            city: $resolved['city'],
            postcode: $resolved['postcode'],
            country: $resolved['country'],
        );

        $this->logger->info('print_fulfillment_created', [
            'batch_id' => $batch->id,
            'fulfillment_id' => $fulfillment->id,
            'subscription_id' => $subscription->id,
            'issue_delivery_id' => $issueDelivery->id,
        ]);

        $batchId = $batch->id;
        $issueDeliveryId = $issueDelivery->id;

        $this->database->afterCommit(
            function () use ($batchId, $issueDeliveryId): void {
                dispatch(ExportPrintBatchJob::for(), $batchId, $issueDeliveryId);
            }
        );
    }

    private function guardSubscription(Subscription $subscription): void
    {
        if (!$subscription->id) {
            throw new \RuntimeException('Cannot create print fulfillment: subscription has no ID');
        }
    }

    /**
     * Resolve the IssuesDelivered row ID for this subscription + issue pair.
     * The row must already exist at this point (created by GenerateIssueDeliveriesJob).
     */
    private function resolveIssuesDeliveredId(Subscription $subscription, IssueDelivery $issueDelivery): int
    {
        $issuesDelivered = $this->issuesDeliveredRepository->findBySubscriptionAndDelivery(
            $subscription->id,
            $issueDelivery->id
        );

        if (!$issuesDelivered) {
            throw new \RuntimeException(
                "Cannot create print fulfillment: IssuesDelivered record not found "
                . "for subscription #{$subscription->id} and issue delivery #{$issueDelivery->id}"
            );
        }

        return $issuesDelivered->id;
    }
}