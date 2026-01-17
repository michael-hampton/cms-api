<?php

namespace App\Models;

class Brief extends Model
{
    protected $table = 'briefs';

    protected $fillable = [
        'title',
        'description',
        'owner_id',
        'category_id',
        'site_id',
        'status',
        'converted_page_id',
        'converted_at'
    ];

    protected $casts = [
        'converted_at' => 'datetime'
    ];

    protected $alwaysInclude = [
        'attachments',
        'comments',
        'owner',
        'category'
    ];

    public function attachments(bool $relation = false)
    {
        return $this->hasMany(BriefAttachment::class, 'brief_id', 'id', $relation)
            ->orderBy('sort_order');
    }

    public function comments(bool $relation = false)
    {
        return $this->hasMany(BriefComment::class, 'brief_id', 'id', $relation)
            ->whereNull('parent_comment_id')
            ->orderBy('created_at', 'desc');
    }

    public function owner(bool $relation = false)
    {
        return $this->belongsTo(User::class, 'owner_id', 'id', $relation);
    }

    public function category(bool $relation = false)
    {
        return $this->belongsTo(Category::class, 'category_id', 'id', $relation);
    }

    public function convertedPage(bool $relation = false)
    {
        return $this->belongsTo(Page::class, 'converted_page_id', 'id', $relation);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}