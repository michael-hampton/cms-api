<?php

namespace App\Models;

class OpenCollabRole extends Model
{
    protected $table = 'oc_roles';
    public $timestamps = false;
    protected $fillable = ['name', 'slug', 'is_system', 'created_at'];
}
