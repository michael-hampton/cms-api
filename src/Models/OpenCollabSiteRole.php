<?php

namespace App\Models;

class OpenCollabSiteRole extends Model
{
    protected $table = 'oc_site_roles';
    public $timestamps = false;
    protected $fillable = ['site_id', 'role_id', 'name', 'is_active'];
}
