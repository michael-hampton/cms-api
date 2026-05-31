<?php

namespace App\Models;

class VoucherSubscriptionPlan extends Model
{
    protected $table = 'voucher_subscription_plan';

    protected $fillable = [
        'voucher_id',
        'subscription_plan_id',
    ];

    protected $casts = [
        'voucher_id'           => 'integer',
        'subscription_plan_id' => 'integer',
    ];

    public function voucher()
    {
        return $this->belongsTo(
            Voucher::class,
            'voucher_id'
        );
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(
            SubscriptionPlan::class,
            'subscription_plan_id'
        );
    }
}