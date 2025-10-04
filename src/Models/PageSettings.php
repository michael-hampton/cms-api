<?php

namespace App\Models;

class PageSettings extends Model
{
    protected $table = 'page_settings';
    protected $fillable = [
        'page_id', 'template', 'custom_css', 'custom_js', 'redirect_url',
        'menu_order', 'parent_page', 'latitude', 'longitude', 'address',
        'price', 'currency', 'sale_price', 'recurring', 'recurring_period',
        'created_at', 'updated_at'
    ];

    protected $casts = [
        'menu_order' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'price' => 'float',
        'sale_price' => 'float',
        'recurring' => 'boolean'
    ];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class, 'page_id', 'id');
    }

    public function hasRedirect(): bool
    {
        return !empty($this->redirect_url);
    }

    public function hasGeolocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function hasPricing(): bool
    {
        return $this->price !== null && $this->price > 0;
    }

    public function getEffectivePrice(): ?float
    {
        if ($this->sale_price !== null && $this->sale_price > 0) {
            return (float) $this->sale_price;
        }
        return $this->price ? (float) $this->price : null;
    }
}