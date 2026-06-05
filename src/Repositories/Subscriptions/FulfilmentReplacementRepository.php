<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Framework\Support\Collection;
use App\Models\FulfilmentReplacement;
use App\Models\IssueDelivery;
use App\Models\Model;
use App\Repositories\Repository;

/**
 * Persistence-only. No business logic.
 *
 * Open replacement statuses that block a new request: pending, queued, dispatched.
 * Non-blocking statuses (allow a new request): failed, cancelled, rejected.
 */
class FulfilmentReplacementRepository extends Repository
{
    /**
     * Statuses that constitute an "open" replacement and block a new request.
     */
    private const OPEN_STATUSES = ['pending', 'queued', 'dispatched'];

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Create a new replacement record.
     */
    public function createReplacement(
        int    $subscriptionId,
        int    $issueId,
        string $reason,
        int    $createdBy,
        string $status = 'pending',
    ): Model {
        return $this->create([
            'subscription_id'   => $subscriptionId,
            'issue_delivery_id' => $issueId,
            'reason'            => $reason,
            'created_by'        => $createdBy,
            'status'            => $status,
        ]);
    }

    /**
     * Update the status of a replacement record.
     */
    public function updateStatus(int $replacementId, string $status): ?Model
    {
        return $this->update($replacementId, ['status' => $status]);
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * All replacements for a subscription, newest first.
     *
     * @return Collection<FulfilmentReplacement>
     */
    public function findBySubscription(int $subscriptionId): Collection
    {
        return FulfilmentReplacement::where('subscription_id', $subscriptionId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * True when an IssueDelivery record with the given ID belongs to the
     * given subscription (direct FK on issue_deliveries.subscription_id).
     *
     * Previously contained a pointless ternary ($subscriptionId === 0 ? $issueId : $issueId)
     * that evaluated identically on both branches. Removed — no behaviour change.
     */
    public function issueExistsForSubscription(int $issueId, int $subscriptionId): bool
    {
        return IssueDelivery::where('id', $issueId)
            ->where('subscription_id', $subscriptionId)
            ->exists();
    }

    /**
     * True when the issue delivery's status is 'dispatched'.
     *
     * Used by eligibility checks before allowing a replacement request.
     */
    public function issueDeliveryWasDispatched(int $issueId): bool
    {
        return IssueDelivery::where('id', $issueId)
            ->where('status', 'dispatched')
            ->exists();
    }

    /**
     * True when the subscription + issue delivery combination already has
     * at least one open replacement record (pending, queued, or dispatched).
     */
    public function hasOpenReplacement(int $subscriptionId, int $issueId): bool
    {
        return FulfilmentReplacement::where('subscription_id', $subscriptionId)
            ->where('issue_delivery_id', $issueId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->exists();
    }

    /**
     * Return all open replacement records for the given issue delivery IDs
     * within a subscription.
     *
     * Intended for bulk eligibility evaluation to avoid N+1 queries.
     * Only records with status in OPEN_STATUSES are returned.
     *
     * @param  int[]  $issueIds
     * @return Collection<FulfilmentReplacement>
     */
    public function findOpenReplacementsForIssues(
        int   $subscriptionId,
        array $issueIds,
    ): Collection {
        if (empty($issueIds)) {
            return new Collection([]);
        }

        return FulfilmentReplacement::where('subscription_id', $subscriptionId)
            ->whereIn('issue_delivery_id', $issueIds)
            ->whereIn('status', self::OPEN_STATUSES)
            ->get();
    }

    // ── Required by Repository base ───────────────────────────────────────────

    protected function getModelClass(): string
    {
        return FulfilmentReplacement::class;
    }
}