<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\Enums\Pages\PageStatus;
use App\Events\OpenCollab\ContributorAccountClosedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\PayoutAuditRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Repositories\OpenCollab\UserSiteRepository;

/**
 * Handles full contributor account closure (admin-initiated).
 *
 * Distinct from a closure request (ContributorAccountController) — that is
 * the contributor asking. This is the admin executing the closure.
 *
 * On closure:
 *   1. User account is deactivated ONLY if they have no remaining site access
 *      after this site is revoked — prevents cross-site lockout.
 *   2. Site access is revoked (oc_user_sites row removed).
 *   3. All pending/approved payouts are cancelled with an audit entry written
 *      for each — balance preserved in the ledger for audit.
 *   4. Contributor's draft/on_hold/waiting_approval pages are archived (not
 *      deleted — content may have been part of a revenue-generating arrangement).
 *   5. Published pages are left published — contributor agreement governs this.
 *   6. ContributorAccountClosedEvent fired with a freshly-loaded user model
 *      so listeners see the correct post-closure state.
 *
 * This is intentionally NOT reversible through the normal UI.
 * Re-activation requires a new invitation or direct DB fix.
 */
class ContributorTerminationService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserSiteRepository      $userSiteRepository,
        private readonly PayoutRepository        $payoutRepository,
        private readonly PayoutAuditRepository $payoutAuditRepository,
        private readonly PageRepository          $pageRepository,
        private readonly EventDispatcher         $eventDispatcher,
        private readonly Database                $database,
        private readonly Logger                  $logger,
    )
    {
    }

    /**
     * Execute account closure for a contributor.
     *
     * @throws \InvalidArgumentException if the user is not found or not a contributor
     * @throws \RuntimeException         on any write failure (transaction rolls back)
     */
    public function close(int $userId, int $siteId, int $adminId, string $reason): void
    {
        $user = $this->userRepository->find($userId);

        if (!$user) {
            throw new \InvalidArgumentException("User [{$userId}] not found.");
        }

        if (!$user->is_contributor) {
            throw new \InvalidArgumentException("User [{$userId}] is not a contributor.");
        }

        $this->database->transaction(function () use ($userId, $siteId, $adminId, $reason): void {
            // 1. Revoke site access first so the remaining-access check is accurate.
            $this->userSiteRepository->revoke($userId, $siteId);

            // 2. Only deactivate globally if the user has no remaining site access.
            $hasOtherAccess = $this->userSiteRepository->hasAnyOtherAccess($userId, $siteId);
            if (!$hasOtherAccess) {
                $this->userRepository->update($userId, ['is_active' => false]);
            }

            // 3. Cancel in-flight payouts with audit entries.
            $this->cancelInFlightPayouts($userId, $siteId, $adminId, $reason);

            // 4. Archive unpublished contributor pages.
            $this->archiveUnpublishedPages($userId, $siteId);
        });

        $this->logger->info('Contributor account closed.', [
            'user_id' => $userId,
            'site_id' => $siteId,
            'closed_by' => $adminId,
            'reason' => $reason,
        ]);

        // Reload so event listeners see the updated is_active state.
        $freshUser = $this->userRepository->find($userId);

        $this->eventDispatcher->dispatch(
            new ContributorAccountClosedEvent($freshUser, $adminId, $reason)
        );
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function cancelInFlightPayouts(int $userId, int $siteId, int $adminId, string $reason): void
    {
        $inFlight = $this->payoutRepository->inFlightForContributor($userId);

        foreach ($inFlight as $payout) {
            $rejectionReason = "Account closed: {$reason}";

            $this->payoutRepository->update($payout->id, [
                'status' => PayoutStatus::Rejected->value,
                'rejected_by' => $adminId,
                'rejected_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => $rejectionReason,
            ]);

            $this->payoutAuditRepository->log(
                payoutId: $payout->id,
                action: \App\Enums\OpenCollab\PayoutAuditAction::Declined,
                performedBy: $adminId,
                reason: $rejectionReason,
            );
        }
    }

    private function archiveUnpublishedPages(int $userId, int $siteId): void
    {
        $archivableStatuses = [
            PageStatus::DRAFT->value,
            PageStatus::ON_HOLD->value,
            PageStatus::WAITING_APPROVAL->value,
        ];

        $pages = $this->pageRepository
            ->query()
            ->where('contributor_id', $userId)
            ->where('site_id', $siteId)
            ->whereIn('status', $archivableStatuses)
            ->get();

        foreach ($pages as $page) {
            $this->pageRepository->update($page->id, [
                'status' => PageStatus::ARCHIVED->value,
            ]);
        }
    }
}