<?php

namespace App\Models;

class MemberStat extends Model
{
    protected $table = 'member_stats';

    protected $fillable = [
        'member_id',
        'site_id',
        'view_count',
        'like_count',
        'comment_count',
        'order_count',
        'reward_claimed_count',
        'articles_gifted_count',
        'articles_received_count',
        'last_computed_at',
        'data'
    ];

    protected $casts = [
        'view_count' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'order_count' => 'integer',
        'reward_claimed_count' => 'integer',
        'articles_gifted_count' => 'integer',
        'articles_received_count' => 'integer',
        'last_computed_at' => 'datetime',
        'data' => 'array',
    ];
}