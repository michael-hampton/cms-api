<?php

declare(strict_types=1);

namespace App\Repositories\Adverts;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;

/**
 * Reads engagement data (clicks, views) for a single merchant.
 *
 * All queries are read-only aggregations — no writes happen here.
 * Each method returns plain arrays or Collection so the service layer
 * can compose them without leaking query-builder objects upstream.
 */
class MerchantAnalyticsRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    // ─── OFFER CLICKS ──────────────────────────────────────────────────────────

    /**
     * Total offer click/render counts for a merchant, split by action.
     *
     * Returns: ['click' => int, 'render' => int, 'total' => int]
     */
    public function offerClickTotals(int $merchantId, int $days = 30): array
    {
        $rows = $this->database->table('offer_clicks as oc')
            ->join('product_offers as po', 'po.id', '=', 'oc.offer_id')
            ->where('po.merchant_id', $merchantId)
            ->where('oc.clicked_at', '>=', $this->cutoff($days))
            ->select(['oc.action'])
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('oc.action')
            ->get();

        $totals = ['click' => 0, 'render' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $action = $row['action'] ?? $row->action;
            $cnt = (int)($row['cnt'] ?? $row->cnt);
            if (isset($totals[$action])) {
                $totals[$action] = $cnt;
            }
            $totals['total'] += $cnt;
        }

        return $totals;
    }

    private function cutoff(int $days): string
    {
        return date('Y-m-d H:i:s', strtotime("-{$days} days"));
    }

    /**
     * Daily offer clicks for the last $days days.
     *
     * Returns Collection of ['date' => 'Y-m-d', 'clicks' => int, 'renders' => int]
     */
    public function offerClicksByDay(int $merchantId, int $days = 30): Collection
    {
        $rows = $this->database->table('offer_clicks as oc')
            ->join('product_offers as po', 'po.id', '=', 'oc.offer_id')
            ->where('po.merchant_id', $merchantId)
            ->where('oc.clicked_at', '>=', $this->cutoff($days))
            ->selectRaw("DATE(oc.clicked_at) as date, oc.action, COUNT(*) as cnt")
            ->groupByRaw('DATE(oc.clicked_at), oc.action')
            ->orderBy('date')
            ->get();

        return $this->pivotDailyActionRows($rows, $days);
    }

    // ─── DEAL CLICKS ───────────────────────────────────────────────────────────

    /**
     * Pivot raw [date, action, cnt] rows into a zero-filled daily series
     * with 'clicks' and 'renders' keys.
     */
    private function pivotDailyActionRows(iterable $rows, int $days): Collection
    {
        $series = $this->buildDateSeries($days, ['clicks' => 0, 'renders' => 0]);

        foreach ($rows as $row) {
            $date = $row['date'] ?? $row->date;
            $action = $row['action'] ?? $row->action;
            $cnt = (int)($row['cnt'] ?? $row->cnt ?? 0);

            if (!isset($series[$date])) {
                continue;
            }

            if ($action === 'click') {
                $series[$date]['clicks'] = $cnt;
            } elseif ($action === 'render') {
                $series[$date]['renders'] = $cnt;
            }
        }

        return collect(array_values($series));
    }

    /**
     * Build an associative [date => [...defaults]] map for every day in range.
     */
    private function buildDateSeries(int $days, array $defaults = []): array
    {
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $series[$date] = array_merge(['date' => $date], $defaults);
        }
        return $series;
    }

    /**
     * Per-offer click summary for a merchant.
     *
     * Returns Collection of ['offer_id', 'product_name', 'clicks', 'renders', 'ctr']
     */
    public function offerClicksByOffer(int $merchantId, int $days = 30): Collection
    {
        $rows = $this->database->table('offer_clicks as oc')
            ->join('product_offers as po', 'po.id', '=', 'oc.offer_id')
            ->join('products as p', 'p.id', '=', 'po.product_id')
            ->where('po.merchant_id', $merchantId)
            ->where('oc.clicked_at', '>=', $this->cutoff($days))
            ->selectRaw("
                po.id            AS offer_id,
                p.name           AS product_name,
                SUM(CASE WHEN oc.action = 'click'  THEN 1 ELSE 0 END) AS clicks,
                SUM(CASE WHEN oc.action = 'render' THEN 1 ELSE 0 END) AS renders
            ")
            ->groupBy('po.id', 'p.name')
            ->orderByRaw('clicks DESC')
            ->limit(20)
            ->get();

        return $rows->map(function ($row) {
            $clicks = (int)($row['clicks'] ?? $row->clicks ?? 0);
            $renders = (int)($row['renders'] ?? $row->renders ?? 0);

            return [
                'offer_id' => (int)($row['offer_id'] ?? $row->offer_id),
                'product_name' => $row['product_name'] ?? $row->product_name ?? '—',
                'clicks' => $clicks,
                'renders' => $renders,
                'ctr' => $renders > 0 ? round(($clicks / $renders) * 100, 1) : 0.0,
            ];
        });
    }

    // ─── PRODUCT VIEWS ─────────────────────────────────────────────────────────

    /**
     * Total deal click/render counts for a merchant's products.
     */
    public function dealClickTotals(int $merchantId, int $days = 30): array
    {
        $rows = $this->database->table('deal_clicks as dc')
            ->join('product_merchants as pm', 'pm.product_id', '=', 'dc.product_id')
            ->where('pm.merchant_id', $merchantId)
            ->where('dc.created_at', '>=', $this->cutoff($days))
            ->select(['dc.action'])
            ->selectRaw('COUNT(*) as cnt')
            ->groupBy('dc.action')
            ->get();

        $totals = ['click' => 0, 'render' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $action = $row['action'] ?? $row->action;
            $cnt = (int)($row['cnt'] ?? $row->cnt);
            if (isset($totals[$action])) {
                $totals[$action] = $cnt;
            }
            $totals['total'] += $cnt;
        }

        return $totals;
    }

    /**
     * Daily deal clicks for the last $days days.
     */
    public function dealClicksByDay(int $merchantId, int $days = 30): Collection
    {
        $rows = $this->database->table('deal_clicks as dc')
            ->join('product_merchants as pm', 'pm.product_id', '=', 'dc.product_id')
            ->where('pm.merchant_id', $merchantId)
            ->where('dc.created_at', '>=', $this->cutoff($days))
            ->selectRaw("DATE(dc.created_at) as date, dc.action, COUNT(*) as cnt")
            ->groupByRaw('DATE(dc.created_at), dc.action')
            ->orderBy('date')
            ->get();

        return $this->pivotDailyActionRows($rows, $days);
    }

    /**
     * Per-product deal click summary.
     *
     * Returns Collection of ['product_id', 'product_name', 'clicks', 'renders', 'ctr']
     */
    public function dealClicksByProduct(int $merchantId, int $days = 30): Collection
    {
        $rows = $this->database->table('deal_clicks as dc')
            ->join('product_merchants as pm', 'pm.product_id', '=', 'dc.product_id')
            ->join('products as p', 'p.id', '=', 'dc.product_id')
            ->where('pm.merchant_id', $merchantId)
            ->where('dc.created_at', '>=', $this->cutoff($days))
            ->selectRaw("
                p.id   AS product_id,
                p.name AS product_name,
                SUM(CASE WHEN dc.action = 'click'  THEN 1 ELSE 0 END) AS clicks,
                SUM(CASE WHEN dc.action = 'render' THEN 1 ELSE 0 END) AS renders
            ")
            ->groupBy('p.id', 'p.name')
            ->orderByRaw('clicks DESC')
            ->limit(20)
            ->get();

        return $rows->map(function ($row) {
            $clicks = (int)($row['clicks'] ?? $row->clicks ?? 0);
            $renders = (int)($row['renders'] ?? $row->renders ?? 0);

            return [
                'product_id' => (int)($row['product_id'] ?? $row->product_id),
                'product_name' => $row['product_name'] ?? $row->product_name ?? '—',
                'clicks' => $clicks,
                'renders' => $renders,
                'ctr' => $renders > 0 ? round(($clicks / $renders) * 100, 1) : 0.0,
            ];
        });
    }

    // ─── PERIOD COMPARISON ─────────────────────────────────────────────────────

    /**
     * Total product view count for a merchant's products.
     */
    public function productViewTotals(int $merchantId, int $days = 30): array
    {
        $row = $this->database->table('product_views as pv')
            ->join('product_merchants as pm', 'pm.product_id', '=', 'pv.product_id')
            ->where('pm.merchant_id', $merchantId)
            ->where('pv.viewed_at', '>=', $this->cutoff($days))
            ->selectRaw('COUNT(*) as total, COUNT(DISTINCT pv.user_id) as unique_users')
            ->first();

        return [
            'total' => (int)($row['total'] ?? $row->total ?? 0),
            'unique_users' => (int)($row['unique_users'] ?? $row->unique_users ?? 0),
        ];
    }

    // ─── PRIVATE HELPERS ───────────────────────────────────────────────────────

    /**
     * Daily product views for the last $days days.
     *
     * Returns Collection of ['date' => 'Y-m-d', 'views' => int, 'unique' => int]
     */
    public function productViewsByDay(int $merchantId, int $days = 30): Collection
    {
        $rows = $this->database->table('product_views as pv')
            ->join('product_merchants as pm', 'pm.product_id', '=', 'pv.product_id')
            ->where('pm.merchant_id', $merchantId)
            ->where('pv.viewed_at', '>=', $this->cutoff($days))
            ->selectRaw("DATE(pv.viewed_at) as date, COUNT(*) as views, COUNT(DISTINCT pv.user_id) as unique_users")
            ->groupByRaw('DATE(pv.viewed_at)')
            ->orderBy('date')
            ->get();

        // Build a zero-filled series for every day in range.
        // Keys must be initialised here so that days with no data still
        // serialise as {"date":"…","views":0,"unique":0} — not {date:"…"}.
        $series = $this->buildDateSeries($days, ['views' => 0, 'unique' => 0]);

        foreach ($rows as $row) {
            $date = $row['date'] ?? $row->date;
            if (isset($series[$date])) {
                $series[$date]['views'] = (int)($row['views'] ?? $row->views ?? 0);
                $series[$date]['unique'] = (int)($row['unique_users'] ?? $row->unique_users ?? 0);
            }
        }

        return collect(array_values($series));
    }

    /**
     * Per-product view summary for a merchant.
     *
     * Returns Collection of ['product_id', 'product_name', 'views', 'unique_users']
     */
    public function productViewsByProduct(int $merchantId, int $days = 30): Collection
    {
        $rows = $this->database->table('product_views as pv')
            ->join('product_merchants as pm', 'pm.product_id', '=', 'pv.product_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('pm.merchant_id', $merchantId)
            ->where('pv.viewed_at', '>=', $this->cutoff($days))
            ->selectRaw("
                p.id   AS product_id,
                p.name AS product_name,
                COUNT(*) AS views,
                COUNT(DISTINCT pv.user_id) AS unique_users
            ")
            ->groupBy('p.id', 'p.name')
            ->orderByRaw('views DESC')
            ->limit(20)
            ->get();

        return $rows->map(fn($row) => [
            'product_id' => (int)($row['product_id'] ?? $row->product_id),
            'product_name' => $row['product_name'] ?? $row->product_name ?? '—',
            'views' => (int)($row['views'] ?? $row->views ?? 0),
            'unique_users' => (int)($row['unique_users'] ?? $row->unique_users ?? 0),
        ]);
    }

    /**
     * Compare totals for current period vs prior period of equal length.
     * Returns delta percentages for the Overview stat cards.
     */
    public function periodComparison(int $merchantId, int $days = 30): array
    {
        $currentStart = $this->cutoff($days);
        $previousStart = $this->cutoff($days * 2);

        $current = $this->aggregateTotalsInRange($merchantId, $currentStart, 'now');
        $previous = $this->aggregateTotalsInRange($merchantId, $previousStart, $currentStart);

        return [
            'current' => $current,
            'previous' => $previous,
            'deltas' => [
                'offer_clicks' => $this->pctDelta($previous['offer_clicks'], $current['offer_clicks']),
                'deal_clicks' => $this->pctDelta($previous['deal_clicks'], $current['deal_clicks']),
                'product_views' => $this->pctDelta($previous['product_views'], $current['product_views']),
            ],
        ];
    }

    private function aggregateTotalsInRange(int $merchantId, string $from, string $to): array
    {
        $toClause = $to === 'now' ? date('Y-m-d H:i:s') : $to;

        // Offer clicks
        $offerClicks = (int)$this->database->table('offer_clicks as oc')
            ->join('product_offers as po', 'po.id', '=', 'oc.offer_id')
            ->where('po.merchant_id', $merchantId)
            ->where('oc.action', 'click')
            ->where('oc.clicked_at', '>=', $from)
            ->where('oc.clicked_at', '<', $toClause)
            ->count();

        // Deal clicks
        $dealClicks = (int)$this->database->table('deal_clicks as dc')
            ->join('product_merchants as pm', 'pm.product_id', '=', 'dc.product_id')
            ->where('pm.merchant_id', $merchantId)
            ->where('dc.action', 'click')
            ->where('dc.created_at', '>=', $from)
            ->where('dc.created_at', '<', $toClause)
            ->count();

        // Product views
        $productViews = (int)$this->database->table('product_views as pv')
            ->join('product_merchants as pm', 'pm.product_id', '=', 'pv.product_id')
            ->where('pm.merchant_id', $merchantId)
            ->where('pv.viewed_at', '>=', $from)
            ->where('pv.viewed_at', '<', $toClause)
            ->count();

        return [
            'offer_clicks' => $offerClicks,
            'deal_clicks' => $dealClicks,
            'product_views' => $productViews,
        ];
    }

    private function pctDelta(int|float $previous, int|float $current): float
    {
        if ($previous == 0) {
            return 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}