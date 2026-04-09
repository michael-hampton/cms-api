<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Events\Subscriptions\IssueDeliveryDispatched;
use App\Events\Subscriptions\IssueDeliveryDispatchFailed;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Subscriptions\IssueDeliveryEligibilityService;
use DomainException;

/**
 * Creates IssuesDelivered records for all eligible subscriptions and fires
 * the appropriate pipeline event.
 *
 * Changes from the original:
 *   - Digital subscriptions: DeliverIssueDeliveryJob dispatched (unchanged).
 *   - Print subscriptions: NO job dispatched here. Instead, IssueDeliveryDispatched
 *     is fired after all records are created. IssueDeliveryDispatchedListener
 *     triggers the print pipeline independently.
 *
 * This separation means the delivery job has zero knowledge of printing.
 * Print pipeline entry is entirely event-driven.
 */
class GenerateIssueDeliveriesJob extends BaseJob
{
    private IssuesDeliveredRepository $issuesDeliveredRepository;
    private IssueDeliveryRepository $issueDeliveryRepository;
    private IssueDeliveryEligibilityService $eligibilityService;
    private Database $database;
    private Logger $logger;

    public function __construct(
        private readonly int $issueDeliveryId,
    )
    {
    }

    public function handle(): array
    {
        $issueDelivery = $this->issueDeliveryRepository->find($this->issueDeliveryId);

        if (!$issueDelivery->isActive()) {
            $this->logger->info('IssueDelivery skipped — not active', [
                'issue_delivery_id' => $issueDelivery->id,
                'status' => $issueDelivery->status,
            ]);
            return [];
        }

        try {
            $eligibleSubscriptions = $this->eligibilityService->getEligibleSubscriptions($issueDelivery);

        } catch (DomainException $e) {
            $this->logger->error('IssueDelivery eligibility resolution failed', [
                'issue_delivery_id' => $issueDelivery->id,
                'error' => $e->getMessage(),
            ]);

            $issueDelivery->markDispatchFailed($e->getMessage());
            event(new IssueDeliveryDispatchFailed($issueDelivery, $e->getMessage()));

            return [];
        }

        // Digital jobs dispatched after commit; print handled via event.
        $digitalToDispatch = [];

        $summary = $this->database->transaction(
            function () use ($issueDelivery, $eligibleSubscriptions, &$digitalToDispatch): array {
                $created = 0;
                $skipped = 0;

                foreach ($eligibleSubscriptions as $subscription) {
                    if ($this->issuesDeliveredRepository->existsForSubscriptionAndSchedule(
                        $subscription->id,
                        $issueDelivery->id
                    )) {
                        $skipped++;
                        continue;
                    }

                    $issueDelivered = $this->issuesDeliveredRepository->createForSubscription(
                        $subscription->id,
                        $issueDelivery->id
                    );

                    // Only queue digital delivery jobs here.
                    // Print subscriptions are handled by the print pipeline
                    // triggered via IssueDeliveryDispatched event below.
                    $isPrint = $subscription->delivery_type === SubscriptionType::PRINTED->value;

                    if (!$isPrint) {
                        $digitalToDispatch[] = $issueDelivered->id;
                    }

                    $created++;
                }

                $issueDelivery->markDispatched();

                return [
                    'issue_delivery_id' => $issueDelivery->id,
                    'eligible_subscriptions' => $eligibleSubscriptions->count(),
                    'created' => $created,
                    'skipped' => $skipped,
                    'dispatched' => count($digitalToDispatch),
                ];
            }
        );

        // Dispatch digital delivery jobs outside the transaction.
        foreach ($digitalToDispatch as $issueDeliveredId) {
            dispatch(DeliverIssueDeliveryJob::for((int)$issueDeliveredId));
        }

        // Fire the event that triggers the print pipeline.
        // IssueDeliveryDispatchedListener will check whether any print
        // subscriptions exist before dispatching TriggerPrintRunWorkflowJob.
        event(new IssueDeliveryDispatched(
            issueDelivery: $issueDelivery,
            eligibleCount: $summary['eligible_subscriptions'],
            createdCount: $summary['created'],
            skippedCount: $summary['skipped'],
        ));

        $this->logger->info('Issue deliveries generated', $summary);

        return $summary;
    }
}