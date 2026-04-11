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
use App\Repositories\OpenCollab\PayoutRepository;
use App\Repositories\OpenCollab\UserSiteRepository;

/**
 * Handles full contributor account closure (admin-initiated).
 *
 * Distinct from a closure request (ContributorAccountController) — that is
 * the contributor asking. This is the admin executing the closure.
 *
 * On closure:
 *   1. User account is deactivated (is_active = false)
 *   2. Site access is revoked (oc_user_sites row removed)
 *   3. All pending/approved payouts are cancelled — balance preserved in
 *      the ledger for audit. Admin can manually process a final payout first.
 *   4. Contributor's draft/on_hold pages are archived (not deleted — content
 *      may have been part of a revenue-generating arrangement)
 *   5. Published pages are left published — contributor agreement governs this
 *   6. ContributorAccountClosedEvent fired — listeners notify contributor
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
     * @throws \RuntimeException on any write failure (transaction rolls back)
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

        $this->database->transaction(function () use ($userId, $siteId, $adminId, $reason, $user): void {
            // 1. Deactivate account
            $this->userRepository->update($userId, ['is_active' => false]);

            // 2. Revoke site access
            $this->userSiteRepository->revoke($userId, $siteId);

            // 3. Cancel in-flight payouts
            $this->cancelInFlightPayouts($userId, $adminId);

            // 4. Archive unpublished contributor pages
            $this->archiveUnpublishedPages($userId, $siteId);
        });

        $this->logger->info('Contributor account closed.', [
            'user_id' => $userId,
            'site_id' => $siteId,
            'closed_by' => $adminId,
            'reason' => $reason,
        ]);

        $this->eventDispatcher->dispatch(
            new ContributorAccountClosedEvent($user, $adminId, $reason)
        );
    }

    // -------------------------------------------------------------------------

    private function cancelInFlightPayouts(int $userId, int $adminId): void
    {
        $inFlight = $this->payoutRepository->forContributor($userId)
            ->filter(fn($p) => in_array($p->status, [
                PayoutStatus::Pending->value,
                PayoutStatus::Approved->value,
            ], true));

        foreach ($inFlight as $payout) {
            $this->payoutRepository->update($payout->id, [
                'status' => PayoutStatus::Rejected->value,
                'rejected_by' => $adminId,
                'rejected_at' => date('Y-m-d H:i:s'),
                'rejection_reason' => 'Account closed.',
            ]);
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