<?php

namespace App\Models;

class GiftPromotionTrigger extends Model
{
    protected $table = 'gift_promotion_triggers';

    protected $fillable = [
        'promotion_id',
        'type',
        'operator',
        'reference_id',
        'value',
        'value_set',
        'group_key',
        'negated'
    ];

    protected $casts = [
        'value' => 'float',
        'value_set' => 'array',
        'negated' => 'boolean',
    ];

    /*
    | ------------------------------------------------------------------
    | Relationships
    | ------------------------------------------------------------------
    */

    public function promotion()
    {
        return $this->belongsTo(GiftPromotion::class, 'promotion_id');
    }

    /*
    | ------------------------------------------------------------------
    | Helpers
    | ------------------------------------------------------------------
    */

    public function isNumericTrigger(): bool
    {
        return in_array($this->type, [
            'cart_total',
            'item_count'
        ]);
    }

    public function isEntityTrigger(): bool
    {
        return in_array($this->type, [
            'product',
            'subscription_plan',
            'category'
        ]);
    }
}