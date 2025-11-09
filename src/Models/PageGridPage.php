<?php

namespace App\Models;

class PageGridPage extends Model
{
    protected $table = 'page_grid_pages';
    protected $fillable = ['page_grid_id', 'page_id'];
    protected $timestamps = false;

}