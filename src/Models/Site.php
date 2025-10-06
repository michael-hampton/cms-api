<?php

namespace App\Models;

class Site extends Model
{
    protected $table = 'sites';

    protected $fillable = ['name', 'domain', 'is_active', 'slug'];

    public static function resolveSite(string $siteSlug): int
    {
        return Site::where('slug', $siteSlug)->first()->id ?? config('app.default_site_id', 1);
    }
}