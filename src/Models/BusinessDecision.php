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

    /**
     * The custom ORM does not hydrate backed-enum casts on read, so
     * `category` may arrive as a string or as BusinessDecisionCategoryEnum.
     */
    public function categoryEnum(): BusinessDecisionCategoryEnum
    {
        if ($this->category instanceof BusinessDecisionCategoryEnum) {
            return $this->category;
        }

        return BusinessDecisionCategoryEnum::from((string) $this->category);
    }

    public function categoryValue(): string
    {
        return $this->categoryEnum()->value;
    }

    public function assignments()
    {
        return $this->hasMany(BusinessDecisionAssignment::class);
    }

    public function reasonPolicies()
    {
        return $this->hasMany(CancellationReasonPolicy::class);
    }

    public function refundReasonPolicies()
    {
        return $this->hasMany(RefundReasonPolicy::class);
    }
}
