<?php

namespace App\Models;

class OpenCollabSiteUserPermission extends Model
{
    protected $table = 'oc_site_user_permissions';
    public $timestamps = false;
    protected $fillable = ['site_id', 'user_id', 'permission_id', 'granted'];
}
