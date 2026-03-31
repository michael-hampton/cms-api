<?php

namespace App\Models;

use App\Enums\Vouchers\VoucherType;
use App\Models\Concerns\HasCloneHistory;
use App\Models\Concerns\TracksCreator;

class
Voucher extends Model
{
    use HasCloneHistory, TracksCreator;

    protected $table = 'vouchers';

    protected $fillable = [
        'site_id',
        'code',
        'name',
        'description',
        'type',
        'value',
        'minimum_order_value',
        'maximum_discount',
        'usage_limit',
        'usage_count',
        'per_user_limit',
        'starts_at',
        'expires_at',
        'status',
        'clone_history',
        'applies_to_subscriptions',
        'subscription_plan_ids',
        'stripe_coupon_id',
        'duration_in_months',
        'is_stackable',
        'merchant_id',
        'terms_and_conditions',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'value' => 'float',
        'minimum_order_value' => 'float',
        'maximum_discount' => 'float',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'per_user_limit' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'clone_history' => 'array',
        'applies_to_subscriptions' => 'boolean',
        'subscription_plan_ids' => 'array',
    ];

    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $now = new \DateTime();

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->expires_at && $this->expires_at < $now) {
            return false;
        }

        if ($this->usage_limit && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at < new \DateTime();
    }

    public function calculateDiscount(float $orderValue): float
    {
        if ($this->minimum_order_value && $orderValue < $this->minimum_order_value) {
            return 0;
        }

        $discount = 0;

        if ($this->type === VoucherType::Percentage->value) {
            $discount = ($orderValue * $this->value) / 100;
        } else {
            $discount = $this->value;
        }

        if ($this->maximum_discount && $discount > $this->maximum_discount) {
            $discount = $this->maximum_discount;
        }

        return round($discount, 2);
    }

    public function products($returnRelation = false)
    {
        return $this->belongsToMany(Product::class, 'product_voucher', 'voucher_id', 'product_id', $returnRelation);
    }

    public function categories($returnRelation = false)
    {
        return $this->belongsToMany(Category::class, 'voucher_categories', 'voucher_id', 'category_id', $returnRelation);
    }

    public function brands($returnRelation = false)
    {
        return $this->belongsToMany(Brand::class, 'voucher_brands', 'voucher_id', 'brand_id', $returnRelation);
    }

    public function isApplicableToProduct(int $productId): bool
    {
        // If no products, categories, or brands linked, voucher applies to all
        $hasProducts = $this->products()->count() > 0;
        $hasCategories = $this->categories()->count() > 0;
        $hasBrands = $this->brands()->count() > 0;

        if (!$hasProducts && !$hasCategories && !$hasBrands) {
            return true;
        }

        // Check if product is directly linked
        if ($hasProducts) {
            $directMatch = $this->products(true)->where('product_id', $productId)->get();
            if ($directMatch->count() > 0) {
                return true;
            }
        }

        // Check if product's category is linked
        if ($hasCategories) {
            $product = Product::find($productId);
            if ($product && $product->category_id) {
                $categoryMatch = $this->categories(true)->where('category_id', $product->category_id)->get();
                if ($categoryMatch->count() > 0) {
                    return true;
                }
            }
        }

        // Check if product's brand is linked
        if ($hasBrands) {
            $product = $product ?? Product::find($productId);
            if ($product && $product->brand_id) {
                $brandMatch = $this->brands(true)->where('brand_id', $product->brand_id)->get();
                if ($brandMatch->count() > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function redemptions($returnRelation = false)
    {
        return $this->hasMany(VoucherRedemption::class, 'voucher_id', $returnRelation);
    }

    public function hasBeenUsedByUser(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        return $this->redemptions()
                ->where('member_id', $userId)
                ->count() > 0;
    }

    public function getUserUsageCount(?int $userId): int
    {
        if (!$userId) {
            return 0;
        }

        return $this->redemptions()
            ->where('member_id', $userId)
            ->count();
    }

    public function subscriptions($returnRelation = false)
    {
        return $this->hasMany(Subscription::class, 'voucher_id', 'id', $returnRelation);
    }

    public function appliesToSubscriptions(): bool
    {
        return $this->applies_to_subscriptions ?? false;
    }

    public function isApplicableToSubscriptionPlan(int $planId): bool
    {
        if (!$this->applies_to_subscriptions) {
            return false;
        }

        if (!$this->subscription_plan_ids || count($this->subscription_plan_ids) === 0) {
            return true; // Applies to all plans
        }

        return in_array($planId, $this->subscription_plan_ids);
    }

    public function calculateSubscriptionDiscount(float $subscriptionPrice): float
    {
        $discount = 0;

        if ($this->type === VoucherType::Percentage->value) {
            $discount = ($subscriptionPrice * $this->value) / 100;
        } else {
            $discount = $this->value;
        }

        if ($this->maximum_discount && $discount > $this->maximum_discount) {
            $discount = $this->maximum_discount;
        }

        return round($discount, 2);
    }

    public function isNonStackable(): bool
    {
        return $this->is_stackable === false;
    }

    public function requiresOverrideForOfferDiscount(bool $hasOfferDiscount): bool
    {
        return $hasOfferDiscount && $this->isNonStackable();
    }
}