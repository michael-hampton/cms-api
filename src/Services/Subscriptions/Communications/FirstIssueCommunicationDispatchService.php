<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Communications;

use App\Models\Subscription;
use App\Repositories\Subscriptions\SubscriptionCommunicationLetterCodeRepository;
use App\Repositories\Subscriptions\SubscriptionCommunicationRepository;

/**
 * Sends the "first issue" communication.
 *
 * Availability is a business decision, off by default sitewide and
 * overridable per product — reuses the same site/plan scope mechanism
 * built for payment communication letters
 * (SubscriptionCommunicationScopeRepository). The on/off check itself is
 * NOT performed here: SubscriptionCommunicationSender::send() applies it
 * universally to every communication it sends, so this service doesn't
 * need to know about scope at all — just like it doesn't need to know
 * about deceased/consent/do-not-mail suppression.
 *
 * Channel is resolved dynamically by SubscriptionCommunicationSender via
 * CommunicationChannelResolver (email default, letter if the member has no
 * email) — this service only looks up what to send, not whether or how.
 */
class FirstIssueCommunicationDispatchService
{
    private const COMMUNICATION_KEY = 'first_issue_default';

    public function __construct(
        private readonly SubscriptionCommunicationRepository $communications,
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
