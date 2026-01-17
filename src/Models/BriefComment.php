<?php

namespace App\Models;

class BriefComment extends Model
{
    protected $table = 'brief_comments';

    protected $fillable = [
        'brief_id',
        'user_id',
        'parent_comment_id',
        'content',
        'highlighted_text',
        'highlighted_range'
    ];

    protected $casts = [
        'highlighted_range' => 'json'
    ];

    protected $alwaysInclude = [
        'user',
        'replies'
    ];

    public function brief(bool $relation = false)
    {
        return $this->belongsTo(Brief::class, 'brief_id', 'id', $relation);
    }

    public function user(bool $relation = false)
    {
        return $this->belongsTo(User::class, 'user_id', 'id', $relation);
    }

    public function parentComment(bool $relation = false)
    {
        return $this->belongsTo(BriefComment::class, 'parent_comment_id', 'id', $relation);
    }

    public function replies(bool $relation = false)
    {
        return $this->hasMany(BriefComment::class, 'parent_comment_id', 'id', $relation)
            ->orderBy('created_at', 'asc');
    }
}