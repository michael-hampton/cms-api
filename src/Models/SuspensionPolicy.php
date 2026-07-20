<?php

namespace App\Models;

class SuspensionPolicy extends Model
{
    protected $table = 'suspension_policies';

    protected $fillable = [
        'business_decision_id',
        'allow_suspend',
        'requires_note',
    ];

    protected $casts = [
        'allow_suspend' => 'boolean',
        'requires_note' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessDecision()
    {
        return $this->belongsTo(BusinessDecision::class);
    }
}
