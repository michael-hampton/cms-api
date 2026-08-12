<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\DisputeStatus;
use App\Events\OpenCollab\DisputeRaisedEvent;
use App\Events\OpenCollab\DisputeResolvedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\EarningsDispute;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\EarningsDisputeRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Services\OpenCollab\Notifications\DisputeAdjustmentAppliedNotification;
use App\Services\OpenCollab\Notifications\DisputeRaisedNotification;
use App\Services\OpenCollab\Notifications\DisputeResolvedNotification;

/**
 * Governs the earnings dispute lifecycle.
 *
 * Contributor raises a dispute → admin resolves or rejects.
 * Resolution MAY write an adjustment entry to the earnings ledger.
 * All multi-write paths use a transaction.
 *
 * Services MUST NOT cross-call other services for side-effects.
 * Ledger adjustments are written directly via EarningsLedgerRepository.
 */
class EarningsDisputeService
{
    public function __construct(
        private readonly EarningsDisputeRepository $disputeRepository,
        private readonly EarningsLedgerRepository  $ledgerRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly Database                  $database,
        private readonly NotificationDispatcher  $notificationDispatcher,
        private readonly EventDispatcher $eventDispatcher,
    )
    {
    }

    /**
     * Contributor raises a dispute against one of their ledger entries.
     *
     * A dispute can only ever be raised once per ledger entry — regardless
     * of whether a prior dispute was resolved or rejected.
     *
     * @throws \InvalidArgumentException if the ledger entry does not belong to the user
     * @throws \RuntimeException         if any dispute (open, resolved, or rejected) already exists for this entry
     */
    public function raise(int $userId, int $ledgerId, string $reason, ?int $siteId = null): EarningsDispute
    {
        $ledgerEntry = $this->ledgerRepository->find($ledgerId);

        if (!$ledgerEntry || (int)$ledgerEntry->user_id !== $userId) {
            throw new \InvalidArgumentException(
                "Ledger entry [{$ledgerId}] not found or does not belong to user [{$userId}]."
            );
        }

        $ledgerSiteId = $this->siteIdForLedger($ledgerId);
        if ($siteId !== null && $ledgerSiteId !== null && $ledgerSiteId !== $siteId) {
            throw new \InvalidArgumentException(
                "Ledger entry [{$ledgerId}] does not belong to the current site."
            );
        }

        // Block re-disputes regardless of prior dispute status.
        if ($this->disputeRepository->hasAnyDisputeForLedgerEntry($userId, $ledgerId)) {
            throw new \RuntimeException(
                "A dispute has already been raised for ledger entry [{$ledgerId}]."
            );
        }

        $dispute = $this->disputeRepository->createForUser($userId, $ledgerId, $reason);

        $contributor = $this->userRepository->find($userId);
        if ($contributor) {
            $this->notificationDispatcher->dispatch(
                new DisputeRaisedNotification($dispute, $contributor)
            );
        }

        $this->eventDispatcher->dispatch(
            new DisputeRaisedEvent($userId, $dispute->id, $ledgerSiteId ?? $siteId)
        );

        return $dispute;
    }

    private function siteIdForLedger(int $ledgerId): ?int
    {
        $row = Database::table('oc_earnings_ledger as l')
            ->join('pages as p', 'p.id', '=', 'l.article_id')
            ->where('l.id', $ledgerId)
            ->select('p.site_id')
            ->first();

        return $row && isset($row->site_id) ? (int) $row->site_id : null;
    }

    /**
     * Admin resolves a dispute, optionally writing a ledger adjustment.
     *
     * @param int $disputeId
     * @param int $adminId
     * @param string $adminNotes
     * @param int|null $adjustmentAmount Pence. Positive = credit, negative = debit. null = no adjustment.
     * @param string|null $adjustmentReason Required when $adjustmentAmount is provided.
     *
     * @throws \InvalidArgumentException if the dispute is not found or not open
     * @throws \InvalidArgumentException if adjustmentAmount is provided without adjustmentReason
     */
    public function resolve(
        int     $disputeId,
        int     $adminId,
        string  $adminNotes,
        ?int    $adjustmentAmount = null,
        ?string $adjustmentReason = null,
    ): EarningsDispute
    {
        $dispute = $this->disputeRepository->find($disputeId);

        if (!$dispute) {
            throw new \InvalidArgumentException("Dispute [{$disputeId}] not found.");
        }

        if ($dispute->status !== DisputeStatus::Open->value) {
            throw new \InvalidArgumentException(
                "Dispute [{$disputeId}] is not open (status: {$dispute->status})."
            );
        }

        if ($adjustmentAmount !== null && empty($adjustmentReason)) {
            throw new \InvalidArgumentException(
                'Adjustment reason is required when an adjustment amount is provided.'
            );
        }

        $resolved = $this->database->transaction(function () use (
            $dispute, $adminId, $adminNotes, $adjustmentAmount, $adjustmentReason
        ): EarningsDispute {
            $resolved = $this->disputeRepository->markResolved($dispute->id, $adminNotes, $adminId);

            if ($adjustmentAmount !== null) {
                $ledgerEntry = $this->ledgerRepository->find($dispute->earnings_ledger_id);

                if (!$ledgerEntry) {
                    throw new \RuntimeException(
                        "Original ledger entry missing for dispute [{$dispute->id}]."
                    );
                }

                $this->ledgerRepository->recordAdjustment(
                    userId: (int) $dispute->user_id,
                    articleId: $ledgerEntry->article_id ? (int) $ledgerEntry->article_id : null,
                    amount: $adjustmentAmount,
                    currency: (string) ($ledgerEntry->currency ?? 'GBP'),
                    referenceId: sprintf('dispute:%d', $dispute->id),
                    reason: (string) $adjustmentReason,
                );
            }

            return $resolved;
        });

        $contributor = $this->userRepository->find($dispute->user_id);
        if ($contributor) {
            $this->notificationDispatcher->dispatch(
                new DisputeResolvedNotification($resolved, $contributor, true, $adminNotes)
            );

            if ($adjustmentAmount !== null) {
                $ledgerEntry = $this->ledgerRepository->find($dispute->earnings_ledger_id);
                $this->notificationDispatcher->dispatch(
                    new DisputeAdjustmentAppliedNotification(
                        $resolved,
                        $contributor,
                        $adjustmentAmount,
                        $ledgerEntry->currency ?? 'GBP',
                    )
                );
            }
        }

        $this->eventDispatcher->dispatch(
            new DisputeResolvedEvent($dispute->user_id, $dispute->id, 'resolved')
        );

        return $resolved;
    }

    /**
     * Admin rejects a dispute.
     *
     * @throws \InvalidArgumentException if the dispute is not found or not open
     */
    public function reject(int $disputeId, int $adminId, string $adminNotes): EarningsDispute
    {
        $dispute = $this->disputeRepository->find($disputeId);

        if (!$dispute) {
            throw new \InvalidArgumentException("Dispute [{$disputeId}] not found.");
        }

        if ($dispute->status !== DisputeStatus::Open->value) {
            throw new \InvalidArgumentException(
                "Dispute [{$disputeId}] is not open (status: {$dispute->status})."
            );
        }

        $rejected = $this->disputeRepository->markRejected($dispute->id, $adminNotes, $adminId);

        $contributor = $this->userRepository->find($dispute->user_id);
        if ($contributor) {
            $this->notificationDispatcher->dispatch(
                new DisputeResolvedNotification($rejected, $contributor, false, $adminNotes)
            );
        }

        $this->eventDispatcher->dispatch(
            new DisputeResolvedEvent($dispute->user_id, $dispute->id, 'rejected')
        );

        return $rejected;
    }
}
