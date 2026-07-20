<?php

namespace App\Models;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;

/**
 * Assigns a BusinessDecision to a Site or a SubscriptionPlan for a given
 * category. See Review::reviewable() for the same morph convention used
 * elsewhere in this codebase (assignable_type stores the full model
 * class name).
 */
class BusinessDecisionAssignment extends Model
{
    protected $table = 'business_decision_assignments';

    protected $fillable = [
        'business_decision_id',
        'assignable_type',
        'assignable_id',
        'category',
    ];

    protected $casts = [
        'category' => BusinessDecisionCategoryEnum::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function businessDecision()
    {
        return $this->belongsTo(BusinessDecision::class);
    }

    public function assignable()
    {
        return $this->morphTo();
    }
}
