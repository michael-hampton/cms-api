<?php

namespace App\Models;

class OpenCollabRolePermission extends Model
{
    protected $table = 'oc_role_permissions';
    public $timestamps = false;
    protected $fillable = ['role_id', 'permission_id'];
}
