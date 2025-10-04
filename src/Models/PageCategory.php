<?php

namespace App\Models;

class PageCategory extends Model
{
    protected $table = 'page_categories';
    protected $fillable = ['page_id', 'category_id', 'created_at', 'updated_at'];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class);
    }

    public function category(): ?Model
    {
        return $this->belongsTo(Category::class);
    }
}