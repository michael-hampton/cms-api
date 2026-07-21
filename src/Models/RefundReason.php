<?php

namespace App\Models;

class RefundReason extends Model
{
    protected $table = 'refund_reasons';

    protected $fillable = [
        'code',
        'label',
        'requires_note',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'requires_note' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function policies()
    {
        return $this->hasMany(RefundReasonPolicy::class);
    }
}
