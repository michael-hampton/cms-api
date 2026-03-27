<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Orders\OrderLineStatus;
use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
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

}