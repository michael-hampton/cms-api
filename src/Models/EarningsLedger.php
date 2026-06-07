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
        'earned_at',

        'accrual_status',

        'confirmed_at',
        'confirmed_by',

        'settled_at',
        'settled_by',

        'withdrawn_at',
        'payout_id',

        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected $casts = [
        'amount' => 'integer',

        'paid_at' => 'datetime',
        'earned_at' => 'datetime',

        'confirmed_at' => 'datetime',
        'confirmed_by' => 'integer',

        'settled_at' => 'datetime',
        'settled_by' => 'integer',

        'withdrawn_at' => 'datetime',
        'payout_id' => 'integer',

        'reversed_at' => 'datetime',
        'reversed_by' => 'integer',
    ];
}