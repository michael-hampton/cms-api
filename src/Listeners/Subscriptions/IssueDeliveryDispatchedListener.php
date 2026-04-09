<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\IssueDeliveryDispatched;
use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\TriggerPrintRunWorkflowJob;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Listens for IssueDeliveryDispatched and triggers the print pipeline
 * when at least one print subscription is eligible for this delivery.
 *
 * Digital deliveries (email) are handled directly by GenerateIssueDeliveriesJob
 * via DeliverIssueDeliveryJob — this listener is the seam that separates
 * the print pipeline from the digital pipeline.
 *
 * Guard: if no print subscriptions exist for this IssueDelivery's plan,
 * no job is dispatched and no PrintRun is created. This avoids creating
 * empty PrintRuns for digital-only publications.
 */
class IssueDeliveryDispatchedListener
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Logger                 $logger,
    )
    {
    }

    public function handle(IssueDeliveryDispatched $event): void
    {
        $issueDelivery = $event->issueDelivery;

        $hasPrintSubscriptions = $this->subscriptionRepository->hasPrintSubscriptionsForPlan(
            $issueDelivery->subscription_plan_id,
        );

        if (!$hasPrintSubscriptions) {
            $this->logger->info('IssueDeliveryDispatchedListener: no print subscriptions, skipping print pipeline', [
                'issue_delivery_id' => $issueDelivery->id,
            ]);
            return;
        }

        dispatch(TriggerPrintRunWorkflowJob::for((int)$issueDelivery->id))->onQueue('print');

        $this->logger->info('IssueDeliveryDispatchedListener: TriggerPrintRunWorkflowJob dispatched', [
            'issue_delivery_id' => $issueDelivery->id,
        ]);
    }
}