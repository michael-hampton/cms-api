<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Models\Concerns\TracksCreator;

class ProductOfferBundle extends Model
{
    use TracksCreator;

    protected $table = 'product_offer_bundles';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'total_price',
        'bundle_price',
        'discount_percentage',
        'start_date',
        'end_date',
        'is_active',
        'status',
        'rejection_reason',
        'published_at',
        'published_by',
        'rejected_at',
        'rejected_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_price' => 'float',
        'bundle_price' => 'float',
        'discount_percentage' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function items($relation = false)
    {
        return $this->hasMany(ProductOfferBundleItem::class, 'bundle_id', 'id', $relation);
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function rejector()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopePublished(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'published');
    }

    public function scopePending(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'rejected');
    }

    public function canBePublished(): bool
    {
        return $this->status === 'pending' && $this->isCurrentlyActive();
    }

    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now_datetime();
        return $this->start_date <= $now && $this->end_date >= $now;
    }

    public function calculateSavings(): float
    {
        return $this->total_price - $this->bundle_price;
    }
}