<?php

namespace App\Models;

class EarningsDispute extends Model
{
    protected $table = 'oc_earnings_disputes';

    protected $fillable = [
        'user_id',
        'earnings_ledger_id',
        'reason',
        'status',
        'admin_notes',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'created_at' => 'date',
        'updated_at' => 'date',
    ];
}