<?php

namespace App\Jobs\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Subscriptions\IssueDeliveryEligibilityService;

class GenerateIssueDeliveriesJob
{
    public function __construct(
        private readonly IssuesDeliveredRepository       $issuesDeliveredRepository,
        private readonly IssueDeliveryEligibilityService $eligibilityService,
        private readonly Database                        $database
    )
    {
    }

    public function handle(IssueDelivery $issueDelivery): array
    {
        return $this->database->transaction(function () use ($issueDelivery) {
            $eligibleSubscriptions = $this->eligibilityService->getEligibleSubscriptions($issueDelivery);

            $created = 0;
            $skipped = 0;
            $dispatched = 0;

            foreach ($eligibleSubscriptions as $subscription) {
                // Skip if already exists (idempotency)
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

                $created++;

                // Dispatch delivery job
                dispatch(DeliverIssueDeliveryJob::for(), $issueDelivered->id);
                $dispatched++;
            }

            $summary = [
                'issue_delivery_id' => $issueDelivery->id,
                'eligible_subscriptions' => $eligibleSubscriptions->count(),
                'created' => $created,
                'skipped' => $skipped,
                'dispatched' => $dispatched,
            ];

            Logger::info('Issue deliveries generated', $summary);

            return $summary;
        });
    }
}