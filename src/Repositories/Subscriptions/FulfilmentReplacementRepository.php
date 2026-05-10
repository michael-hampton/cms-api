<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\FulfilmentReplacement;
use App\Models\Model;
use App\Repositories\Repository;

/**
 * Persistence-only. No business logic.
 */
class FulfilmentReplacementRepository extends Repository
{
    /**
     * Create a new replacement record.
     */
    public function createReplacement(
        int    $subscriptionId,
        int    $issueId,
        string $reason,
        int    $createdBy,
        string $status = 'pending',
    ): Model
    {
        return $this->create([
            'subscription_id' => $subscriptionId,
            'issue_delivery_id' => $issueId,
            'reason' => $reason,
            'created_by' => $createdBy,
            'status' => $status,
        ]);
    }

    /**
     * All replacements for a subscription, newest first.
     *
     * @return Collection<FulfilmentReplacement>
     */
    public function findBySubscription(int $subscriptionId): Collection
    {
        return FulfilmentReplacement::where('subscription_id', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * True when an IssueDelivery record with the given ID belongs to the
     * given subscription (direct FK on issue_deliveries.subscription_id).
     *
     * Used as the primary eligibility guard in FulfilmentReplacementService
     * before creating a replacement record.
     */
    public function issueExistsForSubscription(int $issueId, int $subscriptionId): bool
    {
        return \App\Models\IssueDelivery::where('id', $subscriptionId === 0 ? $issueId : $issueId)
            ->where('subscription_id', $subscriptionId)
            ->exists();
    }

    /**
     * Update the status of a replacement record.
     */
    public function updateStatus(int $replacementId, string $status): ?Model
    {
        return $this->update($replacementId, ['status' => $status]);
    }

    protected function getModelClass(): string
    {
        return FulfilmentReplacement::class;
    }
}