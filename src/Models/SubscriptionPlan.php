<?php
namespace App\Models;

use App\Enums\Subscriptions\IssueDeliveryStatus;
use App\Enums\Subscriptions\SubscriptionDeliveryType;
use App\Enums\Subscriptions\SubscriptionEntitlementType;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Concerns\HasRegionSetVisibility;
use App\Services\Billing\Preorder\Contracts\AvailabilityPolicyInterface;
use App\Services\Billing\Preorder\SubscriptionAvailabilityPolicy;

class SubscriptionPlan extends Model
{
    use HasRegionSetVisibility;

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
        'stripe_product_id',
        'plan_type',
        'delivery_type',
        'entitlement_type',
        'digital_download_url',
        'print_shipping_required',
        'replacement_policy_id',
        'includes_insider',
        'is_upgrade_option',
        'upgrade_from_plan_id',
        'premium_access',
        'release_date',
        'pre_release_enabled',
        'dispatch_days',
        'categories',
        'tags',
        'print_image_url',
        'digital_image_url',
        'created_at',
        'updated_at'
    ];

    public $appends = [
        'lowest_effective_price',
        'default_lowest_effective_price',
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function replacementPolicy($relation = false)
    {
        return $this->belongsTo(ReplacementPolicy::class, 'replacement_policy_id', 'id', $relation);
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

    public function regionSets(bool $relation = false)
    {
        return $this->belongsToMany(
            RegionSet::class,
            'subscription_plan_region_sets',
            'subscription_plan_id',
            'region_set_id',
            $relation
        );
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
        return $this->getDeliveryType()?->includesDigital() ?? false;
    }

    public function hasPrintOption(): bool
    {
        return $this->getDeliveryType()?->includesPrint() ?? false;
    }

    public function getDeliveryType(): ?SubscriptionDeliveryType
    {
        $deliveryType = SubscriptionDeliveryType::tryFrom((string)$this->delivery_type);

        if ($deliveryType) {
            return $deliveryType;
        }

        $hasDigitalDownload = trim((string)($this->digital_download_url ?? '')) !== '';
        $requiresPrintShipping = (bool)($this->print_shipping_required ?? false);

        if ($hasDigitalDownload && $requiresPrintShipping) {
            return SubscriptionDeliveryType::PRINT_AND_DIGITAL;
        }

        if ($hasDigitalDownload) {
            return SubscriptionDeliveryType::DIGITAL;
        }

        if ($requiresPrintShipping) {
            return SubscriptionDeliveryType::PRINT;
        }

        return null;
    }

    public function getEntitlementType(): SubscriptionEntitlementType
    {
        return SubscriptionEntitlementType::tryFrom((string)($this->entitlement_type ?? 'time'))
            ?? SubscriptionEntitlementType::TIME;
    }

    public function getLowestEffectivePrice(): array
    {
        $default = $this->getDefaultEffectivePrice();

        if ($default['tier']) {
            return $default;
        }

        $tiers = $this->pricingTiers;
        $lowest = $this->resolveLowestEffectivePriceFromTiers($tiers);

        if ($lowest['tier'] || $tiers->isNotEmpty()) {
            return $lowest;
        }

        return $default;
    }

    public function getDefaultEffectivePrice(): array
    {
        $defaultTier = $this->pricingTiers()
            ->where('is_default', true)
            ->first();

        if (!$defaultTier) {
            return $this->planPriceResult();
        }

        return $this->resolveLowestEffectivePriceFromTiers([$defaultTier]);
    }

    public function getDefaultLowestEffectivePriceAttribute(): array
    {
        return $this->getDefaultEffectivePrice();
    }

    public function getLowestEffectivePriceAttribute()
    {
        return $this->getLowestEffectivePrice();
    }

    private function resolveLowestEffectivePriceFromTiers(iterable $tiers): array
    {
        $best = null;
        $availableFormats = [];

        foreach ($tiers as $tier) {
            foreach ($this->getAvailablePriceCandidates($tier) as $candidate) {
                $availableFormats[$candidate['delivery_type']] = true;

                if ($best === null || $candidate['price'] < $best['price']) {
                    $best = $candidate;
                }
            }
        }

        if ($best === null) {
            return $this->emptyAvailabilityAwarePriceResult();
        }

        $availableFormatCount = count($availableFormats);

        return [
            'min' => $best['price'],
            'tier' => $best['tier'],
            'delivery_type' => $best['delivery_type'],
            'available_format_count' => $availableFormatCount,
            'is_out_of_stock' => false,
            'show_from_prefix' => $availableFormatCount > 1,
        ];
    }

    private function getAvailablePriceCandidates(SubscriptionPlanPricing $tier): array
    {
        $candidates = [];
        $digitalAvailable = $this->isOneTime()
            ? $this->isDigitalInStock()
            : $this->hasDigitalOption();
        $printAvailable = $this->isOneTime()
            ? $this->isPrintInStock()
            : $this->hasPrintOption();

        if ($digitalAvailable) {
            $candidates[] = [
                'delivery_type' => SubscriptionType::DIGITAL->value,
                'price' => $tier->getEffectiveDigitalPrice(),
                'tier' => $tier,
            ];
        }

        if ($printAvailable) {
            $candidates[] = [
                'delivery_type' => SubscriptionType::PRINTED->value,
                'price' => $tier->getEffectivePrintPrice(),
                'tier' => $tier,
            ];
        }

        return $candidates;
    }

    private function planPriceResult(): array
    {
        return [
            'min' => (float)$this->price,
            'tier' => null,
            'delivery_type' => null,
            'available_format_count' => 0,
            'is_out_of_stock' => false,
            'show_from_prefix' => false,
        ];
    }

    private function emptyAvailabilityAwarePriceResult(): array
    {
        return [
            'min' => null,
            'tier' => null,
            'delivery_type' => null,
            'available_format_count' => 0,
            'is_out_of_stock' => true,
            'show_from_prefix' => false,
        ];
    }

    public function getBestSale(): ?array
    {
        $availability = [
            'print' => $this->isPrintInStock(),
            'digital' => $this->isDigitalInStock(),
        ];

        $best = null;

        foreach ($this->pricingTiers as $tier) {
            foreach (['print', 'digital'] as $type) {
                if (!$availability[$type]) {
                    continue;
                }

                $price = $type === 'print'
                    ? $tier->price
                    : ($tier->digital_price ?? $tier->price);

                $sale = $type === 'print'
                    ? $tier->sale_price
                    : ($tier->digital_sale_price ?? $tier->sale_price);

                if (!is_numeric($price)) {
                    continue;
                }

                $price = (float)$price;
                $sale = is_numeric($sale) ? (float)$sale : null;

                if ($sale !== null && $sale > 0 && $sale < $price) {
                    $pct = (int)round((($price - $sale) / $price) * 100);

                    if ($best === null || $pct > $best['savingPct']) {
                        $best = [
                            'original' => $price,
                            'sale' => $sale,
                            'savingPct' => $pct,
                            'tierId' => $tier->id,
                            'delivery_type' => $type === 'print'
                                ? SubscriptionType::PRINTED->value
                                : SubscriptionType::DIGITAL->value,
                        ];
                    }
                }
            }
        }

        return $best;
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

    public function getAvailableDeliveryOptions(): array
    {
        if (!$this->isOneTime()) {
            return $this->getDeliveryOptions();
        }

        $options = [];

        if ($this->isDigitalInStock()) {
            $options[] = SubscriptionType::DIGITAL->value;
        }

        if ($this->isPrintInStock()) {
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

    public function includesInsider(): bool
    {
        return $this->includes_insider;
    }

    public function isUpgradePlan(): bool
    {
        return $this->is_upgrade_option;
    }

    public function upgradesFromPlan()
    {
        if (!$this->upgrade_from_plan_id) {
            return null;
        }

        return $this->belongsTo(SubscriptionPlan::class, 'upgrade_from_plan_id', 'id');
    }

    public function getPremiumAccessGrants(): array
    {
        return $this->premium_access ?? [];
    }

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

    public function getIncludesInsiderAttribute(): bool
    {
        return $this->grantsPremiumAccess('newsletter', 'insider');
    }

    public function addPremiumAccess(string $type, string $identifier): void
    {
        $grants = $this->premium_access ?? [];

        foreach ($grants as $grant) {
            if ($grant['type'] === $type && $grant['identifier'] === $identifier) {
                return;
            }
        }

        $grants[] = ['type' => $type, 'identifier' => $identifier];
        $this->premium_access = $grants;
        $this->save();
    }

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

    public function getNextIssue(): ?IssueDelivery
    {
        return IssueDelivery::query()
            ->whereHas('subscriptionPlans', function ($q) {
                $q->where('id', $this->id);
            })
            ->where('status', IssueDeliveryStatus::ACTIVE->value)
            ->where(function ($q) {
                $q->where('on_sale_date', '>=', now_datetime()->modify('-7 days')->format('Y-m-d H:i:s'))
                    ->orWhereNull('on_sale_date');
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

    public function isPrintInStock(): bool
    {
        if (!$this->hasPrintOption()) {
            return false;
        }

        $issue = $this->getNextIssue();

        return $issue !== null && $issue->isInStock();
    }

    public function isDigitalInStock(): bool
    {
        if (!$this->hasDigitalOption()) {
            return false;
        }

        $explicitStock = $this->resolveExplicitDigitalStockState();

        if ($explicitStock !== null) {
            return $explicitStock;
        }

        if ($this->release_date && $this->release_date > now_datetime()) {
            return (bool)$this->pre_release_enabled;
        }

        return true;
    }

    private function resolveExplicitDigitalStockState(): ?bool
    {
        foreach (['digital_stock_quantity', 'digital_inventory_quantity'] as $field) {
            if (isset($this->{$field}) && is_numeric($this->{$field})) {
                return (int)$this->{$field} > 0;
            }
        }

        foreach (['digital_in_stock', 'is_digital_in_stock'] as $field) {
            if (isset($this->{$field})) {
                return (bool)$this->{$field};
            }
        }

        $issue = $this->getNextIssue();
        $metadata = is_array($issue?->metadata ?? null) ? $issue->metadata : [];

        foreach (['digital_stock_quantity', 'digital_inventory_quantity'] as $key) {
            if (array_key_exists($key, $metadata) && is_numeric($metadata[$key])) {
                return (int)$metadata[$key] > 0;
            }
        }

        foreach (['digital_in_stock', 'is_digital_in_stock'] as $key) {
            if (array_key_exists($key, $metadata)) {
                return (bool)$metadata[$key];
            }
        }

        return null;
    }

    public function segments($relation = false)
    {
        return $this->belongsToMany(
            Segment::class,
            'plan_segment',
            'plan_id',
            'segment_id',
            $relation
        );
    }

    public function promotion()
    {
        return $this->belongsToMany(
            Voucher::class,
            'voucher_subscription_plan',
            'subscription_plan_id',
            'voucher_id'
        );
    }
}