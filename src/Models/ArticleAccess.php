<?php

namespace App\Models;

class ArticleAccess extends Model
{
    protected $table = 'oc_article_access';

    protected $fillable = [
        'site_id',
        'page_id',
        'user_id',
        'email',
        'granted_at',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
    ];
}