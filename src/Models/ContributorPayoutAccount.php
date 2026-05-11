<?php

namespace App\Models;

class ContributorPayoutAccount extends Model
{
    protected $table = 'contributor_payout_accounts';

    protected $fillable = [
        'user_id',
        'provider',
        'stripe_account_id',
        'charges_enabled',
        'payouts_enabled',
        'details_submitted',
        'onboarding_completed_at',
        'requirements_due_json',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'details_submitted' => 'boolean',
        'onboarding_completed_at' => 'datetime',
        'requirements_due_json' => 'json',
        'created_at' => 'date',
        'updated_at' => 'date',
    ];
}

