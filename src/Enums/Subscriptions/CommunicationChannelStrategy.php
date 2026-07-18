<?php

namespace App\Enums\Subscriptions;

/**
 * Governs how SubscriptionCommunicationSender interprets a communication's
 * `channels` column.
 *
 * ALL (default, existing behaviour): attempt every channel listed —
 * unchanged for every communication seeded before this feature.
 *
 * EMAIL_WITH_LETTER_FALLBACK: pick exactly one channel per member —
 * 'email' if they have an address, otherwise 'letter'. Used for
 * communications that should always reach the member exactly once,
 * regardless of which contact method is available.
 */
enum CommunicationChannelStrategy: string
{
    case ALL = 'all';
    case EMAIL_WITH_LETTER_FALLBACK = 'email_with_letter_fallback';
}
