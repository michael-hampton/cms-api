<?php

namespace App\Models;

class IssueDelivery extends Model
{
    protected $table = 'issue_deliveries';

    protected $fillable = [
        'subscription_id',
        'issue_number',
        'issue_title',
        'on_sale_date',
        'estimated_delivery_date',
        'status',
        'tracking_info',
        'metadata'
    ];

    protected $casts = [
        'on_sale_date' => 'datetime',
        'estimated_delivery_date' => 'datetime',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function subscription($relation = false)
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id', $relation);
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
}