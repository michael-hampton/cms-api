<?php

namespace App\Services\Subscriptions\Printing;

use App\DTO\Subscriptions\FulfilmentDecisionContext;
use App\Models\IssueDelivery;
use App\Models\Subscription;

/**
 * Produces a FulfilmentDecisionContext for a given subscription + issue delivery.
 *
 * This service owns a single concern: deciding what context (territory, address
 * snapshot, channel metadata) should be applied to a fulfilment record before
 * it is written to the database. It does not write anything itself.
 *
 * Collaborators with an independent reason to change are injected:
 *   - RegionResolver       : territory selection priority rules
 *   - PrintAddressResolver : address selection rules
 */
class FulfilmentDecisionService
{
    public function __construct(
        private readonly RegionResolver       $regionResolver,
        private readonly PrintAddressResolver $addressResolver,
    )
    {
    }

    /**
     * Build the fulfilment decision context for a subscription.
     *
     * The postcode used for territory derivation comes from the resolved delivery
     * address — no separate member lookup is needed.
     *
     * @throws \RuntimeException When no valid delivery address exists for the subscription.
     */
    public function decide(Subscription $subscription, IssueDelivery $issueDelivery): FulfilmentDecisionContext
    {
        // Resolve the delivery address first — throws if no address exists,
        // which is a hard failure for physical fulfilment.
        $resolvedAddress = $this->addressResolver->resolve($subscription);

        $postcode = $resolvedAddress['postcode'] ?? null;

        $territory = $this->regionResolver->resolve($subscription, $postcode);

        return new FulfilmentDecisionContext(
            territory: $territory,
            addressSnapshot: $resolvedAddress['snapshot'],
            fullName: $resolvedAddress['full_name'],
            channelMetadata: [
                'issue_delivery_id' => $issueDelivery->id,
                'subscription_id' => $subscription->id,
            ],
        );
    }
}