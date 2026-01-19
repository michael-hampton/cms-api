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
        'highlighted_range',
        'created_at',
        'is_resolved',
        'resolved_by',
        'resolved_at',
        'mentions'
    ];

    protected $casts = [
        'highlighted_range' => 'json',
        'is_resolved' => 'boolean',
        'is_task' => 'boolean',
        'mentions' => 'array',
        'resolved_at' => 'datetime'
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

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function task()
    {
        return $this->belongsTo(BriefTask::class, 'task_id');
    }
}