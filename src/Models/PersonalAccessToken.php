<?php

namespace App\Models;

class PersonalAccessToken extends Model
{

    protected $table = 'personal_access_tokens';
    protected $fillable = ['tokenable_type', 'tokenable_id', 'site_id', 'name', 'token', 'abilities', 'last_used_at', 'expires_at'];
}