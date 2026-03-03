<?php

namespace App\Repositories\Newsletters;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendPageView;
use App\Repositories\Repository;

class NewsletterSendPageViewRepository extends Repository
{
    /**
     * Track a page view from a newsletter
     */
    public function trackPageView(int $sendId, int $pageId, ?string $email, ?string $ipAddress, ?string $userAgent): void
    {
        $this->create([
            'newsletter_send_id' => $sendId,
            'page_id' => $pageId,
            'email' => $email,
            'clicked_at' => now_datetime()->format('Y-m-d H:i:s'),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }

    /**
     * Get page views for a specific newsletter send
     */
    public function getViewsForSend(int $sendId): array
    {
        return NewsletterSendPageView::where('newsletter_send_id', $sendId)
            ->with(['page'])
            ->orderBy('clicked_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get page view statistics for a newsletter send
     */
    public function getViewStatistics(int $sendId): array
    {
        $views = NewsletterSendPageView::where('newsletter_send_id', $sendId)
            ->get();

        $uniqueEmails = $views->pluck('email')->unique()->count();
        $totalClicks = $views->count();
        $pageClicks = $views->groupBy('page_id')->map(fn($group) => $group->count())->toArray();

        return [
            'total_clicks' => $totalClicks,
            'unique_recipients' => $uniqueEmails,
            'page_clicks' => $pageClicks,
            'click_through_rate' => null // Will be calculated when we have recipient count
        ];
    }

    /**
     * Get the top clicked pages with their click counts.
     */
    public function getTopClickedPages(array $sendIds, int $limit = 10): Collection
    {
        return Database::table('newsletter_send_page_views as nspv')
            ->join('pages as p', 'nspv.page_id', '=', 'p.id')
            ->whereIn('nspv.newsletter_send_id', $sendIds)
            ->select('p.id', 'p.title', 'p.slug', Database::raw('COUNT(*) as click_count'))
            ->groupBy('p.id', 'p.title', 'p.slug')
            ->orderByDesc('click_count')
            ->limit($limit)
            ->get();
    }

    public function getViewStatisticsByNewsletterIds(array $newsletterIds): array
    {
        $sendIds = NewsletterSend::whereIn('newsletter_id', $newsletterIds)
            ->pluck('id');

        return $this->getViewStatisticsBySendIds($sendIds);
    }

    private function getViewStatisticsBySendIds(array $sendIds): array
    {
        if (empty($sendIds)) {
            return [
                'total_clicks' => 0,
                'unique_recipients' => 0
            ];
        }

        $totalClicks = NewsletterSendPageView::whereIn('newsletter_send_id', $sendIds)
            ->count();

        $uniqueClickers = NewsletterSendPageView::whereIn('newsletter_send_id', $sendIds)
            ->distinct('email')
            ->count('email');

        return [
            'total_clicks' => $totalClicks,
            'unique_recipients' => $uniqueClickers
        ];
    }

    public function getViewsBySendIds(array $sendIds, ?string $dateFrom = null, ?string $dateTo = null): Collection
    {
        $query = NewsletterSendPageView::whereIn('newsletter_send_id', $sendIds);

        if ($dateFrom) {
            $query->whereDate('clicked_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('clicked_at', '<=', $dateTo);
        }

        return $query->get();
    }


    protected function getModelClass(): string
    {
        return NewsletterSendPageView::class;
    }
}