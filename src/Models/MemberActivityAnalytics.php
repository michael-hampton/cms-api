<?php

namespace App\Models;

class MemberActivityAnalytics extends Model
{
    protected $table = 'member_activity_analytics';

    protected $fillable = [
        'member_id',
        'site_id',
        'summary',
        'scores',
        'behaviour',
        'trends',
        'interests',
        'flags',
    ];

    protected $casts = [
        'summary' => 'array',
        'scores' => 'array',
        'behaviour' => 'array',
        'trends' => 'array',
        'interests' => 'array',
        'flags' => 'array',
    ];
}