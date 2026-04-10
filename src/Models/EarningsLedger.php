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
    ];

    protected $casts = [
        'amount' => 'integer',
    ];
}