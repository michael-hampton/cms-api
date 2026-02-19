<?php

namespace App\Models;

class BoostStat extends Model
{
    protected $table = 'boost_stats';

    protected $fillable = [
        'boost_id',
        'impressions',
        'clicks',
        'conversions',
        'spend_attributed',
        'last_aggregated_at',
    ];

    protected $casts = [
        'impressions' => 'integer',
        'clicks' => 'integer',
        'conversions' => 'integer',
        'spend_attributed' => 'float',
        'last_aggregated_at' => 'datetime',
    ];

    public function boost()
    {
        return $this->belongsTo(Boost::class);
    }

    public function ctr(): float
    {
        if ($this->impressions === 0) {
            return 0.0;
        }

        return round($this->clicks / $this->impressions * 100, 2);
    }

    public function conversionRate(): float
    {
        if ($this->clicks === 0) {
            return 0.0;
        }

        return round($this->conversions / $this->clicks * 100, 2);
    }
}