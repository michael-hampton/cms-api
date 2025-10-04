<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;

class Comment extends Model
{
    protected $table = 'comments';

    protected $fillable = [
        'page_id', 'name', 'email', 'content', 'status',
        'ip_address', 'user_agent', 'parent_id', 'created_at', 'updated_at'
    ];

    protected $casts = [
        'page_id' => 'integer',
        'parent_id' => 'integer'
    ];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class, 'page_id', 'id');
    }

    public function parent(): ?Model
    {
        if (!$this->parent_id) {
            return null;
        }
        return Comment::find($this->parent_id);
    }

    public function replies(): Collection
    {
        return $this->hasMany(Comment::class, 'parent_id', 'id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isSpam(): bool
    {
        return $this->status === 'spam';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function approve(): void
    {
        $this->status = 'approved';
        $this->save();
    }

    public function reject(): void
    {
        $this->status = 'rejected';
        $this->save();
    }

    public function markAsSpam(): void
    {
        $this->status = 'spam';
        $this->save();
    }

    public function scopeApproved(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForPage(QueryBuilder $query, int $pageId): QueryBuilder
    {
        return $query->where('page_id', $pageId);
    }

    public function scopeTopLevel(QueryBuilder $query): QueryBuilder
    {
        return $query->whereNull('parent_id');
    }
}