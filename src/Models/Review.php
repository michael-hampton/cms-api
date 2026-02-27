<?php

namespace App\Models;

/**
 * Review model.
 *
 * Supports two review targets, both stored in the same table:
 *
 *   1. Products  – legacy path via product_id (backwards compatible).
 *   2. Any model – polymorphic path via reviewable_type + reviewable_id.
 *      Currently used for SubscriptionPlan reviews.
 *
 * The polymorphic columns are the canonical storage going forward.
 * The product_id column is deprecated but remains functional so that
 * all existing code continues to work without modification.
 */
class Review extends Model
{
    protected $table = 'reviews';

    protected $fillable = [
        'product_id',           // Deprecated — use reviewable_type/reviewable_id
        'reviewable_type',      // e.g. App\Models\Product | App\Models\SubscriptionPlan
        'reviewable_id',        // FK to the reviewable entity
        'user_id',
        'rating',
        'title',
        'comment',
        'is_verified_purchase',
        'is_approved',
        'helpful_count',
        'unhelpful_count',
        'site_id',
        'created_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
        'is_approved' => 'boolean',
        'helpful_count' => 'integer',
        'unhelpful_count' => 'integer',
        'reviewable_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────

    /**
     * The entity being reviewed (polymorphic).
     * Returns the Product or SubscriptionPlan (or any future reviewable model).
     */
    public function reviewable()
    {
        return $this->morphTo();
    }

    /**
     * Legacy product relationship — kept for backwards compatibility.
     * Prefer using reviewable() in new code.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(Member::class, 'user_id', 'id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope for legacy product-based reviews (backwards compatible).
     */
    public function scopeByProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope for polymorphic target.
     * Use this for any new entity type (e.g. SubscriptionPlan).
     */
    public function scopeForReviewable($query, string $type, int $id)
    {
        return $query->where('reviewable_type', $type)
            ->where('reviewable_id', $id);
    }

    public function scopeByRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopeVerifiedPurchase($query)
    {
        return $query->where('is_verified_purchase', true);
    }

    // ─── Accessors ────────────────────────────────────────────────────────

    public function getAuthorNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->first_name . ' ' . $this->user->last_name;
        }
        return 'Anonymous';
    }

    public function getFormattedDateAttribute(): string
    {
        if ($this->created_at) {
            return $this->created_at->format('F j, Y');
        }
        return '';
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Whether this review targets a subscription plan.
     */
    public function isForPlan(): bool
    {
        return $this->reviewable_type === SubscriptionPlan::class;
    }

    /**
     * Whether this review targets a product (legacy or polymorphic).
     */
    public function isForProduct(): bool
    {
        return $this->reviewable_type === Product::class
            || ($this->reviewable_type === null && $this->product_id !== null);
    }
}