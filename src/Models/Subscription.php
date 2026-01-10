<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class Subscription extends Model
{
    protected static function boot()
    {
        parent::boot();

        // Create window when paid subscription is created
        static::created(function ($subscription) {
            if ($subscription->type === 'paid') {
                $subscription->createWindow();
            }
        });

        // Update window when subscription status changes
        static::updated(function ($subscription) {
            if ($subscription->type === 'paid') {
                $subscription->updateWindow();
            }
        });
    }

    protected $table = 'subscriptions';

    protected $fillable = [
        'member_id',
        'site_id',
        'plan_name',
        'status',
        'start_date',
        'end_date',
        'auto_renew',
        'price',
        'currency',
        'plan_id',
        'next_billing_date',
        'last_payment_date',
        'payment_intent_id',
        'payment_subscription_id',
        'voucher_id',
        'discount_amount',
        'original_price',
        'delivery_type',
        'download_url',
        'download_expires_at',
        'type',
        'delivery_paused',
        'delivery_pause_start',
        'delivery_pause_end',
        'delivery_pause_reason',
        'cancelled_at',
        'current_period_start',
        'current_period_end',
        'includes_digital_access',
        'upgraded_from_plan_id',
        'upgraded_at',
        'upgrade_price_difference',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'auto_renew' => 'boolean',
        'price' => 'float',
        'next_billing_date' => 'datetime',
        'last_payment_date' => 'datetime',
        'download_expires_at' => 'datetime',
        'delivery_paused' => 'boolean',
        'delivery_pause_start' => 'datetime',
        'delivery_pause_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'includes_digital_access' => 'boolean',
        'upgraded_at' => 'datetime',
        'upgrade_price_difference' => 'float',
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function isActive(): bool
    {
        // A subscription is only active if:
        // 1. Status is 'active'
        // 2. AND (no end_date OR end_date is in the future)
        // 3. AND (no start_date OR start_date is in the past or today)
        if ($this->status !== 'active') {
            return false;
        }

        $now = new \DateTime();

        // Check if subscription has started
        if ($this->start_date && $this->start_date > $now) {
            return false;
        }

        // Check if subscription has ended
        if ($this->end_date && $this->end_date < $now) {
            return false;
        }

        return true;

    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' ||
            ($this->end_date !== null && $this->end_date <= new \DateTime());
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'active');
    }

    public function scopeByMember(QueryBuilder $query, int $memberId): QueryBuilder
    {
        return $query->where('member_id', $memberId);
    }

    public function plan($relation = false)
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'id', $relation);
    }

    public function scopeByPlan(QueryBuilder $query, int $planId): QueryBuilder
    {
        return $query->where('plan_id', $planId);
    }

    public function isTrialing(): bool
    {
        if (!$this->plan) {
            return false;
        }

        $trialEnds = (clone $this->start_date)->modify('+' . $this->plan->trial_days . ' days');
        return $this->plan->trial_days > 0 && new \DateTime() <= $trialEnds;
    }

    public function trialEndsAt(): ?\DateTime
    {
        if (!$this->plan || $this->plan->trial_days <= 0) {
            return null;
        }

        return (clone $this->start_date)->modify('+' . $this->plan->trial_days . ' days');
    }

    public function payments($relation = false)
    {
        return $this->hasMany(Payment::class, 'subscription_id', 'id', $relation);
    }

    public function lastPayment($relation = false)
    {
        return $this->hasOne(Payment::class, 'subscription_id', 'id', $relation)
            ->where('status', 'completed')
            ->orderBy('paid_at', 'desc')
            ->first();
    }

    public function isDueForRenewal(): bool
    {
        if (!$this->auto_renew || $this->status !== 'active') {
            return false;
        }

        if (!$this->next_billing_date) {
            return false;
        }

        return $this->next_billing_date <= new \DateTime();
    }

    public function getDaysUntilRenewal(): ?int
    {
        if (!$this->next_billing_date) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->next_billing_date);

        return $interval->invert ? 0 : $interval->days;
    }

    public function hasStripeSubscription(): bool
    {
        return $this->payment_subscription_id && strlen($this->payment_subscription_id) > 0;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->payment_subscription_id;
    }

    public function voucher($relation = false)
    {
        return $this->belongsTo(Voucher::class, 'voucher_id', 'id', $relation);
    }

    public function getDiscountedPrice(): float
    {
        return $this->price - $this->discount_amount;
    }

    public function hasVoucher(): bool
    {
        return $this->voucher_id !== null;
    }

    public function isOneTime(): bool
    {
        if ($this->relationLoaded('plan')) {
            return $this->plan->isOneTime();
        }

        $plan = $this->plan()->first();
        return $plan ? $plan->isOneTime() : false;
    }

    public function isDigital(): bool
    {
        return $this->delivery_type === 'digital';
    }

    public function isPrint(): bool
    {
        return $this->delivery_type === 'print';
    }

    public function hasValidDownload(): bool
    {
        if (!$this->download_url || !$this->download_expires_at) {
            return false;
        }

        return $this->download_expires_at > new \DateTime();
    }

    public function generateDownloadUrl(string $baseUrl): void
    {
        if ($this->isDigital() && $this->plan && $this->plan->digital_download_url) {
            $this->download_url = $this->plan->digital_download_url;
            // Set expiration to 30 days from now
            $this->download_expires_at = (new \DateTime())->modify('+30 days')->format('Y-m-d H:i:s');
            $this->save();
        }
    }

    public function order($relation = false)
    {
        return $this->hasMany(Order::class, 'one_time_subscription_id', 'id', $relation);
    }

    public function createWindow(): ?SubscriptionWindow
    {
        if ($this->type !== 'paid') {
            return null;
        }

        return SubscriptionWindow::create([
            'member_id' => $this->member_id,
            'subscription_id' => $this->id,
            'site_id' => $this->site_id,
            'window_start' => $this->start_date?->format('Y-m-d H:i:s'),
            'window_end' => $this->end_date?->format('Y-m-d H:i:s') ?? now(),
            'type' => 'paid'
        ]);
    }

    public function updateWindow(): void
    {
        // Only update windows for paid subscriptions
        if ($this->type !== 'paid') {
            return;
        }

        // Find existing window
        $window = SubscriptionWindow::where('subscription_id', $this->id)->first();

        if (!$window) {
            // Create if doesn't exist
            $this->createWindow();
            return;
        }

        // Update existing window
        $window->update([
            'window_end' => $this->end_date?->format('Y-m-d H:i:s') ?? $this->start_date->format('Y-m-d H:i:s'),
        ]);
    }

    public function closeWindow(): void
    {
        if ($this->type !== 'paid') {
            return;
        }

        $window = SubscriptionWindow::where('subscription_id', $this->id)->first();

        if ($window) {
            $window->update([
                'window_end' => now()
            ]);
        } else {
            // Create window if it doesn't exist (backfill case)
            SubscriptionWindow::create([
                'member_id' => $this->member_id,
                'subscription_id' => $this->id,
                'site_id' => $this->site_id,
                'window_start' => $this->start_date?->format('Y-m-d H:i:s'),
                'window_end' => now(),
                'type' => 'paid'
            ]);
        }
    }

    public function issueDeliveries($relation = false)
    {
        return $this->hasMany(IssueDelivery::class, 'subscription_id', 'id', $relation);
    }

    /**
     * Check if delivery is currently paused
     */
    public function isDeliveryPaused(): bool
    {
        if (!$this->delivery_paused) {
            return false;
        }

        $now = new \DateTime();

        // Check if we're within the pause period
        if ($this->delivery_pause_start && $this->delivery_pause_start > $now) {
            return false; // Pause hasn't started yet
        }

        if ($this->delivery_pause_end && $this->delivery_pause_end < $now) {
            return false; // Pause has ended
        }

        return true;
    }

    /**
     * Get days until pause ends
     */
    public function getDaysUntilPauseEnds(): ?int
    {
        if (!$this->isDeliveryPaused() || !$this->delivery_pause_end) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->delivery_pause_end);

        return $interval->invert ? 0 : $interval->days;
    }

    /**
     * Can delivery be paused?
     */
    public function canPauseDelivery(): bool
    {
        return $this->isPrint()
            && $this->isActive()
            && !$this->isDeliveryPaused();
    }

    /**
     * Can delivery be resumed?
     */
    public function canResumeDelivery(): bool
    {
        return $this->isPrint()
            && $this->isActive()
            && $this->isDeliveryPaused();
    }

    /**
     * Check if subscription includes Insider digital access
     */
    public function hasInsiderAccess(): bool
    {
        return $this->includes_digital_access || $this->isDigital();
    }

    /**
     * Check if this subscription was upgraded
     */
    public function wasUpgraded(): bool
    {
        return $this->upgraded_from_plan_id !== null;
    }

    /**
     * Get the original plan if upgraded
     */
    public function originalPlan()
    {
        if (!$this->upgraded_from_plan_id) {
            return null;
        }

        return $this->belongsTo(SubscriptionPlan::class, 'upgraded_from_plan_id', 'id');
    }

    /**
     * Check if subscription is eligible for upgrade to Insider
     */
    public function canUpgradeToInsider(): bool
    {
        return $this->isPrint()
            && !$this->includes_digital_access
            && $this->isActive()
            && !$this->isCancelled();
    }
}