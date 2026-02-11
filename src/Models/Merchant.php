<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;

class Merchant extends Model
{
    use TracksCreator;

    protected $table = 'merchants';

    protected $fillable = [
        'name',
        'slug',
        'primary_url',
        'description',
        'contact_id',
        'logo',
        'created_by',
        'updated_by',
        'created_at',
        'updated_at',
        'is_active',
        'balance'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function productMerchants()
    {
        return $this->hasMany(ProductMerchant::class);
    }

    public function products()
    {
        return $this->hasManyThrough(
            Product::class,
            ProductMerchant::class,
            'merchant_id',
            'id',
            'id',
            'product_id'
        );
    }

    public function contact()
    {
        return $this->belongsTo(MerchantContact::class);
    }

    public function urls()
    {
        return $this->hasMany(MerchantUrl::class)->orderBy('is_primary', 'desc');
    }

    public function primaryUrl()
    {
        return $this->hasOne(MerchantUrl::class)->where('is_primary', true);
    }

    public function secondaryUrls()
    {
        return $this->hasMany(MerchantUrl::class)->where('is_primary', false);
    }

    public function sites(bool $relation = false)
    {
        return $this->belongsToMany(
            Site::class,
            'merchant_sites',
            'merchant_id',
            'site_id',
            $relation
        );
    }

    public function productFeeds()
    {
        return $this->hasMany(MerchantProductFeed::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopeBySite($query, int $siteId)
    {
        return $query->whereHas('sites', function ($q) use ($siteId) {
            $q->where('sites.id', $siteId);
        });
    }

    public function getProductCountAttribute(): int
    {
        return $this->products()->count();
    }
}