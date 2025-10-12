<?php

namespace App\Models;

class PageGridHistory extends Model
{
    protected $table = 'page_grid_history';
    protected $fillable = ['page_grid_id', 'user_id', 'action', 'changes', 'created_at'];

}