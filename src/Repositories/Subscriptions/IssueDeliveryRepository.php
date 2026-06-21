<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Orders\OrderLineStatus;
use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\Model;
use App\Repositories\Repository;
use App\Search\Configurations\IssueDeliverySearchConfiguration;
use App\Search\PaginatedResult;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;
use InvalidArgumentException;

class IssueDeliveryRepository extends Repository
{
    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $engine = new SearchEngine(new IssueDeliverySearchConfiguration());

        return $engine->search(IssueDelivery::query(), $criteria);
    }
    /**
     * Get upcoming deliveries for a subscription
     */
    public function getUpcomingDeliveries(int $subscriptionId, int $limit = 12): Collection
    {
        return IssueDelivery::where('subscription_id', $subscriptionId)
            ->where('estimated_delivery_date', '>=', date('Y-m-d'))
            ->orderBy('estimated_delivery_date', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all deliveries for a subscription
     */
    public function getAllDeliveries(int $subscriptionId): Collection
    {
        return IssueDelivery::where('subscription_id', $subscriptionId)
            ->orderBy('estimated_delivery_date', 'asc')
            ->get();
    }

    /**
     * Get past deliveries
     */
    public function getPastDeliveries(int $subscriptionId, int $limit = 6): Collection
    {
        return IssueDelivery::where('subscription_id', $subscriptionId)
            ->where('estimated_delivery_date', '<', date('Y-m-d'))
            ->orderBy('estimated_delivery_date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create delivery schedule for subscription
     */
    public function generateDeliverySchedule(
        int       $subscriptionId,
        \DateTime $startDate,
        \DateTime $endDate,
        string    $frequency = 'monthly',
        int       $transitDays = 5
    ): array
    {
        $deliveries = [];
        $current = clone $startDate;
        $issueNumber = 1;

        while ($current <= $endDate) {
            $onSaleDate = clone $current;
            $deliveryDate = (clone $current)->modify("+{$transitDays} days");

            $delivery = $this->create([
                'subscription_id' => $subscriptionId,
                'issue_number' => $issueNumber,
                'issue_title' => "Issue #{$issueNumber}",
                'on_sale_date' => $onSaleDate->format('Y-m-d H:i:s'),
                'estimated_delivery_date' => $deliveryDate->format('Y-m-d H:i:s'),
                'status' => 'Scheduled',
                'metadata' => [
                    'frequency' => $frequency,
                    'transit_days' => $transitDays
                ]
            ]);

            $deliveries[] = $delivery;
            $issueNumber++;

            // Increment based on frequency
            match ($frequency) {
                'weekly' => $current->modify('+1 week'),
                'biweekly' => $current->modify('+2 weeks'),
                'monthly' => $current->modify('+1 month'),
                'quarterly' => $current->modify('+3 months'),
                default => $current->modify('+1 month')
            };
        }

        return $deliveries;
    }

    /**
     * Update delivery status
     */
    public function updateDeliveryStatus(int $deliveryId, string $status, ?array $trackingInfo = null): ?IssueDelivery
    {
        $data = ['status' => $status];

        if ($trackingInfo) {
            $data['tracking_info'] = $trackingInfo;
        }

        return $this->update($deliveryId, $data);
    }

    public function bulkCreateFromCsv(int $siteId, array $rows): array
    {
        $created = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            try {
                $this->validateCsvRow($row);

                $schedule = $this->create([
                    'site_id' => $siteId,
                    'product_id' => $row['product_id'] ?? null,
                    'promotion_id' => $row['promotion_id'] ?? null,
                    'title' => $row['title'],
                    'issue_number' => $row['issue_number'],
                    'issue_code' => $row['issue_code'] ?? null,
                    'on_sale_date' => $row['on_sale_date'],
                    'cut_off_date' => $row['cut_off_date'] ?? null,
                    'fulfilment_date' => $row['fulfilment_date'] ?? null,
                    'status' => $row['status'] ?? IssueScheduleStatus::DRAFT->value,
                    'metadata' => $row['metadata'] ?? []
                ]);

                $created[] = $schedule;
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
            }
        }

        return [
            'created' => $created,
            'errors' => $errors,
            'total' => count($rows),
            'success_count' => count($created),
            'error_count' => count($errors)
        ];
    }

    private function validateCsvRow(array $row): void
    {
        if (empty($row['title'])) {
            throw new InvalidArgumentException('Title is required');
        }

        if (empty($row['issue_number'])) {
            throw new InvalidArgumentException('Issue number is required');
        }

        if (empty($row['on_sale_date'])) {
            throw new InvalidArgumentException('On-sale date is required');
        }

        if (!empty($row['status']) && !IssueScheduleStatus::tryFrom($row['status'])) {
            throw new InvalidArgumentException('Invalid status value');
        }
    }

    public function getAllForSite(int $siteId): Collection
    {
        return IssueDelivery::where('site_id', $siteId)
            ->orderBy('on_sale_date', 'desc')
            ->get();
    }

    public function delete(int $id): bool
    {
        $schedule = $this->find($id);

        if (!$schedule) {
            return false;
        }

        $hasDeliveries = IssueDelivery::where('subscription_id', $schedule->subscription_id)
            ->where('id', '!=', $schedule->id)
            ->exists();

        if ($hasDeliveries) {
            throw new \Exception('Cannot delete schedule with existing deliveries');
        }

        return parent::delete($id);
    }

    public function findFutureIssuesForPlanStartingFromIssue(
        int $subscriptionPlanId,
        int $startingIssueDeliveryId,
        int $limit,
    ): Collection {
        $startingIssue = IssueDelivery::where('id', $startingIssueDeliveryId)
            ->where('subscription_plan_id', $subscriptionPlanId)
            ->where('status', IssueScheduleStatus::ACTIVE->value)
            ->first();

        if (!$startingIssue) {
            return new Collection([]);
        }

        return IssueDelivery::where('subscription_plan_id', $subscriptionPlanId)
            ->where('status', IssueScheduleStatus::ACTIVE->value)
            ->where('on_sale_date', '>=', $startingIssue->on_sale_date instanceof \DateTimeInterface
                ? $startingIssue->on_sale_date->format('Y-m-d H:i:s')
                : (string) $startingIssue->on_sale_date
            )
            ->orderBy('on_sale_date', 'asc')
            ->orderBy('issue_number', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find active future issue/edition schedule rows for a subscription plan.
     *
     * These are plan-level schedule rows, not customer fulfilment rows.
     *
     * @return mixed Collection<IssueDelivery>
     */
    public function findAvailableEditionsForSubscriptionPlan(
        int $subscriptionPlanId,
        \DateTimeInterface $fromDate,
    ): Collection {
        return IssueDelivery::where('subscription_plan_id', $subscriptionPlanId)
            ->whereNull('subscription_id')
            ->where('status', IssueScheduleStatus::ACTIVE->value)
            ->where('on_sale_date', '>=', $fromDate->format('Y-m-d H:i:s'))
            ->orderBy('on_sale_date', 'asc')
            ->orderBy('issue_number', 'asc')
            ->get();
    }

    protected function getModelClass(): string
    {
        return IssueDelivery::class;
    }

    public function searchSchedulesPaginated(
        int   $siteId,
        array $filters,
        int   $page = 1,
        int   $perPage = 20
    ): PaginatedResult
    {
        $query = IssueDelivery::where('site_id', $siteId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['promotion_id'])) {
            $query->where('promotion_id', $filters['promotion_id']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('on_sale_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('on_sale_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('issue_title', 'like', "%{$filters['search']}%")
                    ->orWhere('issue_number', 'like', "%{$filters['search']}%")
                    ->orWhere('issue_code', 'like', "%{$filters['search']}%");
            });
        }

        $total = $query->count();
        $offset = ($page - 1) * $perPage;

        $data = $query->orderBy('on_sale_date', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return new PaginatedResult(
            data: $data->toArray(),
            total: $total,
            page: $page,
            perPage: $perPage,
        );
    }

    /**
     * Get the total quantity of pending pre-orders for a specific issue
     * This counts subscribers who have pre-ordered this issue but haven't received it yet
     */
    public function getPendingPreorderQuantity(int $issueId): int
    {
        return IssueDelivery::where('id', $issueId)
            ->where('status', OrderLineStatus::PENDING_PREORDER->value)
            ->count();
    }

    /**
     * Returns all active IssueDelivery records whose on_sale_date or
     * estimated_delivery_date is on or before $now.
     *
     * @return Collection<IssueDelivery>
     */
    public function findDueForDispatch(\DateTime $now): Collection
    {
        return IssueDelivery::where('status', IssueScheduleStatus::ACTIVE->value)
            ->where(function ($query) use ($now) {
                $query->where('on_sale_date', '<=', $now->format('Y-m-d H:i:s'))
                    ->orWhere('estimated_delivery_date', '<=', $now->format('Y-m-d H:i:s'));
            })
            ->get();
    }

    /**
     * Atomically decrement stock_quantity and return the refreshed IssueDelivery.
     *
     * Must be called inside an open transaction (caller-owned).
     *
     * @throws \App\Exceptions\Stock\StockException if the issue delivery is not found.
     */
    public function decrementStock(int $id, int $quantity): \App\Models\IssueDelivery
    {
        $issue = $this->lockForUpdate($id);

        if (!$issue) {
            throw \App\Exceptions\Stock\StockException::itemNotFound("IssueDelivery#{$id}");
        }

        $issue->decrementStock($quantity);

        return $issue;
    }

    /**
     * Atomically increment stock_quantity and return the refreshed IssueDelivery.
     *
     * Must be called inside an open transaction (caller-owned).
     *
     * @throws \App\Exceptions\Stock\StockException if the issue delivery is not found.
     */
    public function incrementStock(int $id, int $quantity): \App\Models\IssueDelivery
    {
        $issue = $this->lockForUpdate($id);

        if (!$issue) {
            throw \App\Exceptions\Stock\StockException::itemNotFound("IssueDelivery#{$id}");
        }

        $issue->incrementStock($quantity);

        return $issue;
    }

    public function findEligibleForPrintRun(int $siteId): Collection
    {
        return IssueDelivery::query()
            ->forSite($siteId)
            ->where('status', IssueScheduleStatus::ACTIVE->value)

            // Must have passed cut-off
            ->whereNotNull('cut_off_date')
            ->whereDate('cut_off_date', '<=', now())

            // Not already dispatched
            ->where(function ($q) {
                $q->whereNull('dispatched_at')
                    ->orWhere('status', '!=', IssueDeliveryStatus::DISPATCHED->value);
            })

            // Not cancelled
            ->where('status', '!=', IssueScheduleStatus::CANCELLED->value)

            // 🔥 Critical: avoid duplicates (no active print run)
            ->whereDoesntHave('printRuns', function ($q) {
                $q->whereNotIn('status', [
                    'cancelled',
                    'failed'
                ]);
            })
            ->orderBy('cut_off_date')
            ->get();
    }

    /**
     * Return a paginated list of issue deliveries for a subscription.
     *
     * Type classification logic (mirrors the previous PHP implementation):
     *   - delivered : a matching subscription_issue_fulfilments row exists and is_delivered = 1
     *   - missed    : estimated_delivery_date < NOW() and no delivered record
     *   - upcoming  : everything else
     *
     * The filter param maps directly to these types:
     *   all      → no type filter
     *   upcoming → type = upcoming
     *   previous → type = delivered
     *   missed   → type = missed
     *
     * Ordering:
     *   upcoming rows → estimated_delivery_date ASC
     *   delivered/missed rows → estimated_delivery_date DESC
     *   Combined via a sort_weight trick so upcoming appears first.
     *
     * @param int $planId
     * @param int $subscriptionId
     * @param string $filter all|upcoming|previous|missed
     * @param \DateTime|null $from
     * @param \DateTime|null $to
     * @param int $page
     * @param int $perPage
     *
     * @return array{ data: array, total: int, last_page: int }
     */
    public function getPaginatedForSubscription(
        int        $planId,
        int        $subscriptionId,
        string     $filter = 'all',
        ?\DateTime $from = null,
        ?\DateTime $to = null,
        int        $page = 1,
        int        $perPage = 15,
    ): array
    {

        // ── 1. Load delivered map (subscription-specific) ─────────────────────
        $delivered = Database::table('subscription_issue_fulfilments')
            ->where('subscription_id', $subscriptionId)
            ->get()
            ->keyBy('issue_delivery_id')
            ->toArray();

        // ── 2. Base query — NO limit/offset here ──────────────────────────────
        $query = IssueDelivery::query()
            ->where('subscription_plan_id', $planId)
            ->select([
                'id',
                'issue_number',
                'issue_title',
                'on_sale_date',
                'estimated_delivery_date',
                'status',
            ]);

        // ── 3. Date filters ────────────────────────────────────────────────────
        if ($from !== null) {
            $query->where(
                'estimated_delivery_date',
                '>=',
                $from->format('Y-m-d 00:00:00')
            );
        }

        if ($to !== null) {
            $query->where(
                'estimated_delivery_date',
                '<=',
                $to->format('Y-m-d 23:59:59')
            );
        }

        // ── 4. Fetch + classify ───────────────────────────────────────────────
        $now = new \DateTime();

        $rows = $query
            ->get()
            ->map(function ($row) use ($delivered, $now) {

                $delivery = $delivered[$row->id] ?? null;

                $isDelivered = $delivery !== null;

                $isMissed = !$isDelivered
                    && $row->estimated_delivery_date !== null
                    && $row->estimated_delivery_date < $now;

                $type = $isDelivered
                    ? 'delivered'
                    : ($isMissed ? 'missed' : 'upcoming');

                return [
                    'id' => $row->id,

                    'issue_number' => $row->issue_number,

                    'issue_title' => $row->issue_title,

                    'on_sale_date' => $row->on_sale_date
                        ? $row->on_sale_date->format('Y-m-d')
                        : null,

                    'estimated_delivery_date' => $row->estimated_delivery_date
                        ? $row->estimated_delivery_date->format('Y-m-d')
                        : null,

                    'status' => $row->status,

                    'type' => $type,

                    'delivered_at' => isset($delivery['delivered_at'])
                        ? (
                        $delivery['delivered_at'] instanceof \DateTimeInterface
                            ? $delivery['delivered_at']->format('Y-m-d H:i:s')
                            : $delivery['delivered_at']
                        )
                        : null,
                ];
            });

        // ── 5. Type filter ────────────────────────────────────────────────────
        $typeMap = [
            'upcoming' => 'upcoming',
            'previous' => 'delivered',
            'missed' => 'missed',
        ];

        if (isset($typeMap[$filter])) {
            $rows = $rows->filter(
                fn($row) => $row['type'] === $typeMap[$filter]
            );
        }

        // ── 6. Sort ────────────────────────────────────────────────────────────
        $rows = $rows->sort(function (array $a, array $b) {

            $dateA = $a['estimated_delivery_date'] ?? '';
            $dateB = $b['estimated_delivery_date'] ?? '';

            // upcoming vs upcoming → ascending
            if ($a['type'] === 'upcoming' && $b['type'] === 'upcoming') {
                return strcmp($dateA, $dateB);
            }

            // upcoming always first
            if ($a['type'] === 'upcoming') {
                return -1;
            }

            if ($b['type'] === 'upcoming') {
                return 1;
            }

            // delivered/missed → descending
            return strcmp($dateB, $dateA);
        });

        // ── 7. Totals ──────────────────────────────────────────────────────────
        $total = $rows->count();

        // ── 8. Pagination ──────────────────────────────────────────────────────
        $data = $rows
            ->values()
            ->slice(($page - 1) * $perPage, $perPage)
            ->all();

        return [
            'data' => $data,
            'total' => $total,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * All fulfilment rows for a subscription whose status is in $statuses.
     *
     * @param  string[] $statuses
     * @return IssueDelivery[]
     */
    public function getFutureDeliveriesForSubscription(int $subscriptionId, array $statuses): array
    {
        return IssueDelivery::where('subscription_id', $subscriptionId)
            ->whereIn('status', $statuses)
            ->get()
            ->all();
    }

    /**
     * Future issue rows for a plan.
     *
     * Edition changes resolve the current/old edition from the subscription's
     * current plan only. Do not scope by subscription_id here: the source of
     * truth for the edition is issue_deliveries.subscription_plan_id.
     *
     * @param  string[] $statuses
     * @return IssueDelivery[]
     */
    public function getFutureDeliveriesForPlan(
        int $subscriptionPlanId,
        array $statuses,
    ): array {
        return IssueDelivery::where('subscription_plan_id', $subscriptionPlanId)
            ->whereIn('status', $statuses)
            ->get()
            ->all();
    }

    /**
     * Find future active issue schedule rows for a subscription plan.
     *
     * These are the source schedule issues used when rebuilding a subscription's
     * future deliveries after changing edition/plan.
     *
     * @return Collection<IssueDelivery>
     */
    public function findFutureIssuesForPlan(
        int $subscriptionPlanId,
        \DateTimeInterface $fromDate,
        int $limit,
    ): Collection {
        return IssueDelivery::where('subscription_plan_id', $subscriptionPlanId)
            ->where('status', IssueScheduleStatus::ACTIVE->value)
            ->where('on_sale_date', '>=', $fromDate->format('Y-m-d H:i:s'))
            ->orderBy('on_sale_date', 'asc')
            ->orderBy('issue_number', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Find the current/next undispatched edition row for a subscription.
     *
     * This is used to audit the old edition before switching to a new edition.
     *
     * @return IssueDelivery|null
     */
    public function findCurrentOrNextForSubscription(int $subscriptionId): ?Model
    {
        return IssueDelivery::where('subscription_id', $subscriptionId)
            ->whereIn('status', [
                'pending',
                'scheduled',
                'not_dispatched',
            ])
            ->orderBy('on_sale_date', 'asc')
            ->orderBy('issue_number', 'asc')
            ->first();
    }

    /**
     * Bulk-update status for a set of fulfilment row IDs.
     *
     * @param int[]  $ids
     */
    public function supersedeManyByIds(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        IssueDelivery::whereIn('id', $ids)
            ->update(['status' => 'superseded']);
    }

    /**
     * Count of future fulfilment rows for a subscription.
     *
     * @param  string[] $futureStatuses
     */
    public function countFutureForSubscription(int $subscriptionId, array $futureStatuses): int
    {
        return IssueDelivery::where('subscription_id', $subscriptionId)
            ->whereIn('status', $futureStatuses)
            ->count();
    }

    /**
     * Next $limit schedule rows for an edition, ordered by on_sale_date ascending.
     * Schedule rows have subscription_plan_id set and subscription_id null.
     *
     * @param  string[] $scheduleStatuses  e.g. ['active', 'scheduled']
     * @return Collection<IssueDelivery>
     */
    public function getUpcomingScheduleIssues(
        int   $editionId,
        int   $limit,
        array $scheduleStatuses = ['active', 'scheduled'],
    ): Collection {
        return IssueDelivery::where('subscription_plan_id', $editionId)
            ->whereNull('subscription_id')
            ->whereIn('status', $scheduleStatuses)
            ->orderBy('on_sale_date', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create a single fulfilment row for a subscription, copied from a
     * schedule issue record.
     */
    public function createFulfilmentFromSchedule(
        int          $subscriptionId,
        int          $editionId,
        IssueDelivery $scheduleIssue,
    ): Model {
        $now = date('Y-m-d H:i:s');

        return IssueDelivery::create([
            'subscription_id'         => $subscriptionId,
            'subscription_plan_id'    => $editionId,
            'issue_number'            => $scheduleIssue->issue_number,
            'issue_title'             => $scheduleIssue->issue_title,
            'on_sale_date'            => $scheduleIssue->on_sale_date,
            'estimated_delivery_date' => $scheduleIssue->estimated_delivery_date,
            'status'                  => 'pending',
            'site_id'                 => $scheduleIssue->site_id,
            'issue_code'              => $scheduleIssue->issue_code,
            'cut_off_date'            => $scheduleIssue->cut_off_date,
            'created_at'              => $now,
            'updated_at'              => $now,
        ]);
    }
}
