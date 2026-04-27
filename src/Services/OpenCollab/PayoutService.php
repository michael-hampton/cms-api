<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutAuditAction;
use App\Enums\OpenCollab\PayoutStatus;
use App\Events\OpenCollab\PayoutFailedEvent;
use App\Events\OpenCollab\PayoutProcessedEvent;
use App\Events\OpenCollab\PayoutRequestedEvent;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Models\Model;
use App\Models\Payout;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PayoutAuditRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\OpenCollab\Notifications\PayoutApprovedNotification;
use App\Services\OpenCollab\Notifications\PayoutCreatedNotification;
use App\Services\OpenCollab\Notifications\PayoutDeclinedNotification;
use App\Services\OpenCollab\Notifications\PayoutPaidNotification;
use App\Services\OpenCollab\Policies\ContributorPolicy;

/**
 * Manual/batch payout management.
 *
 * requestPayout() enforces the ContributorPolicy — a contributor must have
 * completed all onboarding steps (including payment details) before withdrawing.
 */
class PayoutService
{
    private const MINIMUM_PAYOUT_PENCE = 5000; // £50.00
    private const VALID_METHODS = ['bank_transfer', 'stripe'];

    public function __construct(
        private readonly PayoutRepository         $payoutRepository,
        private readonly PayoutAuditRepository   $payoutAuditRepository,
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly ArticlePaymentRepository $paymentRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly EventDispatcher          $eventDispatcher,
        private readonly Database                 $database,
        private readonly NotificationDispatcher  $notificationDispatcher,
        private readonly ContributorPolicy       $policy,
        private readonly SiteRepository          $siteRepository
    )
    {
    }

    // ── Contributor ───────────────────────────────────────────────────────────

    /**
     * Contributor requests a payout for their full available balance.
     *
     * @throws OnboardingIncompleteException if the contributor has not completed onboarding
     * @throws \InvalidArgumentException     if the payout method is invalid or balance is too low
     * @throws \RuntimeException             if there is already a payout in flight
     */
    public function requestPayout(int $userId, int $siteId, string $method): Payout
    {
        if (!in_array($method, self::VALID_METHODS, true)) {
            throw new \InvalidArgumentException(
                "Invalid payout method [{$method}]. Allowed: " . implode(', ', self::VALID_METHODS) . "."
            );
        }

        $site = $this->siteRepository->find($siteId);

        if (!$site) {
            throw new \InvalidArgumentException("Site [{$siteId}] not found.");
        }

        if (!$this->policy->canWithdraw($userId, $site)) {
            $pending = []; // pendingSteps will be evaluated by the policy internally
            throw new OnboardingIncompleteException($pending);
        }

        $payout = $this->database->transaction(function () use ($userId, $siteId, $method): Model {
            $available = $this->availableBalance($userId);

            if ($available < self::MINIMUM_PAYOUT_PENCE) {
                throw new \InvalidArgumentException(
                    "Minimum payout is £" . number_format(self::MINIMUM_PAYOUT_PENCE / 100, 2) .
                    ". Current available balance: £" . number_format($available / 100, 2) . "."
                );
            }

            $inFlight = $this->payoutRepository->totalInFlightForContributor($userId);
            if ($inFlight > 0) {
                throw new \RuntimeException(
                    "A payout of £" . number_format($inFlight / 100, 2) . " is already in progress."
                );
            }

            return $this->payoutRepository->create([
                'user_id' => $userId,
                'site_id' => $siteId,
                'amount' => $available,
                'currency' => 'GBP',
                'status' => PayoutStatus::Pending->value,
                'method' => $method,
            ]);
        });

        $this->eventDispatcher->dispatch(new PayoutRequestedEvent($payout, $userId));

        $contributor = $this->userRepository->find($userId);
        if ($contributor) {
            $this->notificationDispatcher->dispatch(
                new PayoutCreatedNotification($payout, $contributor)
            );
        }

        return $payout;
    }

    /**
     * Available balance in pence for a contributor.
     * = ledger balance − already paid − in-flight
     */
    public function availableBalance(int $userId): int
    {
        $ledgerBalance = $this->ledgerRepository->balanceForContributor($userId);
        $paid = $this->payoutRepository->totalPaidForContributor($userId);
        $inFlight = $this->payoutRepository->totalInFlightForContributor($userId);

        return max(0, $ledgerBalance - $paid - $inFlight);
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    /**
     * @throws \InvalidArgumentException if payout is not found or not pending
     */
    public function approve(int $payoutId, int $adminId): \App\Models\Payout
    {
        $payout = $this->payoutRepository->find($payoutId);

        if (!$payout) {
            throw new \InvalidArgumentException("Payout [{$payoutId}] not found.");
        }

        if (!$payout->isPending()) {
            throw new \InvalidArgumentException(
                "Payout [{$payoutId}] cannot be approved from status [{$payout->status}]."
            );
        }

        $payout = $this->database->transaction(function () use ($payout, $adminId): \App\Models\Payout {
            $this->payoutRepository->update($payout->id, [
                'status' => PayoutStatus::Approved->value,
                'approved_by' => $adminId,
                'approved_at' => date('Y-m-d H:i:s'),
            ]);

            $this->payoutAuditRepository->log(
                payoutId: $payout->id,
                action: PayoutAuditAction::Approved,
                performedBy: $adminId,
            );

            return $this->payoutRepository->find($payout->id);
        });

        $contributor = $this->userRepository->find($payout->user_id);
        if ($contributor) {
            $this->notificationDispatcher->dispatch(
                new PayoutApprovedNotification($payout, $contributor)
            );

            $this->eventDispatcher->dispatch(new PayoutProcessedEvent($payout, $adminId, $payout->user_id));
        }

        return $payout;
    }

    /**
     * @throws \InvalidArgumentException if payout is not found or not approved
     */
    public function markPaid(
        int     $payoutId,
        int     $adminId,
        ?string $reference = null,
        ?string $notes = null,
    ): \App\Models\Payout
    {
        $payout = $this->payoutRepository->find($payoutId);

        if (!$payout) {
            throw new \InvalidArgumentException("Payout [{$payoutId}] not found.");
        }

        if (!$payout->isApproved()) {
            throw new \InvalidArgumentException(
                "Payout [{$payoutId}] cannot be marked as paid from status [{$payout->status}]."
            );
        }

        $payout = $this->database->transaction(function () use ($payout, $adminId, $reference, $notes): \App\Models\Payout {
            $this->payoutRepository->update($payout->id, [
                'status' => PayoutStatus::Paid->value,
                'paid_by' => $adminId,
                'processed_at' => date('Y-m-d H:i:s'),
                'reference' => $reference,
                'notes' => $notes,
            ]);

            $this->payoutAuditRepository->log(
                payoutId: $payout->id,
                action: PayoutAuditAction::Paid,
                performedBy: $adminId,
                reason: $notes,
            );

            return $this->payoutRepository->find($payout->id);
        });

        $this->eventDispatcher->dispatch(new PayoutFailedEvent($payout, $adminId, $payout->user_id));

        $contributor = $this->userRepository->find($payout->user_id);
        if ($contributor) {
            $this->notificationDispatcher->dispatch(
                new PayoutPaidNotification($payout, $contributor, $reference)
            );
        }

        return $payout;
    }

    /**
     * @throws \InvalidArgumentException if payout is not found or not pending
     */
    public function reject(int $payoutId, int $adminId, string $reason): \App\Models\Payout
    {
        $payout = $this->payoutRepository->find($payoutId);

        if (!$payout) {
            throw new \InvalidArgumentException("Payout [{$payoutId}] not found.");
        }

        if (!$payout->isPending()) {
            throw new \InvalidArgumentException(
                "Payout [{$payoutId}] cannot be rejected from status [{$payout->status}]."
            );
        }

        $payout = $this->database->transaction(function () use ($payout, $adminId, $reason): \App\Models\Payout {
            $this->payoutRepository->update($payout->id, [
                'status' => PayoutStatus::Rejected->value,
                'rejected_by' => $adminId,
                'rejected_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => $reason,
            ]);

            $this->payoutAuditRepository->log(
                payoutId: $payout->id,
                action: PayoutAuditAction::Declined,
                performedBy: $adminId,
                reason: $reason,
            );

            return $this->payoutRepository->find($payout->id);
        });

        $contributor = $this->userRepository->find($payout->user_id);
        if ($contributor) {
            $this->notificationDispatcher->dispatch(
                new PayoutDeclinedNotification($payout, $contributor, $reason)
            );

            $this->eventDispatcher->dispatch(new PayoutProcessedEvent($payout, $adminId, $payout->user_id));
        }

        return $payout;
    }
}