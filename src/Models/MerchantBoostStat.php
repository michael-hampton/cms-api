<?php

namespace App\Models;

class MerchantBoostStat extends Model
{
    protected $table = 'merchant_boost_stats';

    protected $fillable = [
        'merchant_id',
        'total_impressions',
        'total_clicks',
        'total_conversions',
        'total_spend_attributed',
        'last_aggregated_at',
    ];

    protected $casts = [
        'total_impressions' => 'integer',
        'total_clicks' => 'integer',
        'total_conversions' => 'integer',
        'total_spend_attributed' => 'float',
        'last_aggregated_at' => 'datetime',
    ];
}