<?php

namespace App\Services\OpenCollab\Dashboard\Widgets;

use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Models\User;
use App\Repositories\Cms\SiteRepository;
use App\Services\OpenCollab\ContributorOnboardingService;
use App\Services\OpenCollab\Dashboard\Contracts\DashboardWidgetInterface;

/**
 * Onboarding widget — shows progress and pending steps.
 *
 * Auto-hides when onboarding is complete (visibleFor returns false).
 * This replaces the separate OnboardingDashboardController status endpoint.
 *
 * NOTE: SiteRepository is injected to avoid the static Site::find() call
 * that existed in the old OnboardingDashboardController.
 */
final class OnboardingWidget implements DashboardWidgetInterface
{
    public function __construct(
        private readonly ContributorOnboardingService $onboardingService,
        private readonly SiteRepository               $siteRepository,
    ) {}

    public function key(): string
    {
        return 'onboarding';
    }

    public function title(): string
    {
        return 'Getting Started';
    }

    /**
     * Hide the widget entirely once onboarding is complete.
     * The resolver will exclude it from the final list.
     */
    public function visibleFor(User $user): bool
    {
        $site = $this->resolveSite($user);

        if (!$site) {
            return false;
        }

        $pending = $this->onboardingService->pendingSteps($user->id, $site);

        return !empty($pending);
    }

    public function data(User $user): array
    {
        $site = $this->resolveSite($user);

        if (!$site) {
            return $this->emptyState();
        }

        $pendingSteps   = $this->onboardingService->pendingSteps($user->id, $site);
        $completedSteps = $this->onboardingService->completedSteps($user->id, $site);
        $totalSteps     = count($pendingSteps) + count($completedSteps);

        return [
            'is_complete'      => empty($pendingSteps),
            'completed_count'  => count($completedSteps),
            'total_steps'      => $totalSteps,
            'completed_steps'  => $completedSteps,
            'pending_steps'    => $pendingSteps,
            'progress_percent' => $totalSteps > 0
                ? (int)round((count($completedSteps) / $totalSteps) * 100)
                : 0,
            // SSE stream URL — the JS renderer uses this to wire live step updates.
            // The token is injected by window.DASHBOARD_TOKEN (set in the Blade view).
            'sse_url'          => "/api/{$site->slug}/open-collab/events/stream",
        ];
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function resolveSite(User $user): ?Site
    {
        $currentSiteId = SiteContext::getId();

        if (!$currentSiteId) {
            return null;
        }

        // Verify the user actually belongs to this site before proceeding
        $belongsToSite = $user->sites
            && $user->sites->contains('id', $currentSiteId);

        if (!$belongsToSite) {
            return null;
        }

        return $this->siteRepository->find($currentSiteId);
    }

    private function emptyState(): array
    {
        return [
            'is_complete'      => false,
            'completed_count'  => 0,
            'total_steps'      => 0,
            'completed_steps'  => [],
            'pending_steps'    => [],
            'progress_percent' => 0,
        ];
    }
}