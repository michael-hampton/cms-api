<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\ReplacementResolution;
use App\Models\IssueDelivery;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

/**
 * Everything a ReplacementPolicyInterface needs to validate/evaluate a
 * request, so policies never query repositories directly (per ticket:
 * "should contain all information required to evaluate a request without
 * requiring policies to query repositories directly").
 *
 * NOTE on $currentResolutionCount: the ticket lists this as separate from
 * usage statistics without defining it further. Implemented here as the
 * count of prior resolutions of the *same decision type* for this exact
 * issue (PER_ISSUE scope), independent of whatever scope the policy
 * itself uses for its limits — i.e. a duplicate/repeat-request signal
 * rather than an entitlement count. No shipped policy currently reads it;
 * it's provided for forward compatibility. Confirm this interpretation
 * matches intent.
 */
final class PolicyContext
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly SubscriptionPlan $plan,
        public readonly IssueDelivery $issueDelivery,
        public readonly ReplacementResolution $requestedResolution,
        public readonly ReplacementUsageStatistics $usageStatistics,
        public readonly int $agentId,
        public readonly int $siteId,
        public readonly int $currentResolutionCount,
    ) {
    }
}
