<?php

namespace App\Models;

class CancellationReasonPolicy extends Model
{
    protected $table = 'cancellation_reason_policies';

    protected $fillable = [
        'business_decision_id',
        'cancellation_reason_id',
        'show_save_actions',
        'allow_discount',
        'allow_offer_switch',
        'allow_cancel',
        'refund_max_percent',
        'marketing_consent',
    ];

    protected $casts = [
        'show_save_actions' => 'boolean',
        'allow_discount' => 'boolean',
        'allow_offer_switch' => 'boolean',
        'allow_cancel' => 'boolean',
        'refund_max_percent' => 'integer',
        'marketing_consent' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessDecision()
    {
        return $this->belongsTo(BusinessDecision::class);
    }

    public function cancellationReason()
    {
        return $this->belongsTo(CancellationReason::class);
    }
}
