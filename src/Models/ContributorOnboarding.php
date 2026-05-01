<?php

namespace App\Models;

/**
 * Tracks whether a contributor has started (and completed) onboarding.
 * Table: contributor_onboarding
 */
class ContributorOnboarding extends Model
{
    protected $table = 'oc_contributor_onboarding';

    protected $fillable = [
        'user_id',
        'site_id',
        'status',
        'created_at',
        'updated_at',
        'completed_at'
    ];

    protected $casts = [
        'created_at' => 'date',
        'updated_at' => 'date',
    ];
}