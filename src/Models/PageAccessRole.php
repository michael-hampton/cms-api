<?php

namespace App\Models;

class PageAccessRole extends Model
{
    protected $table = 'page_access_roles';
    protected $fillable = ['page_id', 'role', 'created_at', 'updated_at'];

    public function page(): ?Model
    {
        return $this->belongsTo(Page::class);
    }
}