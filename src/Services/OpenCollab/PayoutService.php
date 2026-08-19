<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutAuditAction;
use App\Enums\OpenCollab\PayoutStatus;
use App\Events\OpenCollab\PayoutProcessedEvent;
use App\Events\OpenCollab\PayoutRequestedEvent;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Notifications\NotificationDispatcher;
use App\Jobs\OpenCollab\ProcessStripePayoutJob;
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
use App\Services\OpenCollab\SetOffService;
use App\Services\OpenCollab\PayoutLedgerService;
use App\Repositories\OpenCollab\PayoutLiabilityRecoveryRepository;

/**
 * Manual/batch payout management.
 *
 * requestPayout() enforces the ContributorPolicy — a contributor must have
 * completed all onboarding steps (including payment details) before withdrawing.
 */
class PayoutService
{
    private const MINIMUM_PAYOUT_PENCE = 5000; // £50.00
    private const VALID_METHODS = ['bank_transfer', 'paypal', 'other', 'stripe'];

    public function __construct(
        private readonly PayoutRepository          $payoutRepository,
        private readonly PayoutAuditRepository    $payoutAuditRepository,
        private readonly EarningsLedgerRepository $ledgerRepository,
        private readonly ArticlePaymentRepository $paymentRepository,
        private readonly UserRepositoryInterface  $userRepository,
        private readonly EventDispatcher          $eventDispatcher,
        private readonly Database                 $database,
        private readonly NotificationDispatcher   $notificationDispatcher,
        private readonly ContributorPolicy        $policy,
        private readonly SiteRepository           $siteRepository,
        private readonly CreatorBalanceService    $creatorBalanceService,
        private readonly SetOffService            $setOffService,
        private readonly PayoutLedgerService      $payoutLedgerService,
        private readonly PayoutLiabilityRecoveryRepository $payoutLiabilityRecoveryRepository,
    ) {
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
            $pending = [];
            throw new OnboardingIncompleteException($pending);
        }

        $result = $this->database->transaction(function () use ($userId, $siteId, $method): array {
            $settled = $this->creatorBalanceService->settledBalance($userId, $siteId);
            $inFlight = $this->payoutRepository->totalInFlightForContributor($userId, $siteId);

            if ($inFlight > 0) {
                throw new \RuntimeException(
                    "A payout of £" . number_format($inFlight / 100, 2) . " is already in progress."
                );
            }

            $grossAvailable = max(0, $settled - $inFlight);

            if ($grossAvailable < self::MINIMUM_PAYOUT_PENCE) {
                throw new \InvalidArgumentException(
                    "Minimum payout is £" . number_format(self::MINIMUM_PAYOUT_PENCE / 100, 2) .
                    ". Current available balance: £" . number_format($grossAvailable / 100, 2) . "."
                );
            }

            $stateKey = $this->makeManualPayoutStateKey($userId, $siteId);
            $existing = $this->payoutRepository->findByIdempotencyKey($stateKey);

            if ($existing && $existing->status !== PayoutStatus::Rejected->value) {
                return ['payout' => $existing, 'created' => false];
            }

            $setOff = $this->setOffService->apply($userId, $siteId, $grossAvailable);
            $available = $setOff->netAmount;

            if ($available < self::MINIMUM_PAYOUT_PENCE) {
                throw new \InvalidArgumentException(
                    "Minimum payout is £" . number_format(self::MINIMUM_PAYOUT_PENCE / 100, 2) .
                    ". Current available balance: £" . number_format($available / 100, 2) . "."
                );
            }

            $payout = $this->payoutRepository->createWithIdempotency([
                'user_id' => $userId,
                'site_id' => $siteId,
                'amount' => $available,
                'currency' => 'GBP',
                'status' => PayoutStatus::Pending->value,
                'method' => $method,
                'idempotency_key' => $stateKey,
                'processing_attempts' => 0,
            ]);

            foreach ($setOff->deductions as $deduction) {
                $this->payoutLiabilityRecoveryRepository->record(
                    payoutId: (int) $payout->id,
                    creatorLiabilityId: (int) $deduction['liability_id'],
                    amount: (int) $deduction['amount'],
                    sourceType: $deduction['source_type'] ?? null,
                    sourceId: $deduction['source_id'] ?? null,
                    reason: $deduction['reason'] ?? null,
                );
            }

            $this->payoutLedgerService->attachSettledEntriesToPayout(
                payoutId: (int) $payout->id,
                userId: $userId,
                amountToAttach: $grossAvailable,
                siteId: $siteId,
            );

            return ['payout' => $payout, 'created' => true];
        });

        /** @var Payout $payout */
        $payout = $result['payout'];

        if ($result['created']) {
            $this->eventDispatcher->dispatch(new PayoutRequestedEvent($payout, $userId));

            $contributor = $this->userRepository->find($userId);

            if ($contributor) {
                $this->notificationDispatcher->dispatch(
                    new PayoutCreatedNotification($payout, $contributor)
                );
            }
        }

        return $payout;
    }

    private function makeManualPayoutStateKey(int $userId, int $siteId): string
    {
        $entryIds = $this->ledgerRepository
            ->settledAvailableForPayout($userId, $siteId)
            ->map(fn($entry) => (int)$entry->id)
            ->toArray();

        return sprintf(
            'payout:user:%d:site:%d:manual:%s',
            $userId,
            $siteId,
            sha1(implode(',', $entryIds)),
        );
    }

    /**
     * Available balance in pence for a contributor.
     * = ledger balance − already paid − in-flight
     */
    public function availableBalance(int $userId, int $siteId): int
    {
        return $this->creatorBalanceService->availableToWithdraw($userId, $siteId);
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    /**
     * @throws \InvalidArgumentException if payout is not found or not pending
     */
    public function approve(int $payoutId, int $adminId): Payout
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

        $payout = $this->database->transaction(function () use ($payout, $adminId): Payout {
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

        // Stripe payouts are executed asynchronously after approval.
        if ($payout->method === 'stripe') {
            dispatch(ProcessStripePayoutJob::for($payout->id))->onQueue('payouts')->dispatchNow();
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
    ): Payout
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

        if ($payout->method === 'stripe') {
            throw new \InvalidArgumentException(
                "Payout [{$payoutId}] is Stripe-backed and must be finalised by Stripe webhooks."
            );
        }

        $payout = $this->database->transaction(function () use ($payout, $adminId, $reference, $notes): Payout {
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

            $this->payoutLedgerService->markPayoutLedgerEntriesWithdrawn((int) $payout->id);

            return $this->payoutRepository->find($payout->id);
        });

        $this->eventDispatcher->dispatch(
            new PayoutProcessedEvent($payout, $adminId, $payout->user_id)
        );

        $contributor = $this->userRepository->find($payout->user_id);
        if ($contributor) {
            $this->notificationDispatcher->dispatch(
                new PayoutPaidNotification($payout, $contributor, $reference)
            );
        }

        return $payout;
    }

    public function retryStripeFailedPayout(int $payoutId, int $adminId): Payout
    {
        $payout = $this->payoutRepository->find($payoutId);

        if (!$payout) {
            throw new \InvalidArgumentException("Payout [{$payoutId}] not found.");
        }

        if ($payout->status === PayoutStatus::Paid->value) {
            throw new \InvalidArgumentException("Payout [{$payoutId}] is already paid and cannot be retried.");
        }

        if ($payout->method !== 'stripe') {
            throw new \InvalidArgumentException("Only Stripe payouts can be retried.");
        }

        if ($payout->status !== PayoutStatus::Failed->value) {
            throw new \InvalidArgumentException("Only failed payouts can be retried.");
        }

        $payout = $this->database->transaction(function () use ($payout, $adminId): Payout {
            $this->payoutRepository->update($payout->id, [
                'status' => PayoutStatus::Approved->value,
                'provider_status' => 'retry_queued',
                'processed_at' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->payoutAuditRepository->log(
                payoutId: $payout->id,
                action: PayoutAuditAction::Approved,
                performedBy: $adminId,
                reason: 'Stripe payout retry requested.',
            );

            return $this->payoutRepository->find($payout->id);
        });

        dispatch(ProcessStripePayoutJob::for($payout->id))->onQueue('payouts')->dispatchNow();

        return $payout;
    }

    /**
     * @throws \InvalidArgumentException if payout is not found or not pending
     */
    public function reject(int $payoutId, int $adminId, string $reason): Payout
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

        $payout = $this->database->transaction(function () use ($payout, $adminId, $reason): Payout {
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

    /**
     * Deterministic idempotency key for scheduler/automated payouts, scoped
     * to contributor + site + currency + the eligibility window's cutoff
     * date. The same (user, site, currency, cutoff) combination always
     * produces the same key, so a repeated or concurrent scheduler run
     * cannot create a second payout for the same window: PayoutSchedulerService
     * checks findByIdempotencyKey() before inserting, and the
     * oc_payouts_idempotency_key_unique DB constraint is the final backstop
     * against a genuine race between two overlapping scheduler runs.
     */
    public function makeScheduledPayoutIdempotencyKey(
        int $userId,
        int $siteId,
        string $currency,
        \DateTimeInterface $cutoff,
    ): string {
        return sprintf(
            'payout:scheduled:user:%d:site:%d:currency:%s:cutoff:%s',
            $userId,
            $siteId,
            strtoupper($currency),
            $cutoff->format('Y-m-d'),
        );
    }
}
