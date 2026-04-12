<?php

namespace App\Models;

class PayoutAudit extends Model
{
    protected $table = 'oc_payout_audits';

    protected $fillable = [
        'payout_id',
        'action',
        'performed_by',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'date',
    ];
}