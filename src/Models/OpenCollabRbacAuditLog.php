<?php

namespace App\Models;

class OpenCollabRbacAuditLog extends Model
{
    protected $table = 'oc_rbac_audit_logs';
    public $timestamps = false;
    protected $fillable = ['site_id', 'actor_user_id', 'target_user_id', 'action', 'payload', 'created_at'];
    protected $casts = ['payload' => 'array'];
}
