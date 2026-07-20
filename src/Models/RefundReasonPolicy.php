<?php

namespace App\Models;

class RefundReasonPolicy extends Model
{
    protected $table = 'refund_reason_policies';

    protected $fillable = [
        'business_decision_id', 'refund_reason_id', 'allow_full', 'allow_pro_rated',
        'allow_manual', 'allow_cancel_at_period_end', 'allow_cancel_immediately_no_refund',
        'refund_max_percent', 'manager_approval_threshold_percent',
        'default_notify_customer', 'requires_internal_notes',
    ];

    protected $casts = [
        'allow_full' => 'boolean',
        'allow_pro_rated' => 'boolean',
        'allow_manual' => 'boolean',
        'allow_cancel_at_period_end' => 'boolean',
        'allow_cancel_immediately_no_refund' => 'boolean',
        'refund_max_percent' => 'integer',
        'manager_approval_threshold_percent' => 'integer',
        'default_notify_customer' => 'boolean',
        'requires_internal_notes' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessDecision()
    {
        return $this->belongsTo(BusinessDecision::class);
    }

    public function refundReason()
    {
        return $this->belongsTo(RefundReason::class);
    }
}
