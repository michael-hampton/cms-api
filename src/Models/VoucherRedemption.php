<?php

namespace App\Models;

class VoucherRedemption extends Model
{
    protected $table = 'voucher_redemptions';

    protected $fillable = [
        'voucher_id',
        'member_id',
        'order_id',
        'redeemed_at',
        'discount_amount'
    ];

    protected $casts = [
        'voucher_id' => 'integer',
        'member_id' => 'integer',
        'order_id' => 'integer',
        'discount_amount' => 'float',
        'redeemed_at' => 'datetime',
    ];

    public function voucher($returnRelation = false)
    {
        return $this->belongsTo(Voucher::class, 'voucher_id', $returnRelation);
    }

    public function user($returnRelation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', $returnRelation);
    }

    public function order($returnRelation = false)
    {
        return $this->belongsTo(Order::class, 'order_id', $returnRelation);
    }
}