<?php

namespace App\Models;

class CampaignVariant extends Model
{
    protected $table = 'campaign_variants';
    protected $fillable = [
        'campaign_id',
        'key',
        'weight',
        'blocks',
    ];

    protected $casts = [
        'blocks' => 'array',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function deliveries()
    {
        return $this->hasMany(CampaignDelivery::class, 'variant_id');
    }
}