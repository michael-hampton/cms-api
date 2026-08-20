<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Subscriptions\ReplacementLimitScope;

class ReplacementPolicy extends Model
{
    protected $table = 'replacement_policies';

    protected $fillable = [
        'site_id',
        'name',
        'description',
        'replacement_limit_scope',
        'extension_limit_scope',
        'is_default',
        'active',
        'policy_class',
    ];

    protected $casts = [
        'allows_replacements' => 'boolean',
        'allows_extensions' => 'boolean',
        'max_replacements' => 'integer',
        'max_extensions' => 'integer',
        'require_stock' => 'boolean',
        'requires_manager_approval' => 'boolean',
        'is_default' => 'boolean',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function plans($relation = false)
    {
        return $this->hasMany(SubscriptionPlan::class, 'replacement_policy_id', 'id', $relation);
    }

    public function getReplacementLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::tryFrom((string) $this->replacement_limit_scope)
            ?? ReplacementLimitScope::PER_SUBSCRIPTION;
    }

    public function getExtensionLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::tryFrom((string) $this->extension_limit_scope)
            ?? ReplacementLimitScope::PER_SUBSCRIPTION;
    }
}