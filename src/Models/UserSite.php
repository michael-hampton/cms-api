<?php

namespace App\Models;

class UserSite extends Model
{
    protected $table = 'oc_user_sites';

    protected $fillable = [
        'user_id',
        'site_id',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}