<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\PayoutStatus;
use App\DTO\OpenCollab\ContributorAccessRevocationRequest;
use App\Events\OpenCollab\ContributorAccountClosedEvent;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Repositories\Cms\Pages\PageRepository;
use App\Repositories\OpenCollab\PayoutAuditRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use App\Services\User\UserLifecycleServiceInterface;

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
        private readonly UserLifecycleServiceInterface $userLifecycle,
        private readonly OpenCollabAuthorisationInterface $authorisation,
        private readonly PayoutRepository        $payoutRepository,
        private readonly PayoutAuditRepository $payoutAuditRepository,
        private readonly PageRepository          $pageRepository,
        private readonly EventDispatcher         $eventDispatcher,
        private readonly Database                $database,
        private readonly Logger                  $logger,
        private ?PermissionCacheInvalidator $permissionCacheInvalidator = null,
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
        $user = $this->userLifecycle->findById($userId);

        if (!$user) {
            throw new \InvalidArgumentException("User [{$userId}] not found.");
        }

        if (!$user->is_contributor) {
            throw new \InvalidArgumentException("User [{$userId}] is not a contributor.");
        }

        $this->database->transaction(function () use ($userId, $siteId, $adminId, $reason): void {
            // 1. Revoke site access first so the remaining-access check is accurate.
            $this->authorisation->revokeContributorAccess(new ContributorAccessRevocationRequest(
                userId: $userId,
                siteId: $siteId,
                actorUserId: $adminId,
                reason: $reason,
            ));

            // 2. Only deactivate globally if the user has no remaining site access.
            $hasOtherAccess = $this->authorisation->hasOtherContributorAccess($userId, $siteId);
            if (!$hasOtherAccess) {
                $this->userLifecycle->deactivateContributor($userId, $adminId, $reason);
            }

            // 3. Cancel in-flight payouts with audit entries.
            $this->cancelInFlightPayouts($userId, $siteId, $adminId, $reason);

            // 4. Archive unpublished contributor pages.
            $this->archiveUnpublishedPages($userId, $siteId);
        });

        $this->permissionCacheInvalidator()?->invalidateUser($userId);

        $this->logger->info('Contributor account closed.', [
            'user_id' => $userId,
            'site_id' => $siteId,
            'closed_by' => $adminId,
            'reason' => $reason,
        ]);

        // Reload so event listeners see the updated is_active state.
        $freshUser = $this->userLifecycle->findById($userId);

        $this->eventDispatcher->dispatch(
            new ContributorAccountClosedEvent($freshUser, $adminId, $reason)
        );
    }

    private function permissionCacheInvalidator(): ?PermissionCacheInvalidator
    {
        if ($this->permissionCacheInvalidator) {
            return $this->permissionCacheInvalidator;
        }

        try {
            return $this->permissionCacheInvalidator = app(PermissionCacheInvalidator::class);
        } catch (\Throwable) {
            return null;
        }
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
        $this->pageRepository->archiveUnpublishedContributorPages($userId, $siteId);
    }
}
