<?php

namespace App\Models;

class OpenCollabPermission extends Model
{
    protected $table = 'oc_permissions';
    public $timestamps = false;
    protected $fillable = ['name', 'slug', 'group'];
}
