<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Communications;

use App\DTO\Subscriptions\PaymentCommunicationEligibilityResult;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;

/**
 * Single decision point for whether a payment communication letter should
 * go out for a given subscription right now. Kept out of the sender/service
 * so the rule ("letter only when the member has no email") is
 * independently testable and has one home.
 *
 * Site/product scope (the enable/disable business decision) and consent/
 * suppression checks (deceased, marketing consent, do-not-mail) are NOT
 * checked here — SubscriptionCommunicationSender::send() applies both
 * universally to every communication, so every dispatch path gets them
 * for free rather than each one remembering to call them.
 */
class PaymentCommunicationEligibilityResolver
{
    public function resolve(
        SubscriptionCommunication $communication,
        Subscription $subscription,
        ?Member $member,
    ): PaymentCommunicationEligibilityResult {
        if ($member === null) {
            return PaymentCommunicationEligibilityResult::skipped('no_member');
        }

        if (!empty($member->email)) {
            return PaymentCommunicationEligibilityResult::skipped('member_has_email');
        }

        return PaymentCommunicationEligibilityResult::eligible();
    }
}
