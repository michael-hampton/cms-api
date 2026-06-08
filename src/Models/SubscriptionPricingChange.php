<?php

namespace App\Models;

use App\Enums\Subscriptions\SubscriptionPricingChangeStatus;

/**
 * Represents a scheduled price change for a subscription plan.
 *
 * Lifecycle:
 *   scheduled → notified → applied
 *              └──────────────────→ cancelled (at any point before applied)
 *
 * @property int $id
 * @property int $plan_id
 * @property float $old_price
 * @property float $new_price
 * @property string $currency
 * @property \DateTime $effective_date       Date the new price takes effect (≥ 30 days from created_at)
 * @property \DateTime $notice_sent_at       Null until all subscriber emails are dispatched
 * @property string $status               SubscriptionPricingChangeStatus value
 * @property string|null $reason               Optional internal reason (not shown to subscribers)
 * @property int $created_by           Admin user ID
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 */
class SubscriptionPricingChange extends Model
{
    protected $table = 'subscription_pricing_changes';

    protected $fillable = [
        'plan_id',
        'old_price',
        'new_price',
        'currency',
        'effective_date',
        'notice_sent_at',
        'status',
        'reason',
        'created_by',
        'requires_subscription_replacement',
        'itd_required',
        'itd_letter_code',
    ];

    protected $casts = [
        'old_price' => 'float',
        'new_price' => 'float',
        'effective_date' => 'datetime',
        'notice_sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'requires_subscription_replacement' => 'boolean',
        'itd_required' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function plan(bool $relation = false): mixed
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'id', $relation);
    }

    // ── State queries ─────────────────────────────────────────────────────

    public function isScheduled(): bool
    {
        return $this->status === SubscriptionPricingChangeStatus::Scheduled->value;
    }

    public function isNotified(): bool
    {
        return $this->status === SubscriptionPricingChangeStatus::Notified->value;
    }

    public function isApplied(): bool
    {
        return $this->status === SubscriptionPricingChangeStatus::Applied->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === SubscriptionPricingChangeStatus::Cancelled->value;
    }

    public function isPendingNotification(): bool
    {
        return $this->status === SubscriptionPricingChangeStatus::Scheduled->value
            && $this->notice_sent_at === null;
    }

    public function isDueToApply(): bool
    {
        if ($this->status !== SubscriptionPricingChangeStatus::Notified->value) {
            return false;
        }

        return $this->effective_date <= new \DateTime();
    }

    // ── Computed ──────────────────────────────────────────────────────────

    public function noticePeriodDays(): int
    {
        return (int)(new \DateTime())->diff($this->effective_date)->days;
    }

    public function priceIncrease(): float
    {
        return round($this->new_price - $this->old_price, 2);
    }

    public function priceIncreasePercentage(): float
    {
        if ($this->old_price == 0) {
            return 0;
        }

        return round((($this->new_price - $this->old_price) / $this->old_price) * 100, 1);
    }

    public function requiresSubscriptionReplacement(): bool
    {
        return (bool) $this->requires_subscription_replacement;
    }

    public function requiresItdNotification(): bool
    {
        return (bool) $this->itd_required;
    }

    public function itdLetterCode(): ?string
    {
        return $this->itd_letter_code;
    }

    public function isPriceIncrease(): bool
    {
        return $this->new_price > $this->old_price;
    }
}