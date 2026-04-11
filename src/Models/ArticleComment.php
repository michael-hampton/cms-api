<?php

namespace App\Models;

class ArticleComment extends Model
{
    protected $table = 'oc_article_comments';

    protected $fillable = [
        'article_id',
        'user_id',
        'parent_id',
        'position',
        'content',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Direct replies to this comment.
     */
    public function replies($relation = false)
    {
        return $this->hasMany(ArticleComment::class, 'parent_id', 'id', $relation)
            ->orderBy('created_at');
    }

    /**
     * Parent comment (if this is a reply).
     */
    public function parent($relation = false)
    {
        return $this->belongsTo(ArticleComment::class, 'parent_id', 'id', $relation);
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    public function user(): ?Model
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}