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
    ];

    protected $hidden = [
        'payment_details', // encrypted; never expose raw
    ];
}