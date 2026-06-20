<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\IssueDeliveryDispatched;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\DeliverIssueDeliveryJob;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;

class IssueFulfilmentDispatchCoordinator
{
    public function __construct(
        private readonly IssuesDeliveredRepository $issuesDeliveredRepository,
        private readonly Logger $logger
    )
    {
    }

    public function dispatch(IssueDelivery $issueDelivery, array $plan): array
    {
        $digitalIds = array_values($plan['digital_ids']);
        $printIds = array_values($plan['print_ids']);

        foreach ($digitalIds as $index => $fulfilmentId) {
            try {
                dispatch(DeliverIssueDeliveryJob::for($fulfilmentId));
            } catch (\Throwable $exception) {
                $unhandedClaims = array_merge(
                    array_slice($digitalIds, $index),
                    $printIds
                );

                $this->issuesDeliveredRepository->releaseDispatchClaims($unhandedClaims);
                throw $exception;
            }
        }

        if (!empty($printIds)) {
            try {
                event(new IssueDeliveryDispatched(
                    issueDelivery: $issueDelivery,
                    eligibleCount: count($printIds),
                    createdCount: $plan['created'],
                    skippedCount: $this->skippedCount($plan),
                ));
            } catch (\Throwable $exception) {
                $this->issuesDeliveredRepository->releaseDispatchClaims($printIds);
                throw $exception;
            }
        }

        if (!$this->issuesDeliveredRepository->hasUndispatchedForIssue((int) $issueDelivery->id)) {
            $issueDelivery->markDispatched();
        }

        $summary = [
            'issue_delivery_id' => $issueDelivery->id,
            'created' => $plan['created'],
            'deferred' => $plan['deferred'],
            'not_due' => $plan['not_due'] ?? 0,
            'already_dispatched' => $plan['already_dispatched'] ?? 0,
            'non_dispatchable_status' => $plan['non_dispatchable_status'] ?? 0,
            'claim_conflicts' => $plan['claim_conflicts'] ?? 0,
            'digital_dispatches' => count($digitalIds),
            'print_dispatches' => count($printIds),
        ];

        $this->logger->info('Issue fulfilments dispatched', $summary);

        return $summary;
    }

    private function skippedCount(array $plan): int
    {
        return ($plan['deferred'] ?? 0)
            + ($plan['not_due'] ?? 0)
            + ($plan['already_dispatched'] ?? 0)
            + ($plan['non_dispatchable_status'] ?? 0)
            + ($plan['claim_conflicts'] ?? 0);
    }
}
