<?php
namespace App\Models;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Services\Billing\Preorder\Contracts\AvailabilityPolicyInterface;
use App\Services\Billing\Preorder\SubscriptionAvailabilityPolicy;

class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_period',
        'trial_days',
        'features',
        'is_active',
        'is_featured',
        'sort_order',
        'stripe_price_id',
        'plan_type',
        'digital_download_url',
        'print_shipping_required',
        'includes_insider',
        'is_upgrade_option',
        'upgrade_from_plan_id',
        'premium_access',
        'release_date',
        'pre_release_enabled',
        'dispatch_days',
        'categories',
        'tags'
    ];

    protected $casts = [
        'price' => 'float',
        'trial_days' => 'integer',
        'features' => 'array',
        'categories' => 'array',
        'tags' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'print_shipping_required' => 'boolean',
        'includes_insider' => 'boolean',
        'is_upgrade_option' => 'boolean',
        'premium_access' => 'array',
        'release_date' => 'datetime',
        'pre_release_enabled' => 'boolean',
    ];

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function subscriptions($relation = false)
    {
        return $this->hasMany(Subscription::class, 'plan_id', 'id', $relation);
    }

    public function activeSubscriptions($relation = false)
    {
        return $this->hasMany(Subscription::class, 'plan_id', 'id', $relation)
            ->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isFeatured(): bool
    {
        return $this->is_featured;
    }

    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    public function getFormattedPrice(): string
    {
        return $this->currency . ' ' . number_format($this->price, 2);
    }

    public function getBillingPeriodLabel(): string
    {
        return match ($this->billing_period) {
            'monthly' => 'per month',
            'quarterly' => 'per quarter',
            'yearly' => 'per year',
            'lifetime' => 'one-time',
            default => ''
        };
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_featured', true);
    }

    public function scopeBySite(QueryBuilder $query, int $siteId): QueryBuilder
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeOrdered(QueryBuilder $query): QueryBuilder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('price', 'asc');
    }

    public function isRecurring(): bool
    {
        return $this->plan_type === 'recurring';
    }

    public function isOneTime(): bool
    {
        return $this->plan_type === 'onetime';
    }

    public function hasDigitalOption(): bool
    {
        return $this->digital_download_url && strlen($this->digital_download_url) > 0;
    }

    public function hasPrintOption(): bool
    {
        return $this->print_shipping_required;
    }

    public function getDeliveryOptions(): array
    {
        $options = [];

        if ($this->hasDigitalOption()) {
            $options[] = SubscriptionType::DIGITAL->value;
        }

        if ($this->hasPrintOption()) {
            $options[] = SubscriptionType::PRINTED->value;
        }

        return $options;
    }

    public function scopeOneTime(QueryBuilder $query): QueryBuilder
    {
        return $query->where('plan_type', 'onetime');
    }

    public function scopeRecurring(QueryBuilder $query): QueryBuilder
    {
        return $query->where('plan_type', 'recurring');
    }

    /**
     * Check if plan includes Insider access
     */
    public function includesInsider(): bool
    {
        return $this->includes_insider;
    }

    /**
     * Check if this is an upgrade plan
     */
    public function isUpgradePlan(): bool
    {
        return $this->is_upgrade_option;
    }

    /**
     * Get the plan this upgrades from
     */
    public function upgradesFromPlan()
    {
        if (!$this->upgrade_from_plan_id) {
            return null;
        }

        return $this->belongsTo(SubscriptionPlan::class, 'upgrade_from_plan_id', 'id');
    }

    /**
     * Get premium access grants this plan provides
     */
    public function getPremiumAccessGrants(): array
    {
        return $this->premium_access ?? [];
    }

    /**
     * Check if plan grants specific premium access
     */
    public function grantsPremiumAccess(string $type, string $identifier): bool
    {
        $grants = $this->getPremiumAccessGrants();

        foreach ($grants as $grant) {
            if ($grant['type'] === $type && $grant['identifier'] === $identifier) {
                return true;
            }
        }

        return false;
    }

    /**
     * Backward compatibility
     */
    public function getIncludesInsiderAttribute(): bool
    {
        return $this->grantsPremiumAccess('newsletter', 'insider');
    }

    /**
     * Add premium access to plan
     */
    public function addPremiumAccess(string $type, string $identifier): void
    {
        $grants = $this->premium_access ?? [];

        // Check if already exists
        foreach ($grants as $grant) {
            if ($grant['type'] === $type && $grant['identifier'] === $identifier) {
                return;
            }
        }

        $grants[] = ['type' => $type, 'identifier' => $identifier];
        $this->premium_access = $grants;
        $this->save();
    }

    /**
     * Remove premium access from plan
     */
    public function removePremiumAccess(string $type, string $identifier): void
    {
        $grants = $this->premium_access ?? [];

        $this->premium_access = array_values(array_filter($grants, function ($grant) use ($type, $identifier) {
            return !($grant['type'] === $type && $grant['identifier'] === $identifier);
        }));

        $this->save();
    }

    public function pricingTiers()
    {
        return $this->hasMany(SubscriptionPlanPricing::class, 'plan_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function getDefaultPricing(): ?SubscriptionPlanPricing
    {
        return $this->pricingTiers()
            ->where('is_default', true)
            ->first();
    }

    public function issueSchedules()
    {
        return $this->hasMany(IssueDelivery::class, 'subscription_plan_id')
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function availabilityPolicy(): AvailabilityPolicyInterface
    {
        return new SubscriptionAvailabilityPolicy($this);
    }

    public function isPreRelease(): bool
    {
        return $this->availabilityPolicy()->isPreRelease();
    }

    /**
     * Get the next scheduled issue for this subscription plan
     * This is the issue that new subscribers would receive first
     */
    public function getNextIssue(): ?IssueDelivery
    {

        return IssueDelivery::query()
            ->whereHas('subscriptionPlans', function ($q) {
                $q->where('id', $this->id);
            })
            ->where('status', IssueDeliveryStatus::ACTIVE->value)
            ->where(function ($q) {
                // Get issues that are either:
                // 1. On sale date is in the future (upcoming)
                // 2. On sale date is today or recent past (current issue)
                $q->where('on_sale_date', '>=', now_datetime()->modify('-7 days')->format('Y-m-d H:i:s'))
                    ->orWhereNull('on_sale_date'); // Draft issues without sale date yet
            })
            ->orderBy('on_sale_date', 'asc')
            ->first();
    }

    public function getCurrentIssue(): ?IssueDelivery
    {
        return IssueDelivery::query()
            ->whereHas('subscriptionPlans', function ($q) {
                $q->where('id', $this->id);
            })
            ->where('status', IssueDeliveryStatus::ACTIVE->value)
            ->where('on_sale_date', '<=', now())
            ->orderBy('on_sale_date', 'desc')
            ->first();
    }

    /**
     * Get all future issues for this plan
     */
    public function getUpcomingIssues(): Collection
    {
        return IssueDelivery::query()
            ->whereHas('subscriptionPlans', function ($q) {
                $q->where('id', $this->id);
            })
            ->where('status', IssueDeliveryStatus::ACTIVE->value)
            ->where('on_sale_date', '>', now())
            ->orderBy('on_sale_date', 'asc')
            ->get();
    }

}