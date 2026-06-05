<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Events\Subscriptions\IssueReplacementRequested;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\FulfilmentReplacementRepository;

/**
 * Handles agent-initiated print issue replacement requests.
 *
 * Responsibilities:
 *   1. Validate that the reason is non-empty.
 *   2. Delegate all eligibility checks to FulfilmentReplacementEligibilityService.
 *   3. Create a fulfilment_replacements record.
 *   4. Emit IssueReplacementRequested — a listener handles the dispatch job.
 *
 * This service does NOT:
 *   - Duplicate eligibility rules (they live in FulfilmentReplacementEligibilityService).
 *   - Dispatch jobs directly (decoupled via event).
 *   - Mutate the subscription record.
 */
class FulfilmentReplacementService
{
    public function __construct(
        private readonly FulfilmentReplacementRepository      $replacementRepository,
        private readonly FulfilmentReplacementEligibilityService $eligibilityService,
    ) {}

    /**
     * Request a replacement for a dispatched issue within a print subscription.
     *
     * @param int    $subscriptionId The subscription that entitles the issue.
     * @param int    $issueId        The IssueDelivery.id being replaced.
     * @param string $reason         Agent-supplied reason (required).
     * @param int    $agentId        Acting CRM agent user ID.
     * @param int    $siteId         Current site (used for subscription ownership check).
     *
     * @return object  The created FulfilmentReplacement record.
     *
     * @throws \InvalidArgumentException  For empty reason or eligibility failures.
     */
    public function requestReplacement(
        int    $subscriptionId,
        int    $issueId,
        string $reason,
        int    $agentId,
        int    $siteId,
    ): object {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('Reason is required for issue replacement.');
        }

        $eligibility = $this->eligibilityService->canRequest(
            subscriptionId: $subscriptionId,
            issueId:        $issueId,
            siteId:         $siteId,
        );

        if (!$eligibility->canRequestReplacement) {
            throw new \InvalidArgumentException($eligibility->blockedReason);
        }

        $replacement = $this->replacementRepository->createReplacement(
            subscriptionId: $subscriptionId,
            issueId:        $issueId,
            reason:         $reason,
            createdBy:      $agentId,
        );

        $timestamp = now_datetime()->format('Y-m-d H:i:s');

        event(new IssueReplacementRequested(
            subscriptionId: $subscriptionId,
            issueId:        $issueId,
            reason:         $reason,
            agentId:        $agentId,
            timestamp:      $timestamp,
        ));

        Logger::info('Issue replacement requested', [
            'replacement_id'  => $replacement->id,
            'subscription_id' => $subscriptionId,
            'issue_id'        => $issueId,
            'agent_id'        => $agentId,
        ]);

        return $replacement;
    }
}