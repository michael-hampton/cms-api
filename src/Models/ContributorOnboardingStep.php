<?php

namespace App\Models;

/**
 * Tracks explicit workflow state for individual contributor onboarding steps.
 */
class ContributorOnboardingStep extends Model
{
    protected $table = 'oc_contributor_onboarding_steps';

    protected $fillable = [
        'user_id',
        'site_id',
        'step',
        'status',
        'completed_at',
        'created_at',
        'updated_at',
        'completed_meta'
    ];

    protected $casts = [
        'completed_at' => 'date',
        'created_at' => 'date',
        'updated_at' => 'date',
        'completed_meta' => 'array'
    ];
}
