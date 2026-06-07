<?php

namespace App\Models;

class PayoutLedgerEntry extends Model
{
    protected $table = 'oc_payout_ledger_entries';

    protected $fillable = [
        'payout_id',
        'earnings_ledger_id',
        'amount',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'payout_id' => 'integer',
        'earnings_ledger_id' => 'integer',
        'amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}