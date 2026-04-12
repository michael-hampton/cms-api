<?php

namespace App\Models;

class EarningsLedger extends Model
{
    protected $table = 'oc_earnings_ledger';

    protected $fillable = [
        'user_id',
        'article_id',
        'type',
        'amount',
        'currency',
        'reference_id',
        'paid_at',
        'earned_at'
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
        'earned_at' => 'datetime'
    ];
}