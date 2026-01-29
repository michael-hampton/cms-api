<?php

namespace App\Services\Newsletter;

use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendPageViewRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use Exception;

class NewsletterStatisticsService
{
    public function __construct(
        private readonly NewsletterRepository             $newsletterRepository,
        private readonly NewsletterSendRepository         $newsletterSendRepository,
        private readonly NewsletterSendPageViewRepository $newsletterSendPageViewRepository
    )
    {
    }

    public function getAllNewsletterStatistics(int $siteId): array
    {
        // Get all newsletters for this site
        $newsletters = $this->newsletterRepository->findBySite($siteId);
        $newsletterIds = $newsletters->pluck('id')->toArray();

        // Get all sends for these newsletters
        $sends = $this->newsletterSendRepository->getSendsByNewsletterIds($newsletterIds);

        $totalRecipients = $sends->sum('recipient_count');
        $totalSends = $sends->count();
        $failedCount = $sends->sum('failed_count');
        $pendingCount = $sends->sum('pending_count');

        $clickStatistics = $this->newsletterSendPageViewRepository->getViewStatisticsByNewsletterIds($newsletterIds);
        $uniqueClickers = $clickStatistics['unique_recipients'];
        $totalClicks = $clickStatistics['total_clicks'];

        $clickThroughRate = $totalRecipients > 0
            ? round(($uniqueClickers / $totalRecipients) * 100, 2)
            : 0;

        $clicksPerRecipient = $totalRecipients > 0
            ? round($totalClicks / $totalRecipients, 2)
            : 0;

        return [
            'total_newsletters' => count($newsletterIds),
            'total_sends' => $totalSends,
            'total_recipients' => $totalRecipients,
            'total_clicks' => $totalClicks,
            'unique_clickers' => $uniqueClickers,
            'click_through_rate' => $clickThroughRate,
            'failed_sends' => $failedCount,
            'pending_sends' => $pendingCount,
            'clicks_per_recipient' => $clicksPerRecipient,
            'top_clicked_pages' => $this->newsletterSendPageViewRepository->getTopClickedPages($sends->pluck('id')->toArray())->toArray(),
            'sends_by_date' => $sends->groupBy(function ($send) {
                return $send->sent_at->format('Y-m-d');
            })->map(function ($dailySends) {
                return [
                    'date' => $dailySends->first()->sent_at->format('Y-m-d'),
                    'sends' => $dailySends->count(),
                    'recipients' => $dailySends->sum('recipient_count')
                ];
            })->values()->toArray()
        ];
    }

    public function getNewsletterStatistics(int $newsletterId): array
    {
        $newsletter = $this->newsletterRepository->find($newsletterId);

        if (!$newsletter) {
            throw new Exception('Newsletter not found');
        }

        $sends = $this->newsletterSendRepository->getSendsForNewsletter($newsletterId);

        $totalRecipients = $sends->sum('recipient_count');
        $totalSends = $sends->count();
        $failedCount = $sends->sum('failed_count');
        $pendingCount = $sends->sum('pending_count');

        $clickStatistics = $this->newsletterSendPageViewRepository->getViewStatistics($newsletterId);
        $uniqueClickers = $clickStatistics['unique_recipients'];
        $totalClicks = $clickStatistics['total_clicks'];

        $clickThroughRate = $totalRecipients > 0
            ? round(($uniqueClickers / $totalRecipients) * 100, 2)
            : 0;

        $clicksPerRecipient = $totalRecipients > 0
            ? round($totalClicks / $totalRecipients, 2)
            : 0;

        return [
            'newsletter_id' => $newsletterId,
            'newsletter_name' => $newsletter->title,
            'total_sends' => $totalSends,
            'total_recipients' => $totalRecipients,
            'total_clicks' => $totalClicks,
            'unique_clickers' => $uniqueClickers,
            'click_through_rate' => $clickThroughRate,
            'failed_sends' => $failedCount,
            'pending_sends' => $pendingCount,
            'clicks_per_recipient' => $clicksPerRecipient,
            'top_clicked_pages' => $this->newsletterSendPageViewRepository->getTopClickedPages($sends->pluck('id')->toArray())->toArray(),
            'sends_by_date' => $sends->map(fn($send) => [
                'sent_at' => $send->sent_at?->format('Y-m-d H:i:s'),
                'recipient_count' => $send->recipient_count
            ])->toArray()
        ];
    }


}