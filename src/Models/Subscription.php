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
        'start_date',
        'end_date',
        'trial_ends_at',       // ← added: persisted trial expiry, source of truth for conversion job
        'auto_renew',
        'price',
        'price_paid_cents',
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
        'stripe_schedule_id'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'trial_ends_at' => 'datetime',   // ← added
        'auto_renew' => 'boolean',
        'price' => 'float',
        'price_paid_cents' => 'integer',
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
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function isActive(): bool
    {
        // isActive() is intentionally strict — only ACTIVE, not trialing or grace.
        // Use SubscriptionStatus::isEntitled() for access decisions.
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
        return $this->cancelled_at !== null && $this->cancelled_at > new DateTime();
    }

    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatus::EXPIRED->value ||
            ($this->end_date !== null && $this->end_date <= new DateTime());
    }

    // =========================================================================
    // Trial
    // =========================================================================

    /**
     * Whether this subscription is currently in a trial period.
     *
     * Uses the persisted trial_ends_at rather than recomputing from the plan,
     * so the answer is stable even if the plan's trial_days changes later.
     */
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

    /**
     * Returns the persisted trial end date, or null if the subscription has
     * no trial.  Prefer this over the computed trialEndsAt() for any logic
     * that runs after subscription creation.
     */
    public function getTrialEndsAt(): ?DateTime
    {
        return $this->trial_ends_at
            ? DateTime::createFromInterface($this->trial_ends_at)
            : null;
    }

    /**
     * @deprecated Use getTrialEndsAt() — this computes from the plan relation
     *             and breaks if the plan is later modified.
     */
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

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', SubscriptionStatus::ACTIVE->value);
    }

    public function scopeByMember(QueryBuilder $query, int $memberId): QueryBuilder
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Subscriptions whose trial period has expired and are still in TRIALING
     * status — these are candidates for the conversion job.
     */
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

    public function closeWindow(): void
    {
        if ($this->type !== 'paid') {
            return;
        }

        $window = SubscriptionWindow::where('subscription_id', $this->id)->first();

        if ($window) {
            $window->update(['window_end' => now()]);
        } else {
            SubscriptionWindow::create([
                'member_id' => $this->member_id,
                'subscription_id' => $this->id,
                'site_id' => $this->site_id,
                'window_start' => $this->start_date?->format('Y-m-d H:i:s'),
                'window_end' => now(),
                'type' => 'paid',
            ]);
        }
    }

    public function issueDeliveries($relation = false)
    {
        return $this->hasMany(IssueDelivery::class, 'subscription_id', 'id', $relation);
    }

    public function isDeliveryPaused(): bool
    {
        if (!$this->delivery_paused) {
            return false;
        }

        $now = new DateTime();

        if ($this->delivery_pause_start && $this->delivery_pause_start > $now) {
            return false;
        }

        if ($this->delivery_pause_end && $this->delivery_pause_end < $now) {
            return false;
        }

        return true;
    }

    public function getDaysUntilPauseEnds(): ?int
    {
        if (!$this->isDeliveryPaused() || !$this->delivery_pause_end) {
            return null;
        }

        $now = new DateTime();
        $interval = $now->diff($this->delivery_pause_end);

        return $interval->invert ? 0 : $interval->days;
    }

    public function canPauseDelivery(): bool
    {
        return $this->isPrint()
            && $this->isActive()
            && !$this->isDeliveryPaused();
    }

    public function canResumeDelivery(): bool
    {
        return $this->isPrint()
            && $this->isActive()
            && $this->isDeliveryPaused();
    }

    public function hasInsiderAccess(): bool
    {
        return $this->hasPremiumAccess('newsletter', 'insider') ||
            $this->includes_digital_access ||
            $this->isDigital();
    }

    public function canUpgradeToPremium(string $type, string $identifier): bool
    {
        if ($this->hasPremiumAccess($type, $identifier)) {
            return false;
        }

        if (!$this->isActive()) {
            return false;
        }

        if ($this->isCancelled()) {
            return false;
        }

        return true;
    }

    public function wasUpgraded(): bool
    {
        return $this->upgraded_from_plan_id !== null;
    }

    public function originalPlan()
    {
        if (!$this->upgraded_from_plan_id) {
            return null;
        }

        return $this->belongsTo(SubscriptionPlan::class, 'upgraded_from_plan_id', 'id');
    }

    public function canUpgradeToInsider(): bool
    {
        return $this->canUpgradeToPremium('newsletter', 'insider') && $this->isPrint();
    }

    public function getAvailableUpgrades(): array
    {
        if (!$this->plan) {
            return [];
        }

        $currentAccess = $this->premiumAccess(true)
            ->get()
            ->map(fn($access) => $access->premium_type . ':' . $access->premium_identifier)
            ->toArray();

        $upgradePlans = SubscriptionPlan::where('site_id', $this->site_id)
            ->where('is_active', true)
            ->where('is_upgrade_option', true)
            ->where(function ($q) {
                $q->where('upgrade_from_plan_id', $this->plan_id)
                    ->orWhereNull('upgrade_from_plan_id');
            })
            ->get();

        $available = [];
        foreach ($upgradePlans as $plan) {
            $planAccess = $plan->getPremiumAccessGrants();

            $newAccess = array_filter($planAccess, function ($access) use ($currentAccess) {
                $key = $access['type'] . ':' . $access['identifier'];
                return !in_array($key, $currentAccess);
            });

            if (!empty($newAccess)) {
                $available[] = ['plan' => $plan, 'new_access' => $newAccess];
            }
        }

        return $available;
    }

    public function premiumAccess($relation = false)
    {
        return $this->hasMany(SubscriptionPremiumAccess::class, 'subscription_id', 'id', $relation)
            ->where('is_active', true);
    }

    public function hasPremiumAccess(string $type, string $identifier): bool
    {
        return SubscriptionPremiumAccess::where('subscription_id', $this->id)
            ->where('premium_type', $type)
            ->where('premium_identifier', $identifier)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function grantPremiumAccess(string $type, string $identifier, ?DateTime $expiresAt = null): SubscriptionPremiumAccess
    {
        return SubscriptionPremiumAccess::updateOrCreate(
            [
                'subscription_id' => $this->id,
                'premium_type' => $type,
                'premium_identifier' => $identifier,
            ],
            [
                'granted_at' => now_datetime()->format('Y-m-d H:i:s'),
                'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
                'is_active' => true,
            ]
        );
    }

    public function revokePremiumAccess(string $type, string $identifier): bool
    {
        return SubscriptionPremiumAccess::where('subscription_id', $this->id)
            ->where('premium_type', $type)
            ->where('premium_identifier', $identifier)
            ->update(['is_active' => 0]);
    }

    public function getPremiumNewsletters(): array
    {
        return SubscriptionPremiumAccess::active()
            ->where('subscription_id', $this->id)
            ->where('premium_type', 'newsletter')
            ->get()
            ->pluck('premium_identifier')
            ->toArray();
    }

    public function hasAnyPremiumNewsletter(): bool
    {
        return SubscriptionPremiumAccess::active()
            ->where('subscription_id', $this->id)
            ->where('premium_type', 'newsletter')
            ->exists();
    }

    public function grantLowerTierPlans(): array
    {
        if (!$this->plan) {
            return [];
        }

        $allPlans = SubscriptionPlan::where('site_id', $this->site_id)
            ->where('is_active', true)
            ->where('id', '!=', $this->plan_id)
            ->get();

        $lowerTierPlans = $allPlans->filter(function ($plan) {
            return $plan->price < $this->plan->price;
        });

        $grantedAccess = [];

        foreach ($lowerTierPlans as $plan) {
            $premiumGrants = $plan->getPremiumAccessGrants();

            foreach ($premiumGrants as $grant) {
                $exists = $this->premiumAccess(true)
                    ->where('premium_type', $grant['type'])
                    ->where('premium_identifier', $grant['identifier'])
                    ->get();

                if ($exists->count() === 0) {
                    $access = $this->grantPremiumAccess(
                        $grant['type'],
                        $grant['identifier'],
                        $grant['expires_at'] ?? null
                    );

                    $grantedAccess[] = ['plan' => $plan->name, 'access' => $access];
                }
            }
        }

        return $grantedAccess;
    }

    public function isEligibleForPaidNewsletter(): bool
    {
        if ($this->type !== 'paid') {
            return false;
        }

        if ($this->isTrialing()) {
            return true;
        }

        if (!SubscriptionStatus::isEntitled($this->status)) {
            return false;
        }

        $now = new DateTime();

        if ($this->start_date && $this->start_date > $now) {
            return false;
        }

        if (in_array($this->status, [SubscriptionStatus::GRACE_PERIOD->value], true)) {
            if ($this->end_date && $this->end_date < $now) {
                return false;
            }
        }

        return true;
    }

    public function canAccessNewsletter(Newsletter $newsletter, ?Member $member = null): NewsletterAccessResult
    {
        if (!$this->isEligibleForPaidNewsletter()) {
            return NewsletterAccessResult::denied(
                'subscription_not_eligible',
                "Subscription status '{$this->status}' is not eligible"
            );
        }

        if ($newsletter->slug) {
            $hasDirectAccess = $this->hasPremiumAccess(PremiumAccessType::Newsletter->value, $newsletter->slug);
            $hasAccessThroughPlan = $this->plan?->grantsPremiumAccess(PremiumAccessType::Newsletter->value, $newsletter->slug);
            $hasBundleAccess = $this->hasBundleAccessToNewsletter($newsletter->slug);

            if (!$hasDirectAccess && !$hasAccessThroughPlan && !$hasBundleAccess && !$newsletter->requiresBundle()) {
                return NewsletterAccessResult::denied(
                    'access_level_mismatch',
                    "Subscription does not grant access to newsletter '{$newsletter->slug}'"
                );
            }
        }

        if ($newsletter->requiresBundle()) {
            if (!$this->hasBundle($newsletter->bundle_id)) {
                $bundle = $newsletter->bundle();
                $bundleName = $bundle?->slug ?? $bundle?->name ?? 'Unknown Bundle';
                return NewsletterAccessResult::denied(
                    'bundle_required',
                    "Newsletter requires '{$bundleName}' bundle which subscription does not include"
                );
            }
        }

        if ($newsletter->hasGeographicRestrictions()) {
            if (!$member) {
                return NewsletterAccessResult::denied(
                    'member_required_for_geo_check',
                    'Member information required to verify geographic eligibility'
                );
            }

            $memberRegion = $member->getRegion();

            if (!$newsletter->isRegionAllowed($memberRegion)) {
                $region = $memberRegion ?? 'Unknown';
                return NewsletterAccessResult::denied(
                    'geographic_restriction',
                    "Newsletter not available in region '{$region}'"
                );
            }
        }

        if ($newsletter->hasTimeWindow()) {
            $now = new DateTime();

            if (!$newsletter->isWithinAccessWindow($now, $this)) {
                $start = $newsletter->access_window_start?->format('Y-m-d H:i:s') ?? 'N/A';
                $end = $newsletter->access_window_end?->format('Y-m-d H:i:s') ?? 'N/A';

                return NewsletterAccessResult::denied(
                    'outside_access_window',
                    "Newsletter access window: {$start} to {$end}"
                );
            }
        }

        return NewsletterAccessResult::allowed();
    }

    public function hasBundle(int $bundleId): bool
    {
        return $this->bundle_id === $bundleId;
    }

    public function bundle()
    {
        if (!$this->bundle_id) {
            return null;
        }

        return SubscriptionBundle::find($this->bundle_id);
    }

    public function hasBundleAccessToNewsletter(string $newsletterSlug): bool
    {
        $bundle = $this->bundle();

        if (!$bundle) {
            return false;
        }

        return $bundle->includesNewsletter($newsletterSlug);
    }

    public function hasAccess(): bool
    {
        if (!$this->access_starts_at) {
            return true;
        }

        return $this->access_starts_at <= now();
    }

    public function canShip(): bool
    {
        if (!$this->first_shipment_at) {
            return true;
        }

        return $this->first_shipment_at <= now();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'subscription_id', 'id');
    }

    public function lastPayment()
    {
        return $this->payments()->last();
    }

    public function giftedBy()
    {
        return $this->belongsTo(Member::class, 'gifted_by_member_id', 'id');
    }
}
