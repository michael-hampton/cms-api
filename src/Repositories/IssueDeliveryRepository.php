<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\IssueDelivery;

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

    protected function getModelClass(): string
    {
        return IssueDelivery::class;
    }
}