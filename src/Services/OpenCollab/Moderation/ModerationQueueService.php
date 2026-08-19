<?php

namespace App\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\ModerationActionType;
use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Events\OpenCollab\ModerationQueueClaimFailedEvent;
use App\Exceptions\OpenCollab\ModerationQueueClaimConflictException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Models\ModerationQueueEntry;
use App\Models\Page;
use App\Repositories\OpenCollab\ModerationQueueRepository;
use App\Repositories\OpenCollab\RiskMarkerRepository;

/**
 * Orchestrates the moderation queue lifecycle.
 *
 * Persistence -> ModerationQueueRepository
 * Risk/priority math -> RiskScoreCalculator / ModerationPriorityCalculator
 * Audit trail -> ModerationAuditService (Ticket 4)
 */
class ModerationQueueService
{
    public function __construct(
        private readonly ModerationQueueRepository $queueRepository,
        private readonly RiskMarkerRepository $riskMarkerRepository,
        private readonly RiskScoreCalculator $riskScoreCalculator,
        private readonly ModerationPriorityCalculator $priorityCalculator,
        private readonly ModerationAuditService $auditService,
        private readonly EventDispatcher $eventDispatcher,
        private readonly Database $database,
    ) {
    }

    /**
     * Called from ArticleApprovalService::submitForReview()/resubmit().
     * Refreshes an existing open entry, or creates a new review cycle.
     *
     * Performs 2-3 writes (create-or-update the queue entry, recalculate
     * its priority, write an audit entry) wrapped in their own transaction
     * so a mid-sequence failure can't leave a queue entry without a
     * matching audit record. The caller may also already be inside its own
     * transaction (e.g. alongside the page status write) — this method's
     * transaction nests safely via savepoints in that case.
     */
    public function enqueueForSubmission(Page $page, int $actorId, bool $isResubmission = false): ModerationQueueEntry
    {
        return $this->database->transaction(function () use ($page, $actorId, $isResubmission): ModerationQueueEntry {
            $existing = $this->queueRepository->openEntryForPage($page->site_id, $page->id);

            $now = date('Y-m-d H:i:s');

            if ($existing) {
                $entry = $this->queueRepository->update($existing->id, [
                    'status' => ModerationQueueStatus::Queued->value,
                    'submitted_at' => $now,
                    'assigned_to_user_id' => null,
                    'claimed_at' => null,
                ]);
            } else {
                $entry = $this->queueRepository->create([
                    'site_id' => $page->site_id,
                    'page_id' => $page->id,
                    'status' => ModerationQueueStatus::Queued->value,
                    'submitted_at' => $now,
                    'risk_score' => 0,
                    'priority_score' => 0,
                ]);
            }

            $this->recalculatePriority($entry->id);

            $this->auditService->record(
                siteId: $page->site_id,
                pageId: $page->id,
                actorUserId: $actorId,
                action: $isResubmission ? ModerationActionType::Resubmitted : ModerationActionType::Submitted,
                queueEntryId: $entry->id,
            );

            return $entry->refresh();
        });
    }

    public function markApproved(int $pageId, int $siteId): void
    {
        $this->closeWithStatus($siteId, $pageId, ModerationQueueStatus::Approved);
    }

    public function markRejected(int $pageId, int $siteId): void
    {
        $this->closeWithStatus($siteId, $pageId, ModerationQueueStatus::Rejected);
    }

    public function markChangesRequested(int $pageId, int $siteId): void
    {
        $entry = $this->queueRepository->openEntryForPage($siteId, $pageId);

        if ($entry === null) {
            return;
        }

        $this->queueRepository->update($entry->id, [
            'status' => ModerationQueueStatus::ChangesRequested->value,
        ]);
    }

    private function closeWithStatus(int $siteId, int $pageId, ModerationQueueStatus $status): void
    {
        $entry = $this->queueRepository->openEntryForPage($siteId, $pageId);

        if ($entry === null) {
            return;
        }

        $this->queueRepository->update($entry->id, ['status' => $status->value]);
    }

    // src/Services/OpenCollab/Moderation/ModerationQueueService.php (continued / corrected)

    /**
     * Atomic claim. Throws on conflict so the controller can return 409.
     *
     * @throws ModerationQueueClaimConflictException
     */
    public function claim(int $queueEntryId, int $userId, int $siteId): ModerationQueueEntry
    {
        $entry = $this->queueRepository->claimIfUnassigned($queueEntryId, $userId);

        if ($entry === null) {
            throw new ModerationQueueClaimConflictException(
                "Queue entry [{$queueEntryId}] is already claimed."
            );
        }

        $this->auditService->record(
            siteId: $siteId,
            pageId: $entry->page_id,
            actorUserId: $userId,
            action: ModerationActionType::Claimed,
            queueEntryId: $entry->id,
        );

        return $entry;
    }

    public function release(int $queueEntryId, int $userId, int $siteId): ModerationQueueEntry
    {
        $entry = $this->queueRepository->find($queueEntryId);

        if ($entry === null) {
            throw new \InvalidArgumentException("Queue entry [{$queueEntryId}] not found.");
        }

        if ((int)$entry->assigned_to_user_id !== $userId) {
            throw new \InvalidArgumentException("Queue entry [{$queueEntryId}] is not claimed by this user.");
        }

        $entry = $this->queueRepository->update($queueEntryId, [
            'assigned_to_user_id' => null,
            'claimed_at' => null,
            'status' => ModerationQueueStatus::Queued->value,
        ]);

        $this->auditService->record(
            siteId: $siteId,
            pageId: $entry->page_id,
            actorUserId: $userId,
            action: ModerationActionType::Released,
            queueEntryId: $entry->id,
        );

        return $entry;
    }

    /**
     * Recompute risk_score + priority_score for a queue entry.
     * Triggered after submission, and on risk marker status changes
     * (via RiskMarkerStatusChangedEvent listener — Ticket 9/11).
     */
    public function recalculatePriority(int $queueEntryId, int $manualPriorityBoost = 0): ModerationQueueEntry
    {
        $entry = $this->queueRepository->find($queueEntryId);

        if ($entry === null) {
            throw new \InvalidArgumentException("Queue entry [{$queueEntryId}] not found.");
        }

        $status = $entry->status instanceof ModerationQueueStatus
            ? $entry->status
            : ModerationQueueStatus::tryFrom((string) $entry->status);

        if ($status?->isClosed()) {
            return $entry;
        }

        $outstanding = $this->riskMarkerRepository->outstandingForPage($entry->site_id, $entry->page_id);
        $riskScore = $this->riskScoreCalculator->calculate($outstanding);
        $priorityScore = $this->priorityCalculator->forEntry($entry, $riskScore, $manualPriorityBoost);

        return $this->queueRepository->update($queueEntryId, [
            'risk_score' => $riskScore,
            'priority_score' => $priorityScore,
        ]);
    }

    public function overridePriority(int $queueEntryId, int $boost, int $actorId, int $siteId): ModerationQueueEntry
    {
        return $this->database->transaction(function () use ($queueEntryId, $boost, $actorId, $siteId) {
            $entry = $this->recalculatePriority($queueEntryId, $boost);

            $this->auditService->record(
                siteId: $siteId,
                pageId: $entry->page_id,
                actorUserId: $actorId,
                action: ModerationActionType::PriorityOverridden,
                queueEntryId: $entry->id,
                metadata: ['boost' => $boost],
            );

            return $entry;
        });
    }
}