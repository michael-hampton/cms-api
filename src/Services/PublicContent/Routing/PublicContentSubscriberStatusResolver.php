<?php

namespace App\Services\PublicContent\Routing;

use App\Models\Member;

/**
 * Maps member subscription facts to the open-text subscriber_status used by
 * {@see RouteOverrideBranchSelector}. Anonymous / non-subscriber → not-connected.
 */
final class PublicContentSubscriberStatusResolver
{
    public function resolve(?Member $member, int $siteId): ?string
    {
        if ($member === null) {
            return null;
        }

        if (
            $member->hasActiveSubscriptionOfType('paid', $siteId)
            || $member->hasActiveSubscriptionOfType('trial', $siteId)
        ) {
            return 'subscriber';
        }

        return 'not-connected';
    }
}
