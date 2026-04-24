<?php

namespace App\Services\MemberInsights\Newsletters\Suppression;

use App\Models\Member;
use App\Repositories\Newsletters\SubscriberRepository;

final class NewsletterSuppressionService
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
    )
    {
    }

    /**
     * Build a suppression set for the given member on the given site.
     *
     * Only active subscriptions (confirmed = true AND unsubscribed_at IS NULL)
     * are included. Paused or cancelled states do not suppress.
     */
    public function buildSuppressionSet(Member $member, int $siteId): SuppressionSet
    {
        $newsletterIds = $this->subscriberRepository->getActiveNewsletterIdsForMember(
            email: $member->email,
            siteId: $siteId,
        );

        return SuppressionSet::from($newsletterIds);
    }
}