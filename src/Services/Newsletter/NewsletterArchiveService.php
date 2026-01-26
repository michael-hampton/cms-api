<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Collection;
use App\Models\Newsletter;
use App\Repositories\Newsletters\NewsletterRepository;

class NewsletterArchiveService
{
    public function __construct(
        private readonly NewsletterRepository $newsletterRepository
    )
    {
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

        // Apply date range filter
        if (!empty($filters['date_from'])) {
            $query->where('last_sent', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('last_sent', '<=', $filters['date_to']);
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