<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Communications;

use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionCommunicationLetterCodeRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationScopeRepository;

/**
 * Sends the "first issue" communication.
 *
 * Availability is a business decision, off by default sitewide and
 * overridable per product — reuses the same site/plan scope mechanism
 * built for payment communication letters
 * (SubscriptionCommunicationScopeRepository), rather than a bool column on
 * the plan/product model. That keeps the on/off decision editable by admins
 * without a deploy, and consistent with how payment letters are governed.
 *
 * Channel is resolved dynamically by SubscriptionCommunicationSender via
 * CommunicationChannelResolver (email default, letter if the member has no
 * email) — this service only decides *whether* to send, not *how*.
 */
class FirstIssueCommunicationDispatchService
{
    private const COMMUNICATION_KEY = 'first_issue_default';

    public function __construct(
        private readonly SubscriptionCommunicationRepository $communications,
        private readonly SubscriptionCommunicationScopeRepository $scopes,
        private readonly SubscriptionCommunicationLetterCodeRepository $letterCodes,
        private readonly SubscriptionCommunicationSender $sender,
    ) {
    }

    public function dispatch(Subscription $subscription): void
    {
        $communication = $this->communications->findActiveByKey(self::COMMUNICATION_KEY);

        if (!$communication) {
            throw new \RuntimeException(
                sprintf('Active first-issue communication [%s] was not found.', self::COMMUNICATION_KEY)
            );
        }

        $enabled = $this->scopes->isEnabled(
            communicationId: $communication->id,
            siteId: $subscription->site_id,
            subscriptionPlanId: $subscription->plan_id,
        );

        if (!$enabled) {
            return;
        }

        // Only relevant if the member ends up on the letter channel — sent
        // regardless so SubscriptionCommunicationSender's letter path has a
        // code available without needing to know this is "first issue".
        $letterCode = $this->letterCodes->findForCommunication($communication->id);

        $dedupeKey = sprintf('first-issue:subscription:%d', $subscription->id);

        $this->sender->send(
            subscription: $subscription,
            communication: $communication,
            metadata: $letterCode ? ['letter_code' => $letterCode->letter_code] : [],
            dedupeKey: $dedupeKey,
        );
    }
}
