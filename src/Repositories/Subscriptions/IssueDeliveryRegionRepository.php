<?php

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\IssueDeliveryRegion;

/**
 * Persistence-only. No business logic.
 */
class IssueDeliveryRegionRepository
{
    /**
     * Returns all region IDs associated with the given issue delivery.
     * An empty collection means the delivery has no regional editions.
     *
     * @return Collection<IssueDeliveryRegion>
     */
    public function findByIssueDelivery(int $issueDeliveryId): Collection
    {
        return IssueDeliveryRegion::where('issue_delivery_id', $issueDeliveryId)->get();
    }

    /**
     * Returns true when the issue delivery has at least one regional edition registered.
     */
    public function hasRegionalEditions(int $issueDeliveryId): bool
    {
        return IssueDeliveryRegion::where('issue_delivery_id', $issueDeliveryId)->exists();
    }
}