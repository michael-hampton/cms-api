<?php

namespace App\Repositories\Newsletters;

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

    protected function getModelClass(): string
    {
        return NewsletterSendPageView::class;
    }
}