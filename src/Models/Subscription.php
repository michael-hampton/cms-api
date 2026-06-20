<?php

namespace App\Models;

use App\DTO\Newsletters\NewsletterAccessResult;
use App\Enums\Newsletters\PremiumAccessType;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Database\QueryBuilder;
use DateTime;

class Subscription extends Model
{
    public const ACTIVE_STATUSES = [SubscriptionStatus::ACTIVE->value, SubscriptionStatus::TRIALING->value, SubscriptionStatus::GRACE_PERIOD->value];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($subscription) {
            if ($subscription->type === 'paid') {
                $subscription->createWindow();
            }
        });

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
        'renewal_count',
        'first_renewed_at',
        'last_renewed_at',
        'start_date',
        'end_date',
        'trial_ends_at',
        'auto_renew',
        'auto_renew_before_pause',
        'paused_at',
        'pause_until',
        'resumed_at',
        'price',
        'price_paid_cents',
        'subscription_plan_pricing_id',
        'currency',
        'plan_id',
        'next_billing_date',
        'last_payment_date',
        'payment_intent_id',
        'payment_subscription_id',
        'stripe_subscription_id',
        'stripe_subscription_item_id',
        'stripe_price_id',
        'stripe_sync_status',
        'stripe_sync_error',
        'stripe_synced_at',
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
        'cancellation_reason',
        'cancellation_notes',
        'current_period_start',
        'current_period_end',
        'includes_digital_access',
        'upgraded_from_plan_id',
        'upgraded_at',
        'upgrade_price_difference',
        'premium_access',
        'bundle_id',
        'access_starts_at',
        'first_shipment_at',
        'account_number',
        'is_linked',
        'territory_id',
        'territory_override_flag',
        'gifted_by_member_id',
        'replaced_by_subscription_id',
        'replacement_reason',
        'carried_over_credit',
        'renewed_from_subscription_id',
        'cancel_at_period_end',
        'stripe_customer_id',
        'stripe_schedule_id',
        'trial_used_at',
        'offer_type',
        'consent_given',
        'billing_day_of_month',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'trial_ends_at' => 'datetime',
        'auto_renew' => 'boolean',
        'auto_renew_before_pause' => 'boolean',
        'paused_at' => 'datetime',
        'pause_until' => 'datetime',
        'resumed_at' => 'datetime',
        'price' => 'float',
        'price_paid_cents' => 'integer',
        'subscription_plan_pricing_id' => 'integer',
        'renewal_count' => 'integer',
        'first_renewed_at' => 'datetime',
        'last_renewed_at' => 'datetime',
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
        'premium_access' => 'array',
        'access_starts_at' => 'datetime',
        'first_shipment_at' => 'datetime',
        'is_linked' => 'boolean',
        'stripe_synced_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'consent_given' => 'boolean',
        'billing_day_of_month' => 'integer',
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function isActive(): bool
    {
        if ($this->status !== SubscriptionStatus::ACTIVE->value) {
            return false;
        }

        $now = new DateTime();

        if ($this->start_date && $this->start_date > $now) {
            return false;
        }

        if ($this->end_date && $this->end_date < $now) {
            return false;
        }

        return true;
    }

    public function isCancelled(): bool
    {
        return $this->status === SubscriptionStatus::CANCELLED->value;
    }

    public function isCancellationScheduled(): bool
    {
        if (!$this->cancel_at_period_end || $this->status === SubscriptionStatus::CANCELLED->value) {
            return false;
        }

        return $this->end_date === null || $this->end_date > new DateTime();
    }

    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatus::EXPIRED->value ||
            ($this->end_date !== null && $this->end_date <= new DateTime());
    }

    public function isTrialing(): bool
    {
        if ($this->status !== SubscriptionStatus::TRIALING->value) {
            return false;
        }

        if (!$this->trial_ends_at) {
            return false;
        }

        return $this->trial_ends_at > new DateTime();
    }

    public function getTrialEndsAt(): ?DateTime
    {
        return $this->trial_ends_at
            ? DateTime::createFromInterface($this->trial_ends_at)
            : null;
    }

    public function trialEndsAt(): ?DateTime
    {
        if (!$this->plan || $this->plan->trial_days <= 0) {
            return null;
        }

        return (clone $this->start_date)->modify('+' . $this->plan->trial_days . ' days');
    }

    public function isTrialExpired(): bool
    {
        if (!$this->trial_ends_at) {
            return false;
        }

        return $this->trial_ends_at <= new DateTime();
    }

    public function getDaysRemainingInTrial(): ?int
    {
        if (!$this->isTrialing()) {
            return null;
        }

        $now = new DateTime();
        $diff = $now->diff(DateTime::createFromInterface($this->trial_ends_at));

        return $diff->invert ? 0 : $diff->days;
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', SubscriptionStatus::ACTIVE->value);
    }

    public function scopeByMember(QueryBuilder $query, int $memberId): QueryBuilder
    {
        return $query->where('member_id', $memberId);
    }

    public function scopeReadyForTrialConversion(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->where('status', SubscriptionStatus::TRIALING->value)
            ->where('trial_ends_at', '<=', now_datetime()->format('Y-m-d H:i:s'));
    }

    public function plan($relation = false)
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id', 'id', $relation);
    }

    public function scopeByPlan(QueryBuilder $query, int $planId): QueryBuilder
    {
        return $query->where('plan_id', $planId);
    }

    public function isDueForRenewal(): bool
    {
        if (!$this->auto_renew || $this->status !== SubscriptionStatus::ACTIVE->value) {
            return false;
        }

        if (!$this->next_billing_date) {
            return false;
        }

        return $this->next_billing_date <= new DateTime();
    }

    public function getDaysUntilRenewal(): ?int
    {
        if (!$this->next_billing_date) {
            return null;
        }

        $now = new DateTime();
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
        return $this->delivery_type === SubscriptionType::DIGITAL->value;
    }

    public function isPrint(): bool
    {
        return $this->delivery_type === SubscriptionType::PRINTED->value;
    }

    public function hasValidDownload(): bool
    {
        if (!$this->download_url || !$this->download_expires_at) {
            return false;
        }

        return $this->download_expires_at > new DateTime();
    }

    public function generateDownloadUrl(string $baseUrl): void
    {
        if ($this->isDigital() && $this->plan && $this->plan->digital_download_url) {
            $this->download_url = $this->plan->digital_download_url;
            $this->download_expires_at = (new DateTime())->modify('+30 days')->format('Y-m-d H:i:s');
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
            'type' => 'paid',
        ]);
    }

    public function updateWindow(): void
    {
        if ($this->type !== 'paid') {
            return;
        }

        $window = SubscriptionWindow::where('subscription_id', $this->id)->first();

        if (!$window) {
            $this->createWindow();
            return;
        }

        $window->update([
            'window_end' => $this->end_date?->format('Y-m-d H:i:s') ?? $this->start_date->format('Y-m-d H:i:s'),
        ]);
    }

    public function getNewsletterAccess(int $newsletterId): NewsletterAccessResult
    {
        $newsletter = Newsletter::find($newsletterId);

        if (!$newsletter) {
            return NewsletterAccessResult::denied('Newsletter not found');
        }

        if (!SubscriptionStatus::isEntitled((string) $this->status)) {
            return NewsletterAccessResult::denied('Subscription is not currently entitled');
        }

        if ($newsletter->site_id !== $this->site_id) {
            return NewsletterAccessResult::denied('Newsletter does not belong to this site');
        }

        $premiumAccess = $this->premium_access ?? [];
        $accessType = $premiumAccess[$newsletterId] ?? null;

        if (!$accessType) {
            return NewsletterAccessResult::denied('Newsletter is not included in this subscription');
        }

        return NewsletterAccessResult::granted(PremiumAccessType::from($accessType));
    }
}
