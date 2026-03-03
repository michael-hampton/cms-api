<?php

namespace App\Services\Newsletter;

use App\Framework\Database\Database;
use App\Models\NewsletterSend;
use App\Models\NewsletterSendPageView;
use App\Models\NewsletterSendRecipient;
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
        $query = NewsletterSendPageView::query()
            ->select([
                'nspv.email',
                'p.id as page_id',
                'p.title as page_title',
                'p.slug as page_slug',
                'nspv.clicked_at',
                'n.id as newsletter_id',
                'n.title as newsletter_title',
                'ns.sent_at',
                'nspv.ip_address',
                'nspv.user_agent'
            ])
            ->from('newsletter_send_page_views as nspv')
            ->join('newsletter_sends as ns', 'nspv.newsletter_send_id', '=', 'ns.id')
            ->join('newsletters as n', 'ns.newsletter_id', '=', 'n.id')
            ->join('pages as p', 'nspv.page_id', '=', 'p.id')
            ->where('n.site_id', $siteId);

        if ($dateFrom) {
            $query->whereDate('nspv.clicked_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('nspv.clicked_at', '<=', $dateTo);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nspv.email', 'like', "%{$search}%")
                    ->orWhere('p.title', 'like', "%{$search}%")
                    ->orWhere('n.title', 'like', "%{$search}%");
            });
        }

        $total = $query->count();

        $validSortColumns = ['email', 'page_title', 'newsletter_title', 'clicked_at'];

        $sortColumn = in_array($sortBy, $validSortColumns)
            ? $sortBy
            : 'clicked_at';

        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc'])
            ? $sortDirection
            : 'desc';

        $offset = ($page - 1) * $perPage;

        $clicks = $query
            ->orderBy(
                match ($sortColumn) {
                    'page_title' => 'p.title',
                    'newsletter_title' => 'n.title',
                    default => "nspv.{$sortColumn}"
                },
                $sortDir
            )
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $clicks->toArray(),
            'pagination' => $this->buildPagination($total, $perPage, $page, $offset)
        ];
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
        $query = NewsletterSendRecipient::query()
            ->select([
                'nsr.id as recipient_id',
                'nsr.email',
                'nsr.error_message',
                'nsr.attempts',
                'nsr.updated_at as failed_at',
                'n.id as newsletter_id',
                'n.title as newsletter_title',
                'ns.sent_at'
            ])
            ->from('newsletter_send_recipients as nsr')
            ->join('newsletter_sends as ns', 'nsr.newsletter_send_id', '=', 'ns.id')
            ->join('newsletters as n', 'ns.newsletter_id', '=', 'n.id')
            ->where('n.site_id', $siteId)
            ->where('nsr.status', 'failed');

        if ($dateFrom) {
            $query->whereDate('nsr.updated_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('nsr.updated_at', '<=', $dateTo);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nsr.email', 'like', "%{$search}%")
                    ->orWhere('n.title', 'like', "%{$search}%")
                    ->orWhere('nsr.error_message', 'like', "%{$search}%");
            });
        }

        $total = $query->count();

        $validSortColumns = ['email', 'newsletter_title', 'attempts', 'failed_at'];

        $sortColumn = in_array($sortBy, $validSortColumns)
            ? $sortBy
            : 'failed_at';

        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc'])
            ? $sortDirection
            : 'desc';

        $offset = ($page - 1) * $perPage;

        $failedSends = $query
            ->orderBy(
                match ($sortColumn) {
                    'newsletter_title' => 'n.title',
                    'failed_at' => 'nsr.updated_at',
                    default => "nsr.{$sortColumn}"
                },
                $sortDir
            )
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $failedSends->toArray(),
            'pagination' => $this->buildPagination($total, $perPage, $page, $offset)
        ];
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
        $query = NewsletterSendPageView::query()
            ->from('newsletter_send_page_views as nspv')
            ->join('newsletter_sends as ns', 'nspv.newsletter_send_id', '=', 'ns.id')
            ->join('newsletters as n', 'ns.newsletter_id', '=', 'n.id')
            ->where('n.site_id', $siteId)
            ->select([
                'nspv.email',
                Database::raw('COUNT(*) as click_count'),
                Database::raw('MAX(nspv.clicked_at) as last_clicked_at')
            ])
            ->groupBy('nspv.email');

        if ($dateFrom) {
            $query->whereDate('nspv.clicked_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('nspv.clicked_at', '<=', $dateTo);
        }

        if ($search) {
            $query->having('nspv.email', 'like', "%{$search}%");
        }

        $total = $query->count();

        $validSortColumns = ['email', 'click_count', 'last_clicked_at'];

        $sortColumn = in_array($sortBy, $validSortColumns) ? $sortBy : 'click_count';
        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $offset = ($page - 1) * $perPage;

        $data = $query
            ->orderBy($sortColumn, $sortDir)
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $data->toArray(),
            'pagination' => $this->buildPagination($total, $perPage, $page, $offset)
        ];
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
        $query = NewsletterSend::query()
            ->from('newsletter_sends as ns')
            ->join('newsletters as n', 'ns.newsletter_id', '=', 'n.id')
            ->where('n.site_id', $siteId)
            ->select([
                'ns.id as send_id',
                'n.title as newsletter_title',
                'ns.sent_at',
                'ns.total_recipients',
                'ns.success_count',
                'ns.failed_count'
            ]);

        if ($dateFrom) {
            $query->whereDate('ns.sent_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('ns.sent_at', '<=', $dateTo);
        }

        if ($search) {
            $query->where('n.title', 'like', "%{$search}%");
        }

        $total = $query->count();

        $validSortColumns = ['newsletter_title', 'sent_at', 'success_count', 'failed_count'];

        $sortColumn = in_array($sortBy, $validSortColumns) ? $sortBy : 'sent_at';
        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $offset = ($page - 1) * $perPage;

        $data = $query
            ->orderBy(
                match ($sortColumn) {
                    'newsletter_title' => 'n.title',
                    default => "ns.{$sortColumn}"
                },
                $sortDir
            )
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $data->toArray(),
            'pagination' => $this->buildPagination($total, $perPage, $page, $offset)
        ];
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
        $query = NewsletterSendRecipient::query()
            ->from('newsletter_send_recipients as nsr')
            ->join('newsletter_sends as ns', 'nsr.newsletter_send_id', '=', 'ns.id')
            ->join('newsletters as n', 'ns.newsletter_id', '=', 'n.id')
            ->where('n.site_id', $siteId)
            ->select([
                'nsr.email',
                'nsr.status',
                'nsr.error_message',
                'nsr.attempts',
                'nsr.updated_at as last_attempt_at'
            ]);

        if ($dateFrom) {
            $query->whereDate('nsr.updated_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('nsr.updated_at', '<=', $dateTo);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nsr.email', 'like', "%{$search}%")
                    ->orWhere('nsr.error_message', 'like', "%{$search}%");
            });
        }

        $total = $query->count();

        $validSortColumns = ['email', 'status', 'attempts', 'last_attempt_at'];

        $sortColumn = in_array($sortBy, $validSortColumns) ? $sortBy : 'last_attempt_at';
        $sortDir = in_array(strtolower($sortDirection), ['asc', 'desc']) ? $sortDirection : 'desc';

        $offset = ($page - 1) * $perPage;

        $data = $query
            ->orderBy(
                match ($sortColumn) {
                    'last_attempt_at' => 'nsr.updated_at',
                    default => "nsr.{$sortColumn}"
                },
                $sortDir
            )
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $data->toArray(),
            'pagination' => $this->buildPagination($total, $perPage, $page, $offset)
        ];
    }

    private function emptyPagination(int $perPage, int $page): array
    {
        return [
            'total' => 0,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => 1,
            'from' => 0,
            'to' => 0
        ];
    }

    private function buildPagination(int $total, int $perPage, int $page, int $offset): array
    {
        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int)ceil($total / $perPage),
            'from' => $offset + 1,
            'to' => min($offset + $perPage, $total)
        ];
    }
}