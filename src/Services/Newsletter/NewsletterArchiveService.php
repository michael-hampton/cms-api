<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Collection;
use App\Models\Newsletter;
use App\Models\Subscription;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;

class NewsletterArchiveService
{
    public function __construct(
        private readonly NewsletterRepository     $newsletterRepository,
        private readonly NewsletterSendRepository $newsletterSendRepository
    )
    {
    }

    /**
     * Get grouped archive for a newsletter
     */
    public function getNewsletterArchive(int $newsletterId, ?int $memberId = null): array
    {
        $newsletter = $this->newsletterRepository->find($newsletterId);

        if (!$newsletter) {
            return [
                'success' => false,
                'error' => 'Newsletter not found'
            ];
        }

        // Check access
        $accessCheck = $this->checkArchiveAccess($newsletter, $memberId);

        if (!$accessCheck['has_access']) {
            return [
                'success' => false,
                'requires_auth' => true,
                'access_type' => $accessCheck['access_type'],
                'message' => $accessCheck['message'],
                'newsletter' => $newsletter
            ];
        }

        // Get all sent editions
        $editions = $this->getNewsletterEditions($newsletterId);

        // Group by year
        $groupedByYear = $this->groupEditionsByYear($editions);

        // Get latest edition
        $latestEdition = $editions->first();

        return [
            'success' => true,
            'newsletter' => $newsletter,
            'latest_edition' => $latestEdition,
            'grouped_editions' => $groupedByYear,
            'total_editions' => $editions->count(),
            'years_available' => array_keys($groupedByYear)
        ];
    }

    /**
     * Check if site has an app (e.g., Kiplinger Personal Finance)
     */
    public function hasApp(int $siteId): bool
    {
        // For now, hardcode Kiplinger Personal Finance
        // You can make this configurable in the database later
        $sitesWithApps = [
            // Add site IDs that have apps
            // Example: 1 => 'Kiplinger Personal Finance'
        ];

        return isset($sitesWithApps[$siteId]);
    }

    /**
     * Check if user has access to newsletter archive
     */
    public function checkArchiveAccess(Newsletter $newsletter, ?int $memberId = null): array
    {
        // If newsletter is not premium, anyone can access
        if (!$newsletter->isPremium()) {
            return [
                'has_access' => true,
                'access_type' => 'public'
            ];
        }

        // No member ID - user is not logged in
        if (!$memberId) {
            return [
                'has_access' => false,
                'access_type' => 'anonymous',
                'message' => 'Please sign in or create an account to access this archive.'
            ];
        }

        $member = \App\Models\Member::find($memberId);

        if (!$member) {
            return [
                'has_access' => false,
                'access_type' => 'anonymous',
                'message' => 'Please sign in to access this archive.'
            ];
        }

        // Check if member has active subscription with access to this newsletter
        $hasAccess = $this->memberHasNewsletterAccess($memberId, $newsletter);

        if ($hasAccess) {
            return [
                'has_access' => true,
                'access_type' => 'subscriber'
            ];
        }

        // Member exists but doesn't have access
        // Check if they ever had a subscription
        $hadSubscription = $this->memberHadPreviousSubscription($memberId, $newsletter);

        if ($hadSubscription) {
            return [
                'has_access' => false,
                'access_type' => 'lapsed_subscriber',
                'message' => 'Your subscription has ended. Please renew to access this archive.'
            ];
        }

        return [
            'has_access' => false,
            'access_type' => 'non_subscriber',
            'message' => 'This archive is available to subscribers only. Please subscribe to access.'
        ];
    }

    /**
     * Check if member has active access to newsletter
     */
    private function memberHasNewsletterAccess(int $memberId, Newsletter $newsletter): bool
    {
        // Get active subscriptions
        $activeSubscriptions = Subscription::where('member_id', $memberId)
            ->where('site_id', $newsletter->site_id)
            ->where('status', 'active')
            ->get();

        foreach ($activeSubscriptions as $subscription) {
            // Check if subscription grants access to this newsletter
            if ($subscription->hasPremiumAccess('newsletter', $newsletter->slug)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if member had previous subscription
     */
    private function memberHadPreviousSubscription(int $memberId, Newsletter $newsletter): bool
    {
        return Subscription::where('member_id', $memberId)
            ->where('site_id', $newsletter->site_id)
            ->whereIn('status', ['expired', 'cancelled'])
            ->exists();
    }

    /**
     * Get all editions for a newsletter
     */
    private function getNewsletterEditions(int $newsletterId): Collection
    {
        // Get newsletter sends - these represent editions
        return \App\Models\NewsletterSend::where('newsletter_id', $newsletterId)
            ->whereNotNull('sent_at')
            ->orderBy('sent_at', 'desc')
            ->get();
    }

    /**
     * Group editions by year
     */
    private function groupEditionsByYear(Collection $editions): array
    {
        $grouped = [];

        foreach ($editions as $edition) {
            $year = $edition->sent_at->format('Y');

            if (!isset($grouped[$year])) {
                $grouped[$year] = [];
            }

            $edition->page_count = is_array($edition->content_snapshot) ? count($edition->content_snapshot) : 0;

            $grouped[$year][] = $edition;
        }

        // Sort years in reverse order
        krsort($grouped);

        return $grouped;
    }

    /**
     * Get archive summary for multiple newsletters
     */
    public function getArchiveSummaries(array $newsletterIds, ?int $memberId = null): array
    {
        $summaries = [];

        foreach ($newsletterIds as $newsletterId) {
            $newsletter = $this->newsletterRepository->find($newsletterId);

            if (!$newsletter) {
                continue;
            }

            $accessCheck = $this->checkArchiveAccess($newsletter, $memberId);
            $editions = $this->getNewsletterEditions($newsletterId);

            $summaries[] = [
                'newsletter' => $newsletter,
                'has_access' => $accessCheck['has_access'],
                'access_type' => $accessCheck['access_type'],
                'total_editions' => $editions->count(),
                'latest_edition' => $editions->first(),
                'years_available' => array_keys($this->groupEditionsByYear($editions))
            ];
        }

        return $summaries;
    }

    /**
     * Search and filter newsletters
     */
    public function searchNewsletters(int $siteId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = Newsletter::where('site_id', $siteId)
            ->where('active', true)
            ->whereNotNull('last_sent');

        // Apply search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                    ->orWhere('content', 'LIKE', "%{$search}%");
            });
        }

        // Only add whereHas if there are date/year filters
        $hasDateFilters = !empty($filters['date_from']) || !empty($filters['date_to']) || !empty($filters['year']);

        if ($hasDateFilters) {
            $query->whereHas('sends', function ($q) use ($filters) {

                if (!empty($filters['date_from'])) {
                    $q->where('sent_at', '>=', $filters['date_from']);
                }

                if (!empty($filters['date_to'])) {
                    $q->where('sent_at', '<=', $filters['date_to']);
                }

                if (!empty($filters['year'])) {
                    $q->whereYear('sent_at', $filters['year']);
                }

            });
        }

        // Apply interval filter
        if (!empty($filters['interval'])) {
            $query->where('interval', $filters['interval']);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'last_sent';
        $sortOrder = $filters['sort_order'] ?? 'desc';

        $query->orderBy($sortBy, $sortOrder);

        // Get total count before pagination
        $total = $query->count();

        // Apply pagination
        $offset = ($page - 1) * $perPage;
        $newsletters = $query->offset($offset)->limit($perPage)->get();

        return [
            'newsletters' => $newsletters,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ],
            'filters_applied' => $this->getAppliedFilters($filters),
        ];
    }

    /**
     * Get formatted applied filters for display
     */
    private function getAppliedFilters(array $filters): array
    {
        $applied = [];

        if (!empty($filters['search'])) {
            $applied[] = [
                'type' => 'search',
                'label' => 'Search',
                'value' => $filters['search'],
            ];
        }

        if (!empty($filters['interval'])) {
            $applied[] = [
                'type' => 'interval',
                'label' => 'Frequency',
                'value' => ucfirst($filters['interval']),
            ];
        }

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $value = '';
            if (!empty($filters['date_from'])) {
                $value .= 'From ' . $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $value .= (!empty($value) ? ' ' : '') . 'To ' . $filters['date_to'];
            }

            $applied[] = [
                'type' => 'date_range',
                'label' => 'Date Range',
                'value' => $value,
            ];
        }

        return $applied;
    }

    /**
     * Get available filter options
     */
    public function getFilterOptions(int $siteId): array
    {
        $newsletters = Newsletter::where('site_id', $siteId)
            ->where('active', true)
            ->whereNotNull('last_sent')
            ->get();

        // Get unique intervals
        $intervals = $newsletters->pluck('interval')->unique()->filter()->values()->toArray();

        // Get date range
        $oldestDate = $newsletters->min('last_sent');
        $newestDate = $newsletters->max('last_sent');

        return [
            'intervals' => $intervals,
            'date_range' => [
                'min' => $oldestDate ? $oldestDate->format('Y-m-d') : null,
                'max' => $newestDate ? $newestDate->format('Y-m-d') : null,
            ],
            'sort_options' => [
                ['value' => 'last_sent', 'label' => 'Date'],
                ['value' => 'title', 'label' => 'Title'],
            ],
        ];
    }

    /**
     * Get newsletter years for grouping
     */
    public function getNewsletterYears(int $siteId): array
    {
        $newsletters = Newsletter::where('site_id', $siteId)
            ->where('active', true)
            ->whereNotNull('last_sent')
            ->get();

        $years = $newsletters
            ->map(fn($n) => $n->last_sent->format('Y'))
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return array_reverse($years); // Most recent first
    }

    /**
     * Get newsletters grouped by month
     */
    public function getNewslettersByMonth(int $siteId, int $year, int $month): Collection
    {
        return Newsletter::where('site_id', $siteId)
            ->where('active', true)
            ->whereNotNull('last_sent')
            ->whereYear('last_sent', $year)
            ->whereMonth('last_sent', $month)
            ->orderBy('last_sent', 'desc')
            ->get();
    }
}