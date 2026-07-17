<?php

declare(strict_types=1);

namespace App\Models;

class SubscriptionPolicySettingOverride extends Model
{
    protected $table = 'subscription_policy_setting_overrides';

    protected $fillable = [
        'site_id',
        'policy_class',
        'setting_key',
        'value',
        'reason',
        'created_by_user_id',
        'active',
    ];

    protected $casts = [
        'value' => 'array',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }
}