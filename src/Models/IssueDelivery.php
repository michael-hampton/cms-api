<?php

namespace App\Models;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Services\Billing\Preorder\Contracts\AvailabilityPolicyInterface;
use App\Services\Billing\Preorder\IssueAvailabilityPolicy;

class IssueDelivery extends Model
{
    protected $table = 'issue_deliveries';

    protected $fillable = [
        'subscription_id',
        'subscription_plan_id',
        'issue_number',
        'issue_title',
        'on_sale_date',
        'estimated_delivery_date',
        'status',
        'tracking_info',
        'metadata',
        'promotion_id',
        'cut_off_date',
        'fulfilment_date',
        'site_id',
        'issue_code',
        'stock_quantity',
        'preorder_enabled',
        'restock_date',
        'dispatched_at',
        'dispatched_failed_at'
    ];

    protected $casts = [
        'on_sale_date' => 'datetime',
        'estimated_delivery_date' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'cut_off_date' => 'date',
        'fulfilment_date' => 'date',
        'status' => 'string',
        'tracking_info' => 'array',
        'restock_date' => 'datetime',
    ];

    public function subscription($relation = false)
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id', $relation);
    }

    public function subscriptionPlans()
    {
        return $this->hasMany(SubscriptionPlan::class, 'id', 'subscription_plan_id');
    }

    /**
     * Get human-readable status
     */
    public function getStatusLabel(): string
    {
        return match ($this->calculateStatus()) {
            'Scheduled' => '📅 Scheduled',
            'In Transit' => '🚚 In Transit',
            'Delivered' => '✓ Delivered',
            default => $this->calculateStatus()
        };
    }

    /**
     * Calculate delivery status based on dates
     */
    public function calculateStatus(): string
    {
        $now = new \DateTime();

        // If we have explicit tracking info
        if ($this->tracking_info && isset($this->tracking_info['status'])) {
            return $this->tracking_info['status'];
        }

        // Calculate based on dates
        if (!$this->on_sale_date) {
            return 'Scheduled';
        }

        $onSaleDate = $this->on_sale_date;
        $estimatedDelivery = $this->estimated_delivery_date;

        if ($now < $onSaleDate) {
            return 'Scheduled';
        }

        if ($now >= $onSaleDate && $now < $estimatedDelivery) {
            return 'In Transit';
        }

        if ($now >= $estimatedDelivery) {
            return 'Delivered';
        }

        return 'Scheduled';
    }

    /**
     * Check if issue is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->estimated_delivery_date > new \DateTime();
    }

    /**
     * Check if issue is overdue
     */
    public function isOverdue(): bool
    {
        $status = $this->calculateStatus();
        $now = new \DateTime();

        return $status !== 'Delivered' &&
            $this->estimated_delivery_date < $now->modify('-7 days');
    }

    public function isActive(): bool
    {
        return $this->status === IssueScheduleStatus::ACTIVE->value;
    }

    public function isDraft(): bool
    {
        return $this->status === IssueScheduleStatus::DRAFT->value;
    }

    public function isDispatched(): bool
    {
        return $this->status === IssueDeliveryStatus::DISPATCHED->value;
    }

    public function isFailed(): bool
    {
        return $this->status === IssueDeliveryStatus::FAILED->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === IssueScheduleStatus::CANCELLED->value;
    }

    public function scopeActive($query)
    {
        return $query->where('status', IssueScheduleStatus::ACTIVE->value);
    }

    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Get the availability policy for this issue
     */
    public function availabilityPolicy(): AvailabilityPolicyInterface
    {
        return new IssueAvailabilityPolicy($this);
    }

    // =========================================================================
    // Dispatch outcome recording
    // =========================================================================

    public function markDispatched(): void
    {
        $this->update([
            'status' => IssueDeliveryStatus::DISPATCHED->value,
            'dispatched_at' => now(),
            'dispatch_failed_at' => null,
            'dispatch_error' => null,
        ]);
    }

    public function markDispatchFailed(string $error): void
    {
        $this->update([
            'status' => IssueDeliveryStatus::FAILED->value,
            'dispatch_failed_at' => now(),
            'dispatch_error' => $error,
        ]);
    }
}