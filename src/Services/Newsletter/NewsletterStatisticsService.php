<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Collection;
use App\Models\NewsletterSendRecipient;
use App\Models\Page;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendPageViewRepository;
use App\Repositories\Newsletters\NewsletterSendRecipientRepository;
use App\Repositories\Newsletters\NewsletterSendRepository;
use Exception;

class NewsletterStatisticsService
{
    public function __construct(
        private readonly NewsletterRepository              $newsletterRepository,
        private readonly NewsletterSendRepository          $newsletterSendRepository,
        private readonly NewsletterSendPageViewRepository  $newsletterSendPageViewRepository,
        private readonly NewsletterSendRecipientRepository $newsletterSendRecipientRepository,
    )
    {
    }

    public function getAllNewsletterStatistics(int $siteId): array
    {
        $newsletters = $this->newsletterRepository->findBySite($siteId);
        $newsletterIds = $newsletters->pluck('id')->toArray();

        $sends = $this->newsletterSendRepository->getSendsByNewsletterIds($newsletterIds);
        $totalRecipients = $sends->sum('recipient_count');
        $totalSends = $sends->count();
        $failedCount = $sends->sum('failed_count');
        $pendingCount = $sends->sum('pending_count');

        $clickStats = $this->newsletterSendPageViewRepository->getViewStatisticsByNewsletterIds($newsletterIds);
        $uniqueClickers = $clickStats['unique_recipients'];
        $totalClicks = $clickStats['total_clicks'];
        $clickThroughRate = $totalRecipients > 0 ? round(($uniqueClickers / $totalRecipients) * 100, 2) : 0;
        $clicksPerRecipient = $totalRecipients > 0 ? round($totalClicks / $totalRecipients, 2) : 0;

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
            'top_clicked_pages' => $this->newsletterSendPageViewRepository
                ->getTopClickedPages($sends->pluck('id')->toArray())
                ->toArray(),
            'sends_by_date' => $sends->groupBy(fn($s) => $s->sent_at->format('Y-m-d'))
                ->map(fn($daily) => [
                    'date' => $daily->first()->sent_at->format('Y-m-d'),
                    'sends' => $daily->count(),
                    'recipients' => $daily->sum('recipient_count'),
                ])->values()->toArray(),
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

        $clickStats = $this->newsletterSendPageViewRepository->getViewStatistics($newsletterId);
        $uniqueClickers = $clickStats['unique_recipients'];
        $totalClicks = $clickStats['total_clicks'];
        $clickThroughRate = $totalRecipients > 0 ? round(($uniqueClickers / $totalRecipients) * 100, 2) : 0;
        $clicksPerRecipient = $totalRecipients > 0 ? round($totalClicks / $totalRecipients, 2) : 0;

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
            'top_clicked_pages' => $this->newsletterSendPageViewRepository
                ->getTopClickedPages($sends->pluck('id')->toArray())
                ->toArray(),
            'sends_by_date' => $sends->map(fn($s) => [
                'sent_at' => $s->sent_at?->format('Y-m-d H:i:s'),
                'recipient_count' => $s->recipient_count,
            ])->toArray(),
        ];
    }

    public function getClickDetails(
        int     $siteId,
        int     $page = 1,
        int     $perPage = 20,
        ?string $sortBy = null,
        string  $sortDirection = 'desc',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null
    ): array
    {
        [$newsletterMap, $sendMap, $sendIds] = $this->buildNewsletterAndSendMaps($siteId);

        if (empty($sendIds)) {
            return $this->emptyPage($perPage, $page);
        }

        $views = $this->newsletterSendPageViewRepository->getViewsBySendIds($sendIds, $dateFrom, $dateTo);
        $pageIds = $views->pluck('page_id')->unique()->toArray();
        $pageMap = empty($pageIds)
            ? new Collection([])
            : Page::whereIn('id', $pageIds)->get()->keyBy('id');

        $rows = $views->map(function ($view) use ($sendMap, $newsletterMap, $pageMap) {
            $send = $sendMap->get($view->newsletter_send_id);
            $newsletter = $send ? $newsletterMap->get($send->newsletter_id) : null;
            $page = $pageMap->get($view->page_id);

            return [
                'email' => $view->email,
                'page_id' => $view->page_id,
                'page_title' => $page?->title,
                'page_slug' => $page?->slug,
                'clicked_at' => is_string($view->clicked_at) ? $view->clicked_at : $view->clicked_at->format('Y-m-d H:i:s'),
                'newsletter_id' => $newsletter?->id,
                'newsletter_title' => $newsletter?->title,
                'sent_at' => $send?->sent_at,
                'ip_address' => $view->ip_address,
                'user_agent' => $view->user_agent,
            ];
        });

        if ($search) {
            $lower = strtolower($search);
            $rows = $rows->filter(fn($r) => str_contains(strtolower((string)($r['email'] ?? '')), $lower) ||
                str_contains(strtolower((string)($r['page_title'] ?? '')), $lower) ||
                str_contains(strtolower((string)($r['newsletter_title'] ?? '')), $lower)
            )->values();
        }

        $validSort = ['email', 'page_title', 'newsletter_title', 'clicked_at'];
        $sortColumn = in_array($sortBy, $validSort) ? $sortBy : 'clicked_at';

        return $this->sortAndPaginate($rows, $sortColumn, $sortDirection, $page, $perPage);
    }

    public function getFailedSendDetails(
        int     $siteId,
        int     $page = 1,
        int     $perPage = 20,
        ?string $sortBy = null,
        string  $sortDirection = 'desc',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null
    ): array
    {
        [$newsletterMap, $sendMap, $sendIds] = $this->buildNewsletterAndSendMaps($siteId);

        if (empty($sendIds)) {
            return $this->emptyPage($perPage, $page);
        }

        $recipients = $this->newsletterSendRecipientRepository->getRecipientsBySendIds(
            $sendIds,
            NewsletterSendRecipient::STATUS_FAILED,
            $dateFrom,
            $dateTo
        );

        $rows = $recipients->map(function ($recipient) use ($sendMap, $newsletterMap) {
            $send = $sendMap->get($recipient->newsletter_send_id);
            $newsletter = $send ? $newsletterMap->get($send->newsletter_id) : null;

            return [
                'recipient_id' => $recipient->id,
                'email' => $recipient->email,
                'error_message' => $recipient->error_message,
                'attempts' => $recipient->attempts,
                'failed_at' => $recipient->updated_at,
                'newsletter_id' => $newsletter?->id,
                'newsletter_title' => $newsletter?->title,
                'sent_at' => $send?->sent_at,
            ];
        });

        if ($search) {
            $lower = strtolower($search);
            $rows = $rows->filter(fn($r) => str_contains(strtolower((string)($r['email'] ?? '')), $lower) ||
                str_contains(strtolower((string)($r['newsletter_title'] ?? '')), $lower) ||
                str_contains(strtolower((string)($r['error_message'] ?? '')), $lower)
            )->values();
        }

        $validSort = ['email', 'newsletter_title', 'attempts', 'failed_at'];
        $sortColumn = in_array($sortBy, $validSort) ? $sortBy : 'failed_at';

        return $this->sortAndPaginate($rows, $sortColumn, $sortDirection, $page, $perPage);
    }

    public function getUniqueClickerDetails(
        int     $siteId,
        int     $page = 1,
        int     $perPage = 20,
        ?string $sortBy = null,
        string  $sortDirection = 'desc',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null
    ): array
    {
        [$newsletters, $sends, $sendIds] = $this->buildNewsletterAndSendMaps($siteId);

        if (empty($sendIds)) {
            return $this->emptyPage($perPage, $page);
        }

        $views = $this->newsletterSendPageViewRepository->getViewsBySendIds($sendIds, $dateFrom, $dateTo);

        $rows = $views->groupBy('email')->map(function ($emailViews, $email) {
            $dates = $emailViews->pluck('clicked_at')->filter()->toArray();
            $lastClicked = !empty($dates) ? max($dates) : null;
            $firstClicked = !empty($dates) ? min($dates) : null;

            return [
                'email' => $email,
                'click_count' => $emailViews->count(),
                'unique_pages_clicked' => $emailViews->pluck('page_id')->unique()->count(),
                'last_clicked_at' => is_string($lastClicked) ? $lastClicked : $lastClicked->format('Y-m-d H:i:s'),
                'first_clicked_at' => is_string($firstClicked) ? $firstClicked : $firstClicked->format('Y-m-d H:i:s'),
            ];
        })->values();

        if ($search) {
            $lower = strtolower($search);
            $rows = $rows->filter(fn($r) => str_contains(strtolower((string)($r['email'] ?? '')), $lower)
            )->values();
        }

        $validSort = ['email', 'click_count', 'last_clicked_at'];
        $sortColumn = in_array($sortBy, $validSort) ? $sortBy : 'click_count';

        return $this->sortAndPaginate($rows, $sortColumn, $sortDirection, $page, $perPage);
    }

    public function getSendDetails(
        int     $siteId,
        int     $page = 1,
        int     $perPage = 20,
        ?string $sortBy = null,
        string  $sortDirection = 'desc',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null
    ): array
    {
        $newsletters = $this->newsletterRepository->findBySite($siteId);
        $newsletterIds = $newsletters->pluck('id')->toArray();

        if (empty($newsletterIds)) {
            return $this->emptyPage($perPage, $page);
        }

        $newsletterMap = $newsletters->keyBy('id');
        $sends = $this->newsletterSendRepository->getSendsByNewsletterIds($newsletterIds);

        if ($dateFrom) {
            $sends = $sends->filter(fn($s) => $this->sentOnOrAfter($s, $dateFrom))->values();
        }
        if ($dateTo) {
            $sends = $sends->filter(fn($s) => $this->sentOnOrBefore($s, $dateTo))->values();
        }

        $rows = $sends->map(function ($send) use ($newsletterMap) {
            $newsletter = $newsletterMap->get($send->newsletter_id);

            return [
                'send_id' => $send->id,
                'newsletter_title' => $newsletter?->title,
                'sent_at' => $send->sent_at instanceof \DateTimeInterface
                    ? $send->sent_at->format('Y-m-d H:i:s')
                    : $send->sent_at,
                'total_recipients' => $send->recipient_count ?? 0,
                'success_count' => $send->success_count ?? 0,
                'failed_count' => $send->failed_count ?? 0,
            ];
        });

        if ($search) {
            $lower = strtolower($search);
            $rows = $rows->filter(fn($r) => str_contains(strtolower((string)($r['newsletter_title'] ?? '')), $lower)
            )->values();
        }

        $validSort = ['newsletter_title', 'sent_at', 'success_count', 'failed_count'];
        $sortColumn = in_array($sortBy, $validSort) ? $sortBy : 'sent_at';

        return $this->sortAndPaginate($rows, $sortColumn, $sortDirection, $page, $perPage);
    }

    public function getRecipientDetails(
        int     $siteId,
        int     $page = 1,
        int     $perPage = 20,
        ?string $sortBy = null,
        string  $sortDirection = 'desc',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $search = null
    ): array
    {
        [$newsletters, $sends, $sendIds] = $this->buildNewsletterAndSendMaps($siteId);

        if (empty($sendIds)) {
            return $this->emptyPage($perPage, $page);
        }

        $recipients = $this->newsletterSendRecipientRepository->getRecipientsBySendIds(
            $sendIds,
            null,
            $dateFrom,
            $dateTo
        );

        $rows = $recipients->map(function ($r) use ($newsletters, $sends) {
            $send = $sends->get($r->newsletter_send_id);
            $newsletter = $send ? $newsletters->get($send->newsletter_id) : null;

            return [
                'email' => $r->email,
                'status' => $r->status,
                'error_message' => $r->error_message,
                'attempts' => $r->attempts,
                'last_attempt_at' => $r->updated_at,
                'newsletter_title' => $newsletter?->title,
                'sent_at' => $send?->sent_at instanceof \DateTimeInterface
                    ? $send->sent_at->format('Y-m-d H:i:s')
                    : $send?->sent_at,
            ];
        });

        if ($search) {
            $lower = strtolower($search);
            $rows = $rows->filter(fn($r) => str_contains(strtolower((string)($r['email'] ?? '')), $lower) ||
                str_contains(strtolower((string)($r['error_message'] ?? '')), $lower)
            )->values();
        }

        $validSort = ['email', 'status', 'attempts', 'last_attempt_at'];
        $sortColumn = in_array($sortBy, $validSort) ? $sortBy : 'last_attempt_at';

        return $this->sortAndPaginate($rows, $sortColumn, $sortDirection, $page, $perPage);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Loads newsletters and sends for a site and returns three values:
     *   - newsletters keyed by id
     *   - sends keyed by id
     *   - send id list (empty array signals "nothing to query")
     */
    private function buildNewsletterAndSendMaps(int $siteId): array
    {
        $newsletters = $this->newsletterRepository->findBySite($siteId);
        $newsletterIds = $newsletters->pluck('id')->toArray();

        if (empty($newsletterIds)) {
            return [new Collection([]), new Collection([]), []];
        }

        $sends = $this->newsletterSendRepository->getSendsByNewsletterIds($newsletterIds);
        $sendIds = $sends->pluck('id')->toArray();

        return [$newsletters->keyBy('id'), $sends->keyBy('id'), $sendIds];
    }

    private function sortAndPaginate(Collection $rows, string $sortColumn, string $sortDirection, int $page, int $perPage): array
    {
        $sortDir = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
        $sorted = $rows->orderBy($sortColumn, $sortDir);
        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;

        return [
            'data' => $sorted->slice($offset, $perPage)->values()->toArray(),
            'pagination' => $this->buildPagination($total, $perPage, $page, $offset),
        ];
    }

    private function buildPagination(int $total, int $perPage, int $page, int $offset): array
    {
        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $perPage > 0 ? (int)ceil($total / $perPage) : 1,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + $perPage, $total),
        ];
    }

    private function emptyPage(int $perPage, int $page): array
    {
        return ['data' => [], 'pagination' => $this->buildPagination(0, $perPage, $page, 0)];
    }

    private function sentOnOrAfter(object $send, string $date): bool
    {
        if (!$send->sent_at) {
            return false;
        }
        $d = is_string($send->sent_at) ? substr($send->sent_at, 0, 10) : $send->sent_at->format('Y-m-d');
        return $d >= $date;
    }

    private function sentOnOrBefore(object $send, string $date): bool
    {
        if (!$send->sent_at) {
            return false;
        }
        $d = is_string($send->sent_at) ? substr($send->sent_at, 0, 10) : $send->sent_at->format('Y-m-d');
        return $d <= $date;
    }
}