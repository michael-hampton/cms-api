<?php

namespace App\Models;

class ContributorProfile extends Model
{
    protected $table = 'oc_contributor_profiles';

    protected $fillable = [
        'user_id',
        'bio',
        'avatar',
        'payment_method_type',
        'payment_details',
        'tax_country',
        'account_status',
        'closure_reason',
        'closure_requested_at'
    ];

    protected $hidden = [
        'payment_details', // encrypted; never expose raw
    ];
}