<?php

namespace App\Models;

class ArticleQualityScore extends Model
{
    protected $table = 'oc_article_quality_scores';

    protected $fillable = [
        'article_id',
        'readability_score',
        'last_calculated_at',
    ];

    protected $casts = [
        'readability_score' => 'float',
        'last_calculated_at' => 'datetime',
    ];
}