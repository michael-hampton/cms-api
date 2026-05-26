<?php

namespace App\Models;

class OpenCollabSiteUserRole extends Model
{
    protected $table = 'oc_site_user_roles';
    public $timestamps = false;
    protected $fillable = ['site_id', 'user_id', 'role_id'];
}
