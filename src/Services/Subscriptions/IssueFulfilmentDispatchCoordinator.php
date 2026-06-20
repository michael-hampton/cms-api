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
        $now = new \DateTime();

        foreach ($plan['digital_ids'] as $fulfilmentId) {
            dispatch(DeliverIssueDeliveryJob::for($fulfilmentId));
            $this->issuesDeliveredRepository->markDispatched([$fulfilmentId], $now);
        }

        if (!empty($plan['print_ids'])) {
            event(new IssueDeliveryDispatched(
                issueDelivery: $issueDelivery,
                eligibleCount: count($plan['print_ids']),
                createdCount: $plan['created'],
                skippedCount: $plan['deferred'],
            ));

            $this->issuesDeliveredRepository->markDispatched($plan['print_ids'], $now);
        }

        if (!$this->issuesDeliveredRepository->hasUndispatchedForIssue((int) $issueDelivery->id)) {
            $issueDelivery->markDispatched();
        }

        $summary = [
            'issue_delivery_id' => $issueDelivery->id,
            'created' => $plan['created'],
            'deferred' => $plan['deferred'],
            'digital_dispatches' => count($plan['digital_ids']),
            'print_dispatches' => count($plan['print_ids']),
        ];

        $this->logger->info('Issue fulfilments dispatched', $summary);

        return $summary;
    }
}
