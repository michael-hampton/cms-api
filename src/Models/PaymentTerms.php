<?php

namespace App\Models;

class PaymentTerms extends Model
{
    protected $table = 'oc_payment_terms';

    protected $fillable = [
        'site_id',
        'payout_delay_days',
        'minimum_payout_amount',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'payout_delay_days' => 'integer',
        'minimum_payout_amount' => 'integer',
        'created_at' => 'date',
        'updated_at' => 'date',
    ];
}