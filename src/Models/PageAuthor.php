<?php

namespace App\Models;

class PageAuthor extends Model
{
    protected $table = 'page_authors';

    protected $fillable = [
        'page_id',
        'author_id',
        'role',
        'sort_order'
    ];

    public function page($relation = false)
    {
        return $this->belongsTo(Page::class, 'page_id', 'id', $relation);
    }

    public function author($relation = false)
    {
        return $this->belongsTo(Author::class, 'author_id', 'id', $relation);
    }

    public function scopePrimary($query)
    {
        return $query->where('role', 'primary');
    }

    public function scopeContributor($query)
    {
        return $query->where('role', 'contributor');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}