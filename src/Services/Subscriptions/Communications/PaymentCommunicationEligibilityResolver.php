<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Communications;

use App\DTO\Subscriptions\PaymentCommunicationEligibilityResult;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Repositories\Subscriptions\SubscriptionCommunicationScopeRepository;

/**
 * Single decision point for whether a payment communication letter should
 * go out for a given subscription right now. Kept out of the sender/service
 * so the rule ("letter only when the member has no email, and the site/plan
 * hasn't disabled it") is independently testable and has one home.
 */
class PaymentCommunicationEligibilityResolver
{
    public function __construct(
        private readonly SubscriptionCommunicationScopeRepository $scopes,
    ) {
    }

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

        $enabled = $this->scopes->isEnabled(
            communicationId: $communication->id,
            siteId: $subscription->site_id,
            subscriptionPlanId: $subscription->plan_id,
        );

        if (!$enabled) {
            return PaymentCommunicationEligibilityResult::skipped('disabled_for_scope');
        }

        return PaymentCommunicationEligibilityResult::eligible();
    }
}
