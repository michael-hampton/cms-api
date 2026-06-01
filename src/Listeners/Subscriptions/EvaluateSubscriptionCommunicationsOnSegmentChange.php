<?php

namespace App\Listeners\Subscriptions;

use App\Enums\Subscriptions\CommunicationRelativeTo;
use App\Events\Subscriptions\SubscriptionSegmentAssigned;
use App\Framework\Support\Logger;
use App\Jobs\ProcessSubscriptionCommunicationsJob;
use App\Jobs\SyncMemberToStripeJob;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;

/**
 * Processes communications that are triggered by segment assignment.
 *
 * Only dispatches communications where relative_to = segment_assigned_at,
 * or communication type is configured as immediate (acknowledgement, customer_care).
 * Normal future schedules are NOT processed here — the nightly job handles those.
 */
class EvaluateSubscriptionCommunicationsOnSegmentChange
{
    public function __construct(
        private readonly SubscriptionCommunicationRepository $communicationRepository,
    ) {
    }

    public function handle(SubscriptionSegmentAssigned $event): void
    {
        $subscriptionSegment = $event->subscriptionSegment;

        // Check that immediate communications exist for this segment before dispatching.
        $communications = $this->communicationRepository->findActiveForSegment(
            $subscriptionSegment->segment_id
        );

        $hasImmediateCommunications = $communications->some(function ($communication) {
            return $communication->schedules
                ->where('is_active', true)
                ->some(fn($s) => $s->relative_to === CommunicationRelativeTo::SEGMENT_ASSIGNED_AT->value
                    || $s->relative_to instanceof CommunicationRelativeTo
                    && $s->relative_to === CommunicationRelativeTo::SEGMENT_ASSIGNED_AT
                );
        });

        if (!$hasImmediateCommunications) {
            return;
        }

        // Delegate actual sending to the job — listener stays thin.
        dispatch(ProcessSubscriptionCommunicationsJob::for(
            $subscriptionSegment->subscription_id,
            now_datetime()->format('Y-m-d'),
        ))->dispatchNow();

        Logger::info('EvaluateSubscriptionCommunicationsOnSegmentChange: dispatched job', [
            'subscription_id' => $subscriptionSegment->subscription_id,
            'segment_id'      => $subscriptionSegment->segment_id,
        ]);
    }
}