<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Repositories\Repository;
use App\Search\PaginatedResult;

class IssueDeliveryRepository extends Repository
{
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
            throw new \InvalidArgumentException('Title is required');
        }

        if (empty($row['issue_number'])) {
            throw new \InvalidArgumentException('Issue number is required');
        }

        if (empty($row['on_sale_date'])) {
            throw new \InvalidArgumentException('On-sale date is required');
        }

        if (!empty($row['status']) && !IssueScheduleStatus::tryFrom($row['status'])) {
            throw new \InvalidArgumentException('Invalid status value');
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
        $lastPage = (int)ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;

        $data = $query->orderBy('on_sale_date', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return new PaginatedResult(
            data: $data->toArray(),
            total: $total,
            page: $page,
            perPage: $perPage
        );
    }

}