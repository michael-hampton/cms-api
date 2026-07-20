<?php

namespace App\Models;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;

class BusinessDecision extends Model
{
    protected $table = 'business_decisions';

    protected $fillable = [
        'category',
        'name',
        'description',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'category' => BusinessDecisionCategoryEnum::class,
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function assignments()
    {
        return $this->hasMany(BusinessDecisionAssignment::class);
    }

    public function reasonPolicies()
    {
        return $this->hasMany(CancellationReasonPolicy::class);
    }
}
