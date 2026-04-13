<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\DisputeStatus;
use App\Enums\OpenCollab\LedgerEntryType;
use App\Framework\Database\Database;
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
    )
    {
    }

    /**
     * Contributor raises a dispute against one of their ledger entries.
     *
     * @throws \InvalidArgumentException if the ledger entry does not belong to the user
     * @throws \RuntimeException         if an open dispute already exists for this entry
     */
    public function raise(int $userId, int $ledgerId, string $reason): EarningsDispute
    {
        $ledgerEntry = $this->ledgerRepository->find($ledgerId);

        if (!$ledgerEntry || (int)$ledgerEntry->user_id !== $userId) {
            throw new \InvalidArgumentException(
                "Ledger entry [{$ledgerId}] not found or does not belong to user [{$userId}]."
            );
        }

        if ($this->disputeRepository->hasOpenDisputeForLedgerEntry($userId, $ledgerId)) {
            throw new \RuntimeException(
                "An open dispute already exists for ledger entry [{$ledgerId}]."
            );
        }

        $dispute = $this->disputeRepository->createForUser($userId, $ledgerId, $reason);

        $contributor = $this->userRepository->find($userId);
        if ($contributor) {
            $this->notificationDispatcher->dispatch(
                new DisputeRaisedNotification($dispute, $contributor)
            );
        }

        return $dispute;
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
     * @throws \InvalidArgumentException if the dispute is not open
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

        $resolved = $this->database->transaction(function () use (
            $dispute, $adminNotes, $adjustmentAmount, $adjustmentReason
        ): EarningsDispute {
            $resolved = $this->disputeRepository->markResolved($dispute->id, $adminNotes);

            if ($adjustmentAmount !== null) {
                $ledgerEntry = $this->ledgerRepository->find($dispute->earnings_ledger_id);

                $this->ledgerRepository->create([
                    'user_id' => $dispute->user_id,
                    'article_id' => $ledgerEntry->article_id ?? null,
                    'type' => LedgerEntryType::Adjustment->value,
                    'amount' => $adjustmentAmount,
                    'currency' => $ledgerEntry->currency ?? 'GBP',
                    'reference_id' => "dispute-{$dispute->id}",
                    'earned_at' => now()
                ]);
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

        return $resolved;
    }

    /**
     * Admin rejects a dispute.
     *
     * @throws \InvalidArgumentException if the dispute is not open
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

        $rejected = $this->disputeRepository->markRejected($dispute->id, $adminNotes);

        $contributor = $this->userRepository->find($dispute->user_id);
        if ($contributor) {
            $this->notificationDispatcher->dispatch(
                new DisputeResolvedNotification($rejected, $contributor, false, $adminNotes)
            );
        }

        return $rejected;
    }
}