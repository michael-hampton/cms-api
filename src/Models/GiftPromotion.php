<?php

namespace App\Models;

class GiftPromotion extends Model
{
    protected $table = 'gift_promotions';

    protected $fillable = [
        'merchant_id',
        'gift_type',
        'gift_product_id',
        'gift_subscription_plan_id',
        'quantity_rule',
        'max_per_order',
        'exclusive',
        'priority',
        'starts_at',
        'ends_at',
        'active',
        'website',
        'name'
    ];

    protected $casts = [
        'exclusive' => 'boolean',
        'active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'priority' => 'integer',
        'max_per_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /*
    | ------------------------------------------------------------------
    | Relationships
    | ------------------------------------------------------------------
    */

    public function triggers(bool $isRelation = false)
    {
        return $this->hasMany(GiftPromotionTrigger::class, 'promotion_id', 'id', $isRelation);
    }

    /*
    | ------------------------------------------------------------------
    | Scopes
    | ------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeForMerchant($query, array $merchantId)
    {
        return $query->where(function ($q) use ($merchantId) {
            $q->whereNull('merchant_id')
                ->orWhereIn('merchant_id', $merchantId);
        });
    }

    public function issueExclusions($relation = false)
    {
        return $this->hasMany(PromotionIssueExclusion::class, 'promotion_id', 'id', $relation);
    }

    public function excludedIssueDeliveries($relation = false)
    {
        return $this->belongsToMany(
            IssueDelivery::class,
            'promotion_issue_exclusions',
            'promotion_id',
            'issue_delivery_id',
        );
    }

    /**
     * Whether this promotion supports issue-level exclusions.
     * Only standalone subscription promotions can exclude individual issues.
     */
    public function supportsIssueExclusions(): bool
    {
        return $this->gift_type === 'subscription'
            && $this->website === 'standalone';
    }

    /**
     * Check whether a specific issue delivery is excluded from this promotion.
     * Used during eligibility evaluation — never cascade to subscription logic.
     */
    public function hasExcludedIssue(int $issueDeliveryId): bool
    {
        return PromotionIssueExclusion::where('promotion_id', $this->id)
            ->where('issue_delivery_id', $issueDeliveryId)
            ->exists();
    }

    /**
     * Get all excluded issue delivery IDs for this promotion.
     */
    public function getExcludedIssueIds(): array
    {
        return PromotionIssueExclusion::where('promotion_id', $this->id)
            ->get()
            ->pluck('issue_delivery_id')
            ->toArray();
    }
}