<?php

namespace App\Jobs\Subscriptions;

use App\Events\Subscriptions\IssueDeliveryDispatchFailed;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Subscriptions\IssueDeliveryEligibilityService;
use DomainException;

class GenerateIssueDeliveriesJob extends BaseJob
{
    public function __construct(
        private readonly IssuesDeliveredRepository       $issuesDeliveredRepository,
        private readonly IssueDeliveryRepository $issueDeliveryRepository,
        private readonly IssueDeliveryEligibilityService $eligibilityService,
        private readonly Database                $database,
        private readonly Logger                  $logger,
    )
    {
    }

    public function handle(int $issueDeliveryId): array
    {
        $issueDelivery = $this->issueDeliveryRepository->find($issueDeliveryId);

        if (!$issueDelivery->isActive()) {
            $this->logger->info('IssueDelivery skipped — not active', [
                'issue_delivery_id' => $issueDelivery->id,
                'status' => $issueDelivery->status,
            ]);

            return [];
        }

        // Resolve eligible subscriptions BEFORE opening a transaction.
        // A DomainException here signals a non-retryable configuration problem
        // (e.g. no newsletter linked to the plan). We fail-fast, record the
        // error, and emit an event so operators are alerted.
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

        // Collect job IDs to dispatch after the transaction commits.
        // Dispatching inside a transaction risks the queue worker picking up
        // the job before the DB write is visible.
        $toDispatch = [];

        $summary = $this->database->transaction(
            function () use ($issueDelivery, $eligibleSubscriptions, &$toDispatch): array {
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

                    $toDispatch[] = $issueDelivered->id;
                    $created++;
                }

                $issueDelivery->markDispatched();

                return [
                    'issue_delivery_id' => $issueDelivery->id,
                    'eligible_subscriptions' => $eligibleSubscriptions->count(),
                    'created' => $created,
                    'skipped' => $skipped,
                    'dispatched' => $created,
                ];
            }
        );

        // Dispatch outside the transaction so workers see committed rows.
        foreach ($toDispatch as $issueDeliveredId) {
            dispatch(DeliverIssueDeliveryJob::for(), $issueDeliveredId);
        }

        $this->logger->info('Issue deliveries generated', $summary);

        return $summary;
    }
}