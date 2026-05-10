<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\IssueReplacementRequested;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\FulfilmentReplacementRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

/**
 * Handles agent-initiated print issue replacement requests.
 *
 * Responsibilities:
 *   1. Validate that the subscription is active and is a print subscription.
 *   2. Validate that the requested issue belongs to the subscription.
 *   3. Create a fulfilment_replacements record.
 *   4. Emit IssueReplacementRequested — a listener handles the dispatch job.
 *
 * This service does NOT:
 *   - Dispatch jobs directly (decoupled via event).
 *   - Mutate the subscription record.
 *   - Apply any cap on replacement count (no cap per ticket spec).
 */
class FulfilmentReplacementService
{
    public function __construct(
        private readonly FulfilmentReplacementRepository $replacementRepository,
        private readonly SubscriptionRepository          $subscriptionRepository,
    )
    {
    }

    /**
     * Request a replacement for a dispatched issue within a print subscription.
     *
     * @param int $subscriptionId The subscription that entitles the issue.
     * @param int $issueId The IssueDelivery.id being replaced.
     * @param string $reason Agent-supplied reason (required).
     * @param int $agentId Acting CRM agent user ID.
     * @param int $siteId Current site (used for subscription ownership check).
     *
     * @return object  The created FulfilmentReplacement record.
     *
     * @throws \InvalidArgumentException  For eligibility failures.
     */
    public function requestReplacement(
        int    $subscriptionId,
        int    $issueId,
        string $reason,
        int    $agentId,
        int    $siteId,
    ): object
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Reason is required for issue replacement.');
        }

        // ── Validate subscription ──────────────────────────────────────────
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            throw new \InvalidArgumentException("Subscription #{$subscriptionId} not found.");
        }

        if ($subscription->site_id !== $siteId) {
            throw new \InvalidArgumentException("Subscription does not belong to this site.");
        }

        if ($subscription->status !== 'active') {
            throw new \InvalidArgumentException(
                "Only active subscriptions can have issues replaced. Current status: {$subscription->status}."
            );
        }

        if ($subscription->delivery_type && $subscription->delivery_type !== 'print') {
            throw new \InvalidArgumentException(
                "Issue replacement is only available for print subscriptions."
            );
        }

        // ── Validate issue belongs to this subscription ────────────────────
        // issue_deliveries has a subscription_id FK; we verify the match here.
        $issueExistsForSubscription = $this->replacementRepository
            ->issueExistsForSubscription($issueId, $subscriptionId);

        if (!$issueExistsForSubscription) {
            throw new \InvalidArgumentException(
                "Issue #{$issueId} does not belong to subscription #{$subscriptionId}."
            );
        }

        // ── Create replacement record ──────────────────────────────────────
        $replacement = $this->replacementRepository->createReplacement(
            subscriptionId: $subscriptionId,
            issueId: $issueId,
            reason: $reason,
            createdBy: $agentId,
            status: 'pending',
        );

        // ── Emit event (listener dispatches the job) ───────────────────────
        $timestamp = now_datetime()->format('Y-m-d H:i:s');

        event(new IssueReplacementRequested(
            subscriptionId: $subscriptionId,
            issueId: $issueId,
            reason: $reason,
            agentId: $agentId,
            timestamp: $timestamp,
        ));

        Logger::info('Issue replacement requested', [
            'replacement_id' => $replacement->id,
            'subscription_id' => $subscriptionId,
            'issue_id' => $issueId,
            'agent_id' => $agentId,
        ]);

        return $replacement;
    }
}