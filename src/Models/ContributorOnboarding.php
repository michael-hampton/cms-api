<?php

namespace App\Models;

use App\Enums\OpenCollab\ContributorOnboardingStatus;

/**
 * Tracks whether a contributor has started (and completed) onboarding.
 * Table: oc_contributor_onboarding
 */
class ContributorOnboarding extends Model
{
    protected $table = 'oc_contributor_onboarding';

    protected $fillable = [
        'user_id',
        'site_id',
        'status',
        'completed_at',
        'expires_at',
        'expired_at',
        'last_activity_at',
        'expiry_reason',
        'created_at',
        'updated_at',
        'started_at',
    ];

    protected $casts = [
        'completed_at'     => 'datetime',
        'expires_at'       => 'datetime',
        'expired_at'       => 'datetime',
        'last_activity_at' => 'datetime',
        'created_at'       => 'datetime',
        'updated_at'       => 'datetime',
        'started_at'       => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->status === ContributorOnboardingStatus::Expired->value;
    }

    public function isComplete(): bool
    {
        return $this->status === ContributorOnboardingStatus::Completed->value;
    }
}