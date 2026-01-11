<?php

namespace App\Models;

class TrendingContent extends Model
{
    protected $table = 'trending_content';

    protected $fillable = [
        'page_id',
        'site_id',
        'view_count_24h',
        'comment_count_24h',
        'like_count_24h',
        'trending_score',
        'last_calculated_at'
    ];

    protected $casts = [
        'trending_score' => 'float',
        'view_count_24h' => 'integer',
        'comment_count_24h' => 'integer',
        'like_count_24h' => 'integer',
        'last_calculated_at' => 'datetime'
    ];

    public function page($relation = false)
    {
        return $this->belongsTo(Page::class, 'page_id', 'id', $relation);
    }
}