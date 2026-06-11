<?php

namespace App\Services\Cms\Pages;

use App\Framework\Support\SiteContext;
use App\Models\ArticleAccess;
use App\Models\EditorialOverride;
use App\Models\Member;
use App\Models\Page;
use App\Models\SubscriptionWindow;
use App\Repositories\OpenCollab\ArticleAccessRepository;
use DateTimeImmutable;
use DateTimeZone;

class ArticleAccessService
{
    private DateTimeZone $utcTimezone;

    public function __construct(
        private readonly ArticleAccessRepository $articleAccessRepository,
    ) {
        $this->utcTimezone = new DateTimeZone('UTC');
    }

    /**
     * Bulk enrich multiple pages efficiently (for listings)
     * Optimized to avoid N+1 queries
     */
    public function enrichPagesWithAccessInfo(array $pages, ?Member $member = null, ?int $siteId = null): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        if (empty($pages)) {
            return [];
        }

        $enrichedPages = [];

        // If no member, simple pass-through with access checks
        if (!$member) {
            foreach ($pages as $page) {
                $pageData = is_array($page) ? $page : $page->toArray();
                $pageModel = is_array($page) ? Page::find($page['id']) : $page;

                $accessInfo = $this->enrichPageWithAccessInfo($pageModel, null);
                $enrichedPages[] = array_merge($pageData, $accessInfo);
            }
            return $enrichedPages;
        }

        // For members, optimize by loading all windows once
        $memberWindows = $member->getSubscriptionWindows($siteId);
        $hasActivePaid = $member->hasActiveSubscriptionOfType('paid', $siteId);
        $hasActiveTrial = $member->hasActiveSubscriptionOfType('trial', $siteId);

        foreach ($pages as $page) {
            $pageData = is_array($page) ? $page : $page->toArray();
            $pageModel = is_array($page) ? Page::find($page['id']) : $page;

            // Use optimized batch check
            $accessInfo = $this->checkAccessWithPreloadedData(
                $pageModel,
                $member,
                $memberWindows,
                $hasActivePaid,
                $hasActiveTrial
            );

            $enrichedPages[] = array_merge($pageData, $accessInfo);
        }

        return $enrichedPages;
    }

    /**
     * Enrich a single page with access information
     */
    public function enrichPageWithAccessInfo(Page $page, ?Member $member = null, ?int $siteId = null): array
    {
        $accessCheck = $this->canView($page, $member, $siteId);

        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'access_level' => $this->getPageAccessLevel($page),
            'can_view' => $accessCheck['can_view'],
            'denial_reason' => $accessCheck['can_view'] ? null : $accessCheck['reason'],
            'access_reason' => $accessCheck['can_view'] ? $accessCheck['reason'] : null
        ];
    }

    /**
     * Check if a member can view a specific page
     */
    public function canView(Page $page, ?Member $member = null, ?int $siteId = null): array
    {
        // 1. Check editorial overrides first (highest priority)
        if ($override = $this->checkEditorialOverride($page, $member)) {
            return [
                'can_view' => true,
                'reason' => 'editorial_override',
                'override_id' => $override->id
            ];
        }

        // 2. Get page access level
        $accessLevel = $this->getPageAccessLevel($page);

        // 3. Free content - anyone can view
        if ($accessLevel === 'free') {
            return ['can_view' => true, 'reason' => null];
        }

        // 4. Member-only content
        if ($accessLevel === 'member') {
            if (!$member) {
                return ['can_view' => false, 'reason' => 'member_required'];
            }
            return ['can_view' => true, 'reason' => 'member_authenticated'];
        }

        // 5. Premium content
        if ($accessLevel === 'premium') {
            return $this->checkPremiumAccess($page, $member, $siteId);
        }

        return ['can_view' => false, 'reason' => 'unknown_access_level'];
    }

    /**
     * Check for editorial overrides with timezone-safe date comparisons
     */
    private function checkEditorialOverride(Page $page, ?Member $member): ?EditorialOverride
    {
        $now = new DateTimeImmutable('now', $this->utcTimezone);

        // User-specific override takes precedence
        if ($member) {
            $override = EditorialOverride::where('page_id', $page->id)
                ->where('member_id', $member->id)
                ->where('starts_at', '<=', $now->format('Y-m-d H:i:s'))
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $now->format('Y-m-d H:i:s'));
                })
                ->first();

            if ($override) return $override;
        }

        // Global override for this page
        $override = EditorialOverride::where('page_id', $page->id)
            ->whereNull('member_id')
            ->where('starts_at', '<=', $now->format('Y-m-d H:i:s'))
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now->format('Y-m-d H:i:s'));
            })
            ->first();

        if ($override) return $override;

        // Site-wide promotional override (page_id is null)
        $override = EditorialOverride::whereNull('page_id')
            ->whereNull('member_id')
            ->where('starts_at', '<=', $now->format('Y-m-d H:i:s'))
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now->format('Y-m-d H:i:s'));
            })
            ->first();

        return $override;
    }

    /**
     * Get page access level with fallback
     */
    private function getPageAccessLevel(Page $page): string
    {
        if (!$page->metadata) {
            return 'free'; // Default to free if no metadata
        }

        return $page->metadata->getAccessLevel();
    }

    /**
     * Check premium content access with subscription logic
     */
    private function checkPremiumAccess(Page $page, ?Member $member, ?int $siteId): array
    {
        $siteId = $siteId ?? SiteContext::getId();

        if ($member && $this->hasOneOffPurchaseAccess($page, $member)) {
            return [
                'can_view' => true,
                'reason' => 'one_off_page_purchase',
            ];
        }

        if (!$member) {
            return ['can_view' => false, 'reason' => 'subscription_required'];
        }

        // Check active paid subscription
        if ($member->hasActiveSubscriptionOfType('paid', $siteId)) {
            return ['can_view' => true, 'reason' => 'active_paid_subscription'];
        }

        // Check active trial
        if ($member->hasActiveSubscriptionOfType('trial', $siteId)) {
            return ['can_view' => true, 'reason' => 'active_trial'];
        }

        // Check historical subscription windows (PAID only)
        $accessResult = $this->checkHistoricalAccess($page, $member);

        if ($accessResult['has_access']) {
            return ['can_view' => true, 'reason' => $accessResult['reason']];
        }

        return [
            'can_view' => false,
            'reason' => $accessResult['denial_reason']
        ];
    }

    /**
     * Check access through historical subscription windows (PAID only)
     * Uses efficient DB query instead of PHP loop
     */
    private function checkHistoricalAccess(Page $page, Member $member): array
    {
        if (!$page->published_at) {
            return [
                'has_access' => false,
                'denial_reason' => 'page_not_published'
            ];
        }

        // Convert to UTC for timezone-safe comparison
        $publishedAt = ($page->published_at instanceof DateTimeImmutable)
            ? $page->published_at->setTimezone($this->utcTimezone)
            : DateTimeImmutable::createFromMutable($page->published_at)
                ->setTimezone($this->utcTimezone);

        // Single DB query to check if page falls within any paid subscription window
        $matchingWindow = SubscriptionWindow::where('member_id', $member->id)
            ->where('site_id', $page->site_id)
            ->where('type', 'paid')
            ->where('window_start', '<=', $publishedAt->format('Y-m-d H:i:s'))
            ->where('window_end', '>=', $publishedAt->format('Y-m-d H:i:s'))
            ->first();

        if ($matchingWindow) {
            return [
                'has_access' => true,
                'reason' => 'historical_subscription_window',
                'window_id' => $matchingWindow->id
            ];
        }

        // Determine specific denial reason
        $hasAnyWindow = SubscriptionWindow::where('member_id', $member->id)
            ->where('site_id', $page->site_id)
            ->where('type', 'paid')
            ->exists();

        if (!$hasAnyWindow) {
            return [
                'has_access' => false,
                'denial_reason' => 'no_subscription_history'
            ];
        }

        // Check if page was published before first subscription
        $firstWindow = SubscriptionWindow::where('member_id', $member->id)
            ->where('site_id', $page->site_id)
            ->where('type', 'paid')
            ->orderBy('window_start', 'asc')
            ->first();

        $windowStart = ($firstWindow->window_start instanceof DateTimeImmutable)
            ? $firstWindow->window_start->setTimezone($this->utcTimezone)
            : DateTimeImmutable::createFromMutable($firstWindow->window_start)
                ->setTimezone($this->utcTimezone);

        if ($firstWindow && $publishedAt < $windowStart) {
            return [
                'has_access' => false,
                'denial_reason' => 'published_before_subscription'
            ];
        }

        // Page was published after last subscription ended
        return [
            'has_access' => false,
            'denial_reason' => 'published_after_subscription'
        ];
    }

    /**
     * Optimized access check using preloaded member data
     */
    private function checkAccessWithPreloadedData(
        Page   $page,
        Member $member,
               $memberWindows,
        bool   $hasActivePaid,
        bool   $hasActiveTrial,
        ?int   $siteId = null
    ): array
    {
        $siteId = $siteId ?? SiteContext::getId();
        // Check editorial override
        if ($this->checkEditorialOverride($page, $member)) {
            return [
                'access_level' => $this->getPageAccessLevel($page),
                'can_view' => true,
                'denial_reason' => null,
                'access_reason' => 'editorial_override'
            ];
        }

        $accessLevel = $this->getPageAccessLevel($page);

        if ($accessLevel === 'free') {
            return [
                'access_level' => 'free',
                'can_view' => true,
                'denial_reason' => null,
                'access_reason' => null
            ];
        }

        if ($accessLevel === 'member') {
            return [
                'access_level' => 'member',
                'can_view' => true,
                'denial_reason' => null,
                'access_reason' => 'member_authenticated'
            ];
        }

        // Premium content
        if ($hasActivePaid) {
            return [
                'access_level' => 'premium',
                'can_view' => true,
                'denial_reason' => null,
                'access_reason' => 'active_paid_subscription'
            ];
        }

        if ($hasActiveTrial) {
            return [
                'access_level' => 'premium',
                'can_view' => true,
                'denial_reason' => null,
                'access_reason' => 'active_trial'
            ];
        }

        // Check historical windows (in-memory, already loaded)
        if ($page->published_at) {
            $publishedAt = ($page->published_at instanceof DateTimeImmutable)
                ? $page->published_at->setTimezone($this->utcTimezone)
                : DateTimeImmutable::createFromMutable($page->published_at)
                    ->setTimezone($this->utcTimezone);

            foreach ($memberWindows as $window) {
                $windowStart = ($window->window_start instanceof DateTimeImmutable)
                    ? $window->window_start->setTimezone($this->utcTimezone)
                    : DateTimeImmutable::createFromMutable($window->window_start)
                        ->setTimezone($this->utcTimezone);

                $windowEnd = ($window->window_end instanceof DateTimeImmutable)
                    ? $window->window_end->setTimezone($this->utcTimezone)
                    : DateTimeImmutable::createFromMutable($window->window_end)
                        ->setTimezone($this->utcTimezone);

                if ($publishedAt >= $windowStart && $publishedAt <= $windowEnd) {
                    return [
                        'access_level' => 'premium',
                        'can_view' => true,
                        'denial_reason' => null,
                        'access_reason' => 'historical_subscription_window'
                    ];
                }
            }
        }

        // No access
        return [
            'access_level' => 'premium',
            'can_view' => false,
            'denial_reason' => 'published_after_subscription',
            'access_reason' => null
        ];
    }

    private function hasOneOffPurchaseAccess(Page $page, Member $member): bool
    {
        $userId = $member->user_id ?? null;

        if ($userId !== null && $this->articleAccessRepository->hasAccessByUserId(
                (int) $page->id,
                (int) $userId
            )) {
            return true;
        }

        $email = trim((string) ($member->email ?? ''));

        if ($email !== '' && $this->articleAccessRepository->hasAccessByEmail(
                (int) $page->id,
                $email
            )) {
            return true;
        }

        return false;
    }
}