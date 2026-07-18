<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Communications;

use App\Enums\Subscriptions\CommunicationChannelStrategy;
use App\Models\Member;
use App\Models\SubscriptionCommunication;

/**
 * Resolves which channel(s) SubscriptionCommunicationSender should attempt
 * for a given member.
 *
 * Kept out of the sender so the "email default, letter if no email" rule
 * is independently testable and has one home, per the existing
 * PaymentCommunicationEligibilityResolver precedent.
 */
class CommunicationChannelResolver
{
    /**
     * @return array<int, string>
     */
    public function resolve(SubscriptionCommunication $communication, ?Member $member): array
    {
        $strategy = $communication->channel_strategy instanceof CommunicationChannelStrategy
            ? $communication->channel_strategy
            : CommunicationChannelStrategy::tryFrom((string) $communication->channel_strategy)
                ?? CommunicationChannelStrategy::ALL;

        if ($strategy === CommunicationChannelStrategy::ALL) {
            return $communication->channels ?? [];
        }

        // EMAIL_WITH_LETTER_FALLBACK: exactly one channel, chosen by
        // whether the member has a usable email address.
        if ($member !== null && !empty($member->email)) {
            return ['email'];
        }

        return ['letter'];
    }
}
