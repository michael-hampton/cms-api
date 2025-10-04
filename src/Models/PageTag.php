<?php

namespace App\Models;

class PageTag extends Model
{
    protected $table = 'page_tags';
    protected $fillable = ['page_id', 'tag_id', 'created_at', 'updated_at'];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class);
    }

    public function tag(): ?Model
    {
        return $this->belongsTo(Tag::class);
    }
}