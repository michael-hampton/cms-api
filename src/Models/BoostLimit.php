<?php

namespace App\Models;

class BoostLimit extends Model
{
    protected $table = 'boost_limits';

    protected $fillable = [
        'boost_id',
        'max_impressions',
        'max_clicks',
        'max_spend',
        'pause_on_breach',
    ];

    protected $casts = [
        'max_impressions' => 'integer',
        'max_clicks' => 'integer',
        'max_spend' => 'float',
        'pause_on_breach' => 'boolean',
    ];

    public function boost()
    {
        return $this->belongsTo(Boost::class);
    }

    public function hasImpressionLimit(): bool
    {
        return $this->max_impressions !== null;
    }

    public function hasClickLimit(): bool
    {
        return $this->max_clicks !== null;
    }

    public function hasSpendLimit(): bool
    {
        return $this->max_spend !== null;
    }
}