<?php

namespace App\Listeners\OpenCollab;

use App\Events\OpenCollab\ContractPublishedEvent;
use App\Events\OpenCollab\GuidelinesVersionBumpedEvent;
use App\Framework\Notifications\NotificationDispatcher;
use App\Framework\Support\Logger;
use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\OpenCollab\UserSiteRepository;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\Notifications\OnboardingInvalidatedNotification;

/**
 * Reacts to contract or guidelines changes by:
 *   1. Syncing onboarding status for every affected contributor on the site.
 *   2. Notifying contributors whose previously complete onboarding is now invalid.
 *
 * This listener handles both ContractPublishedEvent and GuidelinesVersionBumpedEvent
 * because the reaction pattern is identical — only the trigger differs.
 *
 * IMPORTANT: syncStatus() writes a convenience snapshot but pendingSteps() remains
 * the source of truth. Even if this listener fails, the system stays correct.
 *
 * Non-critical path per contributor: a failure for one user must not block others.
 */
class InvalidateContributorOnboardingListener
{
    public function __construct(
        private readonly ContributorOnboardingService $onboardingService,
        private readonly UserRepositoryInterface      $userRepository,
        private readonly NotificationDispatcher       $notificationDispatcher,
        private readonly Logger                       $logger,
        private readonly SiteRepository               $siteRepository,
        private readonly UserSiteRepository           $userSiteRepository
    )
    {
    }

    /**
     * Handle ContractPublishedEvent.
     */
    public function onContractPublished(ContractPublishedEvent $event): void
    {
        $site = $this->siteRepository->find($event->siteId);

        if (!$site) {
            $this->logger->warning('InvalidateContributorOnboardingListener: site not found.', [
                'site_id' => $event->siteId,
            ]);
            return;
        }

        $this->invalidateContributorsForSite($site);
    }

    /**
     * For every contributor on this site:
     *   - Sync their onboarding status snapshot.
     *   - If they now have pending steps, notify them.
     *
     * Failures are caught and logged per-contributor so one bad record
     * cannot block the rest of the batch.
     */
    private function invalidateContributorsForSite(Site $site): void
    {
        $userIds = $this->userSiteRepository->userIdsForSite($site->id);

        foreach ($userIds as $userId) {
            try {
                $this->onboardingService->syncStatus($userId, $site);

                $pendingSteps = $this->onboardingService->pendingSteps($userId, $site);

                if (empty($pendingSteps)) {
                    continue;
                }

                $user = $this->userRepository->find($userId);

                if (!$user || !$user->is_contributor) {
                    continue;
                }

                $this->notificationDispatcher->dispatch(
                    new OnboardingInvalidatedNotification($user, $site->id, $pendingSteps),
                );
            } catch (\Throwable $e) {
                $this->logger->error('Failed to invalidate onboarding for contributor.', [
                    'user_id' => $userId,
                    'site_id' => $site->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Handle GuidelinesVersionBumpedEvent.
     */
    public function onGuidelinesBumped(GuidelinesVersionBumpedEvent $event): void
    {
        $site = $this->siteRepository->find($event->siteId);

        if (!$site) {
            $this->logger->warning('InvalidateContributorOnboardingListener: site not found.', [
                'site_id' => $event->siteId,
            ]);
            return;
        }

        $this->invalidateContributorsForSite($site);
    }
}