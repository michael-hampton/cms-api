<?php

namespace App\Models;

class PayoutLiabilityRecovery extends Model
{
    protected $table = 'oc_payout_liability_recoveries';

    protected $fillable = [
        'payout_id',
        'creator_liability_id',
        'amount',
        'source_type',
        'source_id',
        'reason',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'payout_id' => 'integer',
        'creator_liability_id' => 'integer',
        'amount' => 'integer',
        'source_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}